<?php
namespace App\Services\Events;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StreamMilestoneProjector
{
    public function persist(array $events,string $consumer): int
    {
        if (!$events) return 0;
        return DB::transaction(function() use($events,$consumer){
            $ids=array_values(array_unique(array_column(array_column($events,'payload'),'link_id')));sort($ids);
            // Serialize with analytics counter writes before determining the pre-stream baseline.
            $links=DB::table('links')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if($links->count()!==count($ids)) throw new RuntimeException('Missing milestone link');
            $token=(string)Str::uuid();$unique=[];
            foreach($events as $event){
                $id=$event['event_id'];$hash=hash('sha256',json_encode($event,JSON_THROW_ON_ERROR));
                if(isset($unique[$id])&&$unique[$id]['hash']!==$hash)throw new RuntimeException('Conflicting event ID');
                $unique[$id]=['event'=>$event,'hash'=>$hash];
            }
            foreach($ids as $id){
                $row=DB::table('stream_milestone_counters')->where('link_id',$id)->lockForUpdate()->first();
                if(!$row){
                    $alreadyWritten=DB::table('stream_processed_events')->where('consumer_group','analytics-writer')->where('link_id',$id)->count();
                    $baseline=max(0,(int)$links[$id]->click_count-$alreadyWritten);
                    DB::table('stream_milestone_counters')->insert(['link_id'=>$id,'count'=>$baseline,'legacy_baseline'=>$baseline]);
                }
            }
            $rows=[];
            foreach($unique as $id=>$item)$rows[]=['consumer_group'=>'milestones','event_id'=>$id,'claim_token'=>$token,'consumer_name'=>$consumer,'link_id'=>$item['event']['payload']['link_id'],'payload_hash'=>$item['hash'],'processed_at'=>now('UTC')];
            DB::table('stream_processed_events')->insertOrIgnore($rows);
            $ledger=DB::table('stream_processed_events')->where('consumer_group','milestones')->whereIn('event_id',array_keys($unique))->get();
            $counts=[];$processed=0;
            foreach($ledger as $row){
                if($row->payload_hash!==$unique[$row->event_id]['hash'])throw new RuntimeException('Previously processed event contents changed');
                if($row->claim_token!==$token)continue;
                $counts[$row->link_id]=($counts[$row->link_id]??0)+1;$processed++;
            }
            foreach($counts as $id=>$count){
                $before=(int)DB::table('stream_milestone_counters')->where('link_id',$id)->value('count');$after=$before+$count;
                DB::table('stream_milestone_counters')->where('link_id',$id)->update(['count'=>$after]);
                if($before<1000&&$after>=1000){
                    $eventId=(string)Str::uuid();$now=now('UTC');
                    $inserted=DB::table('link_milestones')->insertOrIgnore(['link_id'=>$id,'milestone'=>1000,'event_id'=>$eventId,'reached_at'=>$now]);
                    if($inserted){
                        $envelope=['event_id'=>$eventId,'event_type'=>'MilestoneReached','occurred_at'=>$now->format('Y-m-d\TH:i:s.u\Z'),'schema_version'=>1,'ordering_key'=>(string)$id,'payload'=>['link_id'=>(int)$id,'milestone'=>1000,'cumulative_count'=>$after]];
                        DB::table('event_outbox')->insert(['event_id'=>$eventId,'event_type'=>'MilestoneReached','stream_name'=>config('events.stream'),'envelope'=>json_encode($envelope,JSON_THROW_ON_ERROR),'created_at'=>$now]);
                    }
                }
            }
            return $processed;
        },5);
    }
}
