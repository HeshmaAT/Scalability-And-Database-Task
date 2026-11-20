<?php
return ['proxy'=>env('LARAVEL_HTTP_PROXY','http://squid:3128'),'max_attempts'=>5,'timeout'=>4,'test_receiver'=>false];
