<?php
namespace App\Services;
use App\Models\Link;
use App\Models\User;
use App\Services\Events\LinkLifecycle;
use Illuminate\Support\Facades\DB;

class UpdateLinkService
{
    public function __construct(private LinkCacheService $cache) {}
    public function update(User $owner,string $id,array $changes): Link {
        $code=Link::query()->useWritePdo()->where('user_id',$owner->getKey())->whereKey($id)->firstOrFail(['short_code'])->short_code;
        return $this->cache->synchronized($code,function()use($owner,$id,$changes,$code):Link {
            $lockHeld=true;
            try{
                $link=DB::transaction(function()use($owner,$id,$changes):Link {
                    $link=Link::query()->useWritePdo()->where('user_id',$owner->getKey())->whereKey($id)->firstOrFail();
                    $expected=array_key_exists('expected_version',$changes)?(int)$changes['expected_version']:null;
                    app(LinkLifecycle::class)->change($link,$changes,$expected);
                    return $link;
                });
                DB::afterCommit(function()use($link,$code,&$lockHeld):void {
                    if($lockHeld)$this->cache->put($link);else $this->cache->refresh($code);
                });
                return $link;
            }finally{$lockHeld=false;}
        });
    }
}
