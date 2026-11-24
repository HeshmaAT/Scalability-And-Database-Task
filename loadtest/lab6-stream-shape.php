<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing'))throw new RuntimeException('Local probe only');
$r=app(\App\Services\Events\EventStream::class)->client();$r->setOption(\Redis::OPT_READ_TIMEOUT,2);
$key='lab5:test:lab6-response-shape:'.bin2hex(random_bytes(6));
function shape($value,$depth=0){
    if(!is_array($value))return get_debug_type($value).(is_string($value)?'('.strlen($value).')':'');
    if($depth>=4)return ['type'=>'array','count'=>count($value)];
    $children=[];foreach(array_slice($value,0,3,true) as $k=>$v)$children[(string)$k]=shape($v,$depth+1);
    return ['type'=>'array','count'=>count($value),'children'=>$children];
}
function show($label,$reply){echo $label,': ',json_encode(shape($reply),JSON_THROW_ON_ERROR),PHP_EOL;}
try{
    echo 'phpredis_version=',phpversion('redis'),PHP_EOL;
    $r->rawCommand('XGROUP','CREATE',$key,'probe','0','MKSTREAM');
    show('own_pending_empty',$r->rawCommand('XREADGROUP','GROUP','probe','one','COUNT','10','STREAMS',$key,'0'));
    show('autoclaim_empty',$r->rawCommand('XAUTOCLAIM',$key,'probe','one','60000','0-0','COUNT','10'));
    $id=$r->rawCommand('XADD',$key,'*','probe','value');
    show('new_entry',$r->rawCommand('XREADGROUP','GROUP','probe','one','COUNT','10','STREAMS',$key,'>'));
    show('own_pending_entry',$r->rawCommand('XREADGROUP','GROUP','probe','one','COUNT','10','STREAMS',$key,'0'));
    $r->rawCommand('XACK',$key,'probe',$id);
    show('new_empty_timeout',$r->rawCommand('XREADGROUP','GROUP','probe','one','COUNT','10','BLOCK','1000','STREAMS',$key,'>'));
}finally{$r->rawCommand('DEL',$key);$r->close();}
