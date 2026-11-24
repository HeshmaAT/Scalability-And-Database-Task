<?php
namespace App\Console\Commands;
use App\Models\User;use App\Models\Link;use App\Services\LinkCacheService;
use Illuminate\Console\Command;use Illuminate\Support\Facades\Hash;use Illuminate\Support\Str;
class PrepareLab6Dashboard extends Command
{
    protected $signature='lab6:prepare';protected $description='Create an isolated local dashboard account and link';
    public function handle():int{
        if(!app()->environment('local','testing')||!config('lab6.enabled'))throw new \RuntimeException('Local lab only');
        $run=bin2hex(random_bytes(4));$password=Str::random(24);
        $owner=User::forceCreate(['name'=>'Lab 6 Viewer','email'=>'lab6-'.$run.'@example.test','password'=>Hash::make($password)]);
        $link=Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab6_'.$run,'destination_url'=>'https://example.com/lab6','is_active'=>true]);
        app(LinkCacheService::class)->refresh($link->short_code);
        $this->line(json_encode(['email'=>$owner->email,'password'=>$password,'link_id'=>$link->id,'short_code'=>$link->short_code,'dashboard_url'=>'http://localhost:8080/lab6'],JSON_THROW_ON_ERROR));return 0;
    }
}
