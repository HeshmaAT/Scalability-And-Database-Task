<?php
namespace App\Services\Events;
use App\Services\ClickMaintenanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StreamAnalyticsWriter
{
    public function persist(array $events, string $consumer): int
    {
        if (!$events) return 0;
        $traceStarted = microtime(true);
        $inserted = 0;
        $acquired = app(ClickMaintenanceService::class)->run(function () use ($events, $consumer, &$inserted) {
            $inserted = DB::transaction(function () use ($events, $consumer) {
                $token = (string) Str::uuid();
                $unique = [];
                foreach ($events as $event) {
                    $id = $event['event_id'];
                    $hash = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
                    if (isset($unique[$id]) && $unique[$id]['hash'] !== $hash) throw new RuntimeException('Event ID reused with different contents');
                    $unique[$id] = ['event'=>$event,'hash'=>$hash];
                }
                $reservations = [];
                foreach ($unique as $id=>$item) $reservations[] = [
                    'consumer_group'=>'analytics-writer','event_id'=>$id,'claim_token'=>$token,
                    'consumer_name'=>$consumer,'link_id'=>$item['event']['payload']['link_id'],
                    'payload_hash'=>$item['hash'],'processed_at'=>now('UTC'),
                ];
                DB::table('stream_processed_events')->insertOrIgnore($reservations);
                $existing = DB::table('stream_processed_events')->useWritePdo()
                    ->where('consumer_group','analytics-writer')->whereIn('event_id',array_keys($unique))->get();
                foreach ($existing as $row) {
                    if ($unique[$row->event_id]['hash'] !== $row->payload_hash) throw new RuntimeException('Conflicting previously processed event');
                }
                $boundary = DB::table('click_archive_runs')->useWritePdo()->where('status','completed')->max('upper_bound');
                $counts=[]; $archived=[]; $rows=[]; $total=0;
                foreach ($existing as $row) {
                    if ($row->claim_token !== $token) continue;
                    $event=$unique[$row->event_id]['event']; $payload=$event['payload']; $id=$payload['link_id'];
                    $counts[$id]=($counts[$id]??0)+1; $total++;
                    if ($boundary !== null && $payload['clicked_at'] < $boundary) {
                        $archived[$id]=($archived[$id]??0)+1;
                    } else $rows[]=['link_id'=>$id,'clicked_at'=>$payload['clicked_at'],'visitor_hash'=>hex2bin($payload['visitor_hash'])];
                }
                if (!$counts) return 0;
                ksort($counts);
                $ids=DB::table('links')->whereIn('id',array_keys($counts))->orderBy('id')->lockForUpdate()->pluck('id');
                if ($ids->count() !== count($counts)) throw new RuntimeException('Missing link in stream batch');
                if ($rows) DB::table('link_clicks')->insert($rows);
                foreach ($counts as $id=>$count) DB::table('links')->where('id',$id)->incrementEach([
                    'click_count'=>$count,'archived_click_count'=>$archived[$id]??0,
                ]);
                return $total;
            },5);
        }, lockWaitSeconds: 1);
        if (!$acquired) throw new \App\Exceptions\AnalyticsMaintenanceBusy('Analytics maintenance is busy; leave events pending');
        $traceMs = (microtime(true) - $traceStarted) * 1000;
        if ($traceMs >= 100) file_put_contents('php://stderr', json_encode(['lab6_trace'=>'analytics_persist_slow','at'=>gmdate('c'),'elapsed_ms'=>round($traceMs,3),'batch_size'=>count($events),'inserted'=>$inserted]).PHP_EOL);
        return $inserted;
    }
}
