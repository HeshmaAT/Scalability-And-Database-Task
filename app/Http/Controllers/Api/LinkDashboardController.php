<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\Dashboard\LinkDashboard;
use Illuminate\Http\Request;
class LinkDashboardController extends Controller
{
    private function reply(array $data){return response()->json($data)->header('Cache-Control','no-store, private');}
    public function links(Request $request){
        $links=Link::query()->useWritePdo()->where('user_id',$request->user()->id)->orderByDesc('id')->limit(20)
            ->get(['id','short_code','destination_url','is_active']);
        return $this->reply(['links'=>$links]);
    }
    public function snapshot(Request $request,string $id,LinkDashboard $dashboard){
        return $this->reply($dashboard->snapshot($request->user(),$id));
    }
    public function poll(Request $request,string $id,LinkDashboard $dashboard){
        $data=$request->validate(['since'=>['required','integer','min:0'],'timeout'=>['sometimes','integer','min:1','max:25']]);
        set_time_limit(30);
        return $this->reply($dashboard->wait($request->user(),$id,(int)$data['since'],(int)($data['timeout']??25)));
    }
}
