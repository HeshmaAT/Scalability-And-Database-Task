<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local','testing')) throw new RuntimeException('Lab only');
// Dedicated disposable benchmark broker, never the live click stream.
config(['events.host'=>'lab5-replay-redis']);
$redis=app(\App\Services\Events\EventStream::class)->client();
$redis->setOption(\Redis::OPT_READ_TIMEOUT,30.0);
$key='lab5:replay:'.bin2hex(random_bytes(8));
$total=1000000;
$first=null;$last=null;
try {
    foreach (['analytics-writer','dashboard','milestones'] as $group) {
        if (!$redis->rawCommand('XGROUP','CREATE',$key,$group,'0','MKSTREAM')) throw new RuntimeException('Group creation failed');
    }
    $started=microtime(true);
    $occurred=now('UTC')->format('Y-m-d\TH:i:s\Z');
    $clicked=now('UTC')->format('Y-m-d H:i:s');
    $visitor=hash('sha256','synthetic-replay-visitor');
    for ($base=0;$base<$total;$base+=1000) {
        $redis->multi(\Redis::PIPELINE);
        for ($i=$base;$i<min($base+1000,$total);$i++) {
            $event=['event_id'=>(string)\Illuminate\Support\Str::uuid(),'event_type'=>'LinkClicked',
                'occurred_at'=>$occurred,'schema_version'=>1,'ordering_key'=>'1',
                'payload'=>['link_id'=>1,'clicked_at'=>$clicked,'visitor_hash'=>$visitor,'replay_sequence'=>$i]];
            $redis->rawCommand('XADD',$key,'MAXLEN','~','1200000','*','event',json_encode($event,JSON_THROW_ON_ERROR));
        }
        $ids=$redis->exec();
        if (count($ids)!==1000) throw new RuntimeException('Incomplete pipeline');
        foreach ($ids as $id) if (!is_string($id)) throw new RuntimeException('Publication rejected, possibly memory limit');
        $first??=$ids[0];$last=$ids[count($ids)-1];
        if (($base+1000)%100000===0) echo 'Published '.($base+1000)." / $total\n";
    }
    $publishSeconds=microtime(true)-$started;
    $length=$redis->rawCommand('XLEN',$key);
    if ($length!==$total) throw new RuntimeException('History was trimmed or incomplete');
    $memory=$redis->rawCommand('MEMORY','USAGE',$key);
    $groupsBefore=$redis->rawCommand('XINFO','GROUPS',$key);
    // Create fraud-check only AFTER all one million facts have been published.
    if (!$redis->rawCommand('XGROUP','CREATE',$key,'fraud-check','0')) throw new RuntimeException('Late group creation failed');
    $started=microtime(true);$seen=0;$replayedFirst=null;$replayedLast=null;
    while (true) {
        $reply=$redis->rawCommand('XREADGROUP','GROUP','fraud-check','fraud-1','COUNT','2000','STREAMS',$key,'>');
        $entries=$reply[0][1]??[];
        if (!$entries) break;
        $ack=[];
        foreach ($entries as [$id,$fields]) {
            $event=json_decode($fields[1],true,512,JSON_THROW_ON_ERROR);
            if ($event['payload']['replay_sequence']!==$seen) throw new RuntimeException('Replay missing, repeated or out of order');
            $replayedFirst??=$id;$replayedLast=$id;$seen++;$ack[]=$id;
        }
        if ($redis->rawCommand('XACK',$key,'fraud-check',...$ack)!==count($ack)) throw new RuntimeException('Incomplete ack');
        if ($seen%100000===0) echo "Replayed $seen / $total\n";
    }
    $replaySeconds=microtime(true)-$started;
    $pending=$redis->rawCommand('XPENDING',$key,'fraud-check');
    if ($seen!==$total || $pending[0]!==0 || $first!==$replayedFirst || $last!==$replayedLast) throw new RuntimeException('Replay verification failed');
    $groupsAfter=$redis->rawCommand('XINFO','GROUPS',$key);
    // Prove replay only sees retained history, despite starting a group at zero.
    $redis->rawCommand('XTRIM',$key,'MAXLEN','=','1000');
    $redis->rawCommand('XGROUP','CREATE',$key,'fraud-after-trim','0');
    $reply=$redis->rawCommand('XREADGROUP','GROUP','fraud-after-trim','fraud-2','COUNT','2000','STREAMS',$key,'>');
    $remaining=$reply[0][1]??[];
    if (count($remaining)!==1000) throw new RuntimeException('Retention demonstration failed');
    $oldest=json_decode($remaining[0][1][1],true,512,JSON_THROW_ON_ERROR)['payload']['replay_sequence'];
    if ($oldest!==999000) throw new RuntimeException('Unexpected retained history');
    echo 'SUMMARY '.json_encode(['published'=>$total,'retained_before_trim'=>$length,'late_group'=>'fraud-check',
        'replayed'=>$seen,'pending_after_ack'=>$pending[0],'first_id_matches'=>$first===$replayedFirst,'last_id_matches'=>$last===$replayedLast,
        'publish_seconds'=>round($publishSeconds,3),'replay_seconds'=>round($replaySeconds,3),'stream_memory_bytes'=>$memory,
        'retained_after_trim'=>count($remaining),'oldest_sequence_after_trim'=>$oldest,
        'groups_before_fraud'=>$groupsBefore,'groups_after_fraud'=>$groupsAfter,
        'method'=>'Synthetic LinkClicked envelopes on a dedicated disposable Redis broker; no application projections or link counters updated. This is retained-history replay, not a million real HTTP redirects or durable failure-recovery proof.'])."\n";
} finally { $redis->close(); }
