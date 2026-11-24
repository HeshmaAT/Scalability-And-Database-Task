<?php
return ['default'=>'reverb','servers'=>['reverb'=>[
    'host'=>'0.0.0.0','port'=>8080,'path'=>'','hostname'=>null,'options'=>['tls'=>[]],'max_request_size'=>10000,
    'scaling'=>['enabled'=>true,'channel'=>'lab6-reverb','server'=>[
        'host'=>'redis-reverb','port'=>6379,'username'=>null,'password'=>null,'database'=>0,'timeout'=>60,
    ]],'pulse_ingest_interval'=>15,'telescope_ingest_interval'=>15,
]],'apps'=>['provider'=>'config','apps'=>[[
    'key'=>'lab6-public-key','secret'=>hash('sha256',(string)env('APP_KEY').'lab6-reverb'),'app_id'=>'lab6-dashboard',
    'options'=>['host'=>env('REVERB_HOST','reverb-1'),'port'=>8080,'scheme'=>'http','useTLS'=>false],
    'allowed_origins'=>['localhost','127.0.0.1','go.brand.test'],'ping_interval'=>20,'activity_timeout'=>30,
    'max_connections'=>null,'max_message_size'=>10000,'max_channels_per_connection'=>1000,'accept_client_events_from'=>'members',
]]]];
