<?php
namespace App\Services\Events;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class WebhookTransport
{
    public function validateUrl(string $url):void{
        $p=parse_url($url);
        if(!$p||isset($p['user'])||isset($p['pass'])||isset($p['fragment']))throw new RuntimeException('Invalid webhook URL');
        $host=strtolower(trim($p['host']??'','[]'));
        $test=config('webhooks.test_receiver')&&app()->environment('local','testing')&&$host==='lab5-webhook-receiver'&&($p['scheme']??'')==='http'&&($p['port']??80)===80;
        if($test)return;
        if(($p['scheme']??'')!=='https'||($p['port']??443)!==443||!$host)throw new RuntimeException('HTTPS port 443 required');
        if($host==='localhost'||!str_contains($host,'.')||preg_match('/\.(localhost|local|internal|test)$/',$host))throw new RuntimeException('Internal hostname rejected');
        if(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Non-public IP rejected');
        // DNS answers are checked at connection time by the mandatory Squid destination-IP ACL.
    }
    public function send(string $url,string $body,string $secret,string $eventId):int{
        $this->validateUrl($url);
        $proxy=config('webhooks.proxy');
        if(!is_string($proxy)||!str_starts_with($proxy,'http://'))throw new RuntimeException('Forward proxy required');
        $timestamp=(string)time();$signature=hash_hmac('sha256',$timestamp.'.'.$body,$secret);
        return Http::withOptions(['proxy'=>['http'=>$proxy,'https'=>$proxy,'no'=>[]],'allow_redirects'=>false,'verify'=>true])
            ->connectTimeout(2)->timeout(4)
            ->withHeaders(['Idempotency-Key'=>$eventId,'X-Webhook-Timestamp'=>$timestamp,'X-Webhook-Signature'=>'sha256='.$signature,'Accept'=>'application/json'])
            ->withBody($body,'application/json')->post($url)->status();
    }
}
