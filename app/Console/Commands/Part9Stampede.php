<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class Part9Stampede extends Command
{
    protected $signature = 'loadtest:stampede {code} {--workers=10}';
    protected $description = 'Compare cold-cache query fanout with and without the application lock';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing') || config('part9.profile') === 'A') {
            throw new RuntimeException('Run on the normal local Redis stack.');
        }
        $workers=(int)$this->option('workers');
        if ($workers < 2 || $workers > 20) { throw new RuntimeException('Workers must be 2..20.'); }
        $code=$this->argument('code');
        // Require the fixture to be visible on the read connection to avoid measuring replication lag.
        if (! DB::table('links')->where('short_code',$code)->where('is_active',true)->exists()) {
            throw new RuntimeException('Fixture must be active and visible on replica first.');
        }
        $results=[];
        foreach (['without_lock','with_lock'] as $mode) {
            cache()->forget('link:'.$code);
            $start=microtime(true)+8;
            $processes=[];
            try {
                for($i=0;$i<$workers;$i++) {
                    $args=[PHP_BINARY,'artisan','loadtest:cache-worker',$code,'--start-at='.$start];
                    if($mode==='without_lock') { $args[]='--unsafe'; }
                    $p=new Process($args,base_path());
                    $p->setTimeout(45);$p->start();$processes[]=$p;
                }
                $samples=[];
                foreach($processes as $p) {
                    $p->wait();
                    if(!$p->isSuccessful()) { throw new RuntimeException('Worker failed: '.$p->getErrorOutput().$p->getOutput()); }
                    $samples[]=json_decode(trim($p->getOutput()),true,512,JSON_THROW_ON_ERROR);
                }
                $starts=array_column($samples,'started_at');
                $results[$mode]=[
                    'workers'=>$workers,
                    'database_link_selects'=>array_sum(array_column($samples,'queries')),
                    'start_spread_ms'=>round((max($starts)-min($starts))*1000,2),
                    'samples'=>$samples,
                ];
            } finally {
                foreach($processes as $p) { if($p->isRunning()) { $p->stop(1); } }
            }
        }
        $results['method']='Concurrent CLI processes, cold key in both rounds, same injected 100ms delay after each links SELECT; query fanout diagnostic, not an HTTP capacity test.';
        $this->line(json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
