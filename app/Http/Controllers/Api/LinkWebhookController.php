<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\Events\WebhookTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class LinkWebhookController extends Controller
{
    public function store(Request $request,string $id,WebhookTransport $transport){
        $link=Link::query()->useWritePdo()->where('user_id',$request->user()->id)->findOrFail($id);
        $data=$request->validate(['url'=>['required','string','max:2000'],'secret'=>['required','string','min:32','max:256']]);
        try{$transport->validateUrl($data['url']);}catch(\RuntimeException $e){throw ValidationException::withMessages(['url'=>$e->getMessage()]);}
        DB::table('link_webhooks')->updateOrInsert(['link_id'=>$link->id],['url'=>$data['url'],'secret'=>Crypt::encryptString($data['secret']),'enabled'=>true,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        return response()->json(['link_id'=>$link->id,'url'=>$data['url'],'enabled'=>true]);
    }
    public function destroy(Request $request,string $id){
        $link=Link::query()->useWritePdo()->where('user_id',$request->user()->id)->findOrFail($id);
        DB::table('link_webhooks')->where('link_id',$link->id)->update(['enabled'=>false,'updated_at'=>now('UTC')]);
        return response()->noContent();
    }
}
