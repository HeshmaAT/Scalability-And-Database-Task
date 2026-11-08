<?php

namespace App\Console\Commands;

use App\Jobs\ProcessClickBatch;
use App\Services\ClickBufferService;
use Illuminate\Console\Command;

class FlushClickAnalytics extends Command
{
    protected $signature = 'analytics:flush';

    protected $description = 'Dispatch pending and unfinished click batches';

    public function handle(ClickBufferService $buffer): int
    {
        foreach ($buffer->processingIds() as $batchId) {
            ProcessClickBatch::dispatch($batchId);
        }

        for ($i = 0; $i < config('analytics.max_batches_per_run'); $i++) {
            $batchId = $buffer->claim();

            if ($batchId === null) {
                break;
            }

            ProcessClickBatch::dispatch($batchId);
        }

        return self::SUCCESS;
    }
}
