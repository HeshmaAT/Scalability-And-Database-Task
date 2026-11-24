<?php
namespace App\Services\Dashboard;
use App\Models\Link;use App\Models\User;use Illuminate\Support\Facades\DB;
class LinkDashboard{
    public function snapshot(User $owner,string $id):array{
        // Read the authoritative count and catch-up cursor from one consistent DB snapshot.
        return DB::transaction(function()use($owner,$id){
            $link=Link::query()->useWritePdo()->where('user_id',$owner->id)->findOrFail($id,
                ['id','short_code','destination_url','click_count','is_active','expires_at']);
            $cursor=(int)DB::table('dashboard_events')->useWritePdo()->where('link_id',$link->id)->max('id');
            return ['link_id'=>$link->id,'short_code'=>$link->short_code,'destination_url'=>$link->destination_url,
                'total_clicks'=>(int)$link->click_count,'is_active'=>(bool)$link->is_active,'expires_at'=>$link->expires_at,
                'observed_at'=>now('UTC')->toISOString(),'instance'=>gethostname(),'cursor'=>$cursor];
        });
    }
    public function wait(User $owner,string $id,int $since,int $timeout):array{
        $deadline=microtime(true)+$timeout;
        do{$snapshot=$this->snapshot($owner,$id);
            if($snapshot['total_clicks']!==$since)return $snapshot+['changed'=>true];
            if(microtime(true)>=$deadline||connection_aborted())return $snapshot+['changed'=>false];
            usleep(250000);
        }while(true);
    }
}
