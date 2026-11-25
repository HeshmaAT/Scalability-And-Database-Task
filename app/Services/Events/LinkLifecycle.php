<?php
namespace App\Services\Events;
use App\Models\Link;
use App\Models\LinkEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LinkLifecycle
{
    public function state(Link $link): array {
        return ['destination_url'=>(string)$link->destination_url,'is_active'=>(bool)$link->is_active,
            'expires_at'=>$this->expiry($link->expires_at)];
    }
    private function expiry(mixed $value): ?string {
        return $value===null?null:Carbon::parse($value,'UTC')->utc()->format('Y-m-d\TH:i:s\Z');
    }
    public function created(Link $link): void {
        $state=$this->state($link);$this->append($link,'LinkCreated',$state,$state);
    }
    public function change(Link $link,array $changes,?int $expectedVersion=null): void {
        $before=(int)$link->lifecycle_version;
        if($expectedVersion!==null&&$before!==$expectedVersion)throw new ConflictHttpException('Lifecycle version conflict; reload the link');
        if($before===0){$baseline=$this->state($link);$this->append($link,'LinkImported',$baseline,$baseline);}
        $delta=[];$state=$this->state($link);
        if(array_key_exists('is_active',$changes))$delta['is_active']=(bool)$changes['is_active'];
        if(array_key_exists('expires_at',$changes))$delta['expires_at']=$this->expiry($changes['expires_at']);
        if(array_key_exists('destination_url',$changes))$delta['destination_url']=(string)$changes['destination_url'];
        if(!$delta)return;
        $state=array_replace($state,$delta);
        $type=array_key_exists('is_active',$delta)?($delta['is_active']?'LinkResumed':'LinkPaused'):'LinkUpdated';
        // An explicit resume/pause consumes a version even if state already matches the intent.
        $this->append($link,$type,$delta,$state);
    }
    private function append(Link $link,string $type,array $changes,array $state): void {
        if(DB::connection()->transactionLevel()<1)throw new \LogicException('Lifecycle write requires a database transaction');
        $before=(int)$link->lifecycle_version;$version=$before+1;
        $values=['destination_url'=>$state['destination_url'],'is_active'=>$state['is_active'],
            'expires_at'=>$state['expires_at']===null?null:Carbon::parse($state['expires_at'])->utc()->format('Y-m-d H:i:s'),
            'lifecycle_version'=>$version];
        $changed=Link::query()->whereKey($link->id)->where('lifecycle_version',$before)->update($values);
        if($changed!==1)throw new ConflictHttpException('Lifecycle version conflict; reload the link');
        $id=(string)Str::uuid();$now=now('UTC');
        $payload=['link_id'=>(int)$link->id,'version'=>$version,'short_code'=>$link->short_code,
            'changes'=>$changes,'state'=>$state];
        DB::table('link_events')->insert(['link_id'=>$link->id,'version'=>$version,'event_id'=>$id,'type'=>$type,
            'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'occurred_at'=>$now->format('Y-m-d H:i:s.u')]);
        $envelope=['event_id'=>$id,'event_type'=>$type,'occurred_at'=>$now->format('Y-m-d\TH:i:s.u\Z'),
            'schema_version'=>1,'ordering_key'=>(string)$link->id,'payload'=>$payload];
        DB::table('event_outbox')->insert(['event_id'=>$id,'event_type'=>$type,'stream_name'=>config('events.stream'),
            'envelope'=>json_encode($envelope,JSON_THROW_ON_ERROR),'created_at'=>$now,'published_at'=>null]);
        $link->forceFill($values);$link->syncOriginal();
    }
    public function replay(array $ids): array {
        $result=[];
        foreach(LinkEvent::query()->useWritePdo()->whereIn('link_id',$ids)->orderBy('link_id')->orderBy('version')->get() as $event){
            $id=(int)$event->link_id;$previous=$result[$id]['version']??0;
            if($event->version!==$previous+1)throw new \LogicException('Lifecycle history has a version gap');
            $payload=$event->payload;
            if($previous===0&&!in_array($event->type,['LinkCreated','LinkImported'],true))throw new \LogicException('Lifecycle baseline missing');
            $state=$previous===0?[]:$result[$id]['state'];
            foreach(($payload['changes']??[]) as $key=>$value){if(!in_array($key,['destination_url','is_active','expires_at'],true))throw new \LogicException('Unknown lifecycle state field');$state[$key]=$value;}
            if(count($state)!==3||$state!==$payload['state'])throw new \LogicException('Lifecycle replay state mismatch');
            $result[$id]=['version'=>$event->version,'state'=>$state];
        }
        if(count($result)!==count(array_unique($ids)))throw new \LogicException('Lifecycle history is incomplete');
        return $result;
    }
}
