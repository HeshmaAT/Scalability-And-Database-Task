<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class Part9Eviction extends Command
{
    protected $signature = 'loadtest:eviction {host}';
    protected $description = 'Measure LFU eviction on a disposable Redis instance only';

    public function handle(): int
    {
        $host=$this->argument('host');
        if (! app()->environment('local','testing') || !preg_match('/\Apart9-eviction-[a-f0-9]+\z/',$host)) {
            throw new RuntimeException('Only disposable part9-eviction hosts are allowed.');
        }
        $r=new \Redis;$r->connect($host,6379,3);
        $initial=$r->info('stats');
        $value=str_repeat('x',8192);
        for($i=0;$i<10;$i++) { $r->set('hot:'.$i,$value); }
        $p=$r->multi(\Redis::PIPELINE);
        for($j=0;$j<2000;$j++) { for($i=0;$i<10;$i++) { $p->get('hot:'.$i); } }
        $p->exec();
        for($chunk=0;$chunk<100;$chunk++) {
            $p=$r->multi(\Redis::PIPELINE);
            for($i=0;$i<100;$i++) { $p->set('cold:'.($chunk*100+$i),$value); }
            for($i=0;$i<10;$i++) { $p->get('hot:'.$i); }
            $p->exec();
        }
        $hot=0;for($i=0;$i<10;$i++) { $hot+=$r->exists('hot:'.$i)?1:0; }
        $stats=$r->info('stats');$memory=$r->info('memory');
        $this->line(json_encode([
            'policy'=>'allkeys-lfu','maxmemory_bytes'=>16*1024*1024,
            'keys_written'=>10010,'value_bytes'=>8192,
            'evicted_keys'=>$stats['evicted_keys']-$initial['evicted_keys'],
            'remaining_keys'=>$r->dbSize(),'hot_keys_remaining'=>$hot,'hot_keys_total'=>10,
            'used_memory_bytes'=>$memory['used_memory'],
            'used_memory_rss_bytes'=>$memory['used_memory_rss'],
            'note'=>'Disposable Redis only. LFU retention is probabilistic; hot-key survival is observed, not guaranteed. maxmemory is not a process RSS limit.',
        ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
