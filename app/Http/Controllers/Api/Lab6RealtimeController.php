<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\Dashboard\LinkDashboard;
use App\Services\Dashboard\DashboardProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
class Lab6RealtimeController extends Controller{
    public function config(){return response()->json(['key'=>config('broadcasting.connections.reverb.key')])->header('Cache-Control','no-store');}
    public function authorizeChannel(Request $r){
        $r->validate(['socket_id'=>['required','regex:/^[0-9]+\.[0-9]+$/'],'channel_name'=>['required','regex:/^private-links\.[0-9]+$/']]);
        return Broadcast::connection('reverb')->auth($r);
    }
    public function events(Request $r,string $id,LinkDashboard $dashboard,DashboardProjection $projection){
        $data=$r->validate(['since'=>['sometimes','integer','min:0']]);
        Link::query()->useWritePdo()->where('user_id',$r->user()->id)->findOrFail($id);
        $rows=DB::table('dashboard_events')->useWritePdo()->where('link_id',$id)->where('id','>',(int)($data['since']??0))->orderBy('id')->limit(201)->get();
        $more=$rows->count()>200;$page=$rows->take(200);
        return response()->json(['events'=>$page->map(fn($row)=>$projection->payload($row))->values(),
            'next_cursor'=>$page->isEmpty()?(int)($data['since']??0):(int)$page->last()->id,'has_more'=>$more,
            'snapshot'=>$dashboard->snapshot($r->user(),$id)])->header('Cache-Control','no-store, private');
    }
}
