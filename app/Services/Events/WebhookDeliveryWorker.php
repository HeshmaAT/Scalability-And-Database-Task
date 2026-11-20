<?php
namespace App\Services\Events;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class WebhookDeliveryWorker
{
    public function tick(?string $onlyEvent=null):?array{
        $row=DB::transaction(function()use($onlyEvent){
            $query=DB::table('webhook_deliveries')->where(function($q){
                $q->where(function($q){$q->whereIn('status',['pending','retry'])->where('available_at','<=',now('UTC'));})
                  ->orWhere(function($q){$q->where('status','processing')->where('locked_until','<=',now('UTC'));});
            });
            if($onlyEvent)$query->where('event_id',$onlyEvent);
            $row=$query->orderBy('available_at')->lock('FOR UPDATE SKIP LOCKED')->first();
            if(!$row)return null;
            if($row->attempts>=config('webhooks.max_attempts')){DB::table('webhook_deliveries')->where('event_id',$row->event_id)->update(['status'=>'dead','updated_at'=>now('UTC')]);return null;}
            $row->claim_token=(string)Str::uuid();$row->attempts++;
            DB::table('webhook_deliveries')->where('event_id',$row->event_id)->update(['status'=>'processing','attempts'=>$row->attempts,'claim_token'=>$row->claim_token,'locked_until'=>now('UTC')->addSeconds(30),'updated_at'=>now('UTC')]);
            return $row;
        });
        if(!$row)return null;
        $status=null;$error=null;
        try{$status=app(WebhookTransport::class)->send($row->url,$row->body,Crypt::decryptString($row->secret),$row->event_id);}
        catch(\Throwable $e){$error=class_basename($e);}
        $success=$status!==null&&$status>=200&&$status<300;
        $base=min(300,2**min(16,$row->attempts-1));$delay=$base+random_int(0,$base);
        $state=$success?'delivered':($row->attempts>=config('webhooks.max_attempts')?'dead':'retry');
        DB::table('webhook_deliveries')->where('event_id',$row->event_id)->where('claim_token',$row->claim_token)->update([
            'status'=>$state,'available_at'=>now('UTC')->addSeconds($delay),'locked_until'=>null,'last_status'=>$status,'last_error'=>$error??($success?null:'HTTP_'.$status),'updated_at'=>now('UTC'),
        ]);
        return ['event_id'=>$row->event_id,'attempt'=>$row->attempts,'status'=>$state,'http_status'=>$status,'retry_delay_seconds'=>$success?null:$delay];
    }
}
