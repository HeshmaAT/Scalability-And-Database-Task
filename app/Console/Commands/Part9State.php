<?php
namespace App\Console\Commands;

use App\Services\ClickBufferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class Part9State extends Command
{
    protected $signature = 'loadtest:state';
    protected $description = 'Read load-test buffer and failed-job counts';

    public function handle(ClickBufferService $buffer): int
    {
        $pending = config('part9.profile') === 'A'
            ? DB::table('part9_click_buffer')->useWritePdo()->whereNull('batch_id')->count()
            : Redis::connection('queue')->llen('analytics:pending');
        $this->line(json_encode([
            'profile' => config('part9.profile'),
            'pending' => $pending,
            'processing' => count($buffer->processingIds()),
            'jobs' => Queue::connection()->size('default'),
            'failed_jobs' => DB::table('failed_jobs')->useWritePdo()->count(),
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
