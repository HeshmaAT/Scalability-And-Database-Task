<?php
return ['default'=>env('BROADCAST_CONNECTION','reverb'),'connections'=>[
    'reverb'=>['driver'=>'reverb','key'=>'lab6-public-key','secret'=>hash('sha256',(string)env('APP_KEY').'lab6-reverb'),
        'app_id'=>'lab6-dashboard','options'=>['host'=>env('REVERB_HOST','reverb-1'),'port'=>8080,'scheme'=>'http','useTLS'=>false],
        'client_options'=>['connect_timeout'=>2,'timeout'=>4,'proxy'=>'']],
    'null'=>['driver'=>'null'],'log'=>['driver'=>'log'],
]];
