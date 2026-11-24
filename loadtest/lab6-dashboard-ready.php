<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$redis=app(\App\Services\Events\EventStream::class)->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2);
for($i=0;$i<300;$i++){
    $groups=$redis->rawCommand('XINFO','GROUPS',config('events.stream'));
    foreach($groups as$fields){$row=[];for($j=0;$j<count($fields);$j+=2)$row[$fields[$j]]=$fields[$j+1];
        if($row['name']==='dashboard'&&$row['pending']===0&&isset($row['lag'])&&$row['lag']===0){echo "Dashboard group caught up.\n";exit(0);}}
    usleep(200000);
}throw new RuntimeException('Dashboard group did not catch up; inspect dashboard-consumer logs');
