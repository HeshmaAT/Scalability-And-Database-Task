<?php
namespace App\Services\Dashboard;
use Illuminate\Support\Facades\DB;
class DashboardProjection{
    public function store(array $event):object{
        return DB::transaction(function()use($event){
            $hash=hash('sha256',json_encode($event,JSON_THROW_ON_ERROR));
            $existing=DB::table('dashboard_events')->useWritePdo()->where('event_id',$event['event_id'])->first();
            if($existing){if($existing->source_hash!==$hash)throw new \RuntimeException('Dashboard event ID conflict');return $existing;}
            // The dashboard observes committed analytics, never a speculative increment.
            $committed=DB::table('stream_processed_events')->useWritePdo()->where('consumer_group','analytics-writer')->where('event_id',$event['event_id'])->exists();
            if(!$committed)throw new \LogicException('Analytics has not committed this click yet');
            $count=DB::table('links')->useWritePdo()->where('id',$event['payload']['link_id'])->value('click_count');
            if($count===null)throw new \RuntimeException('Missing dashboard link');
            DB::table('dashboard_events')->insertOrIgnore(['event_id'=>$event['event_id'],'link_id'=>$event['payload']['link_id'],
                'source_hash'=>$hash,'total_clicks'=>$count,'occurred_at'=>$event['occurred_at'],'projected_at'=>now('UTC')]);
            $row=DB::table('dashboard_events')->useWritePdo()->where('event_id',$event['event_id'])->first();
            if(!$row||$row->source_hash!==$hash)throw new \RuntimeException('Conflicting dashboard projection');
            return $row;
        },5);
    }
    public function payload(object $row):array{return ['cursor'=>(int)$row->id,'event_id'=>$row->event_id,
        'link_id'=>(int)$row->link_id,'total_clicks'=>(int)$row->total_clicks,'occurred_at'=>$row->occurred_at,'projected_at'=>$row->projected_at];}
}
