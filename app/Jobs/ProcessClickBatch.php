<?php

namespace App\Jobs;

use App\Services\ClickBufferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessClickBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $batchId)
    {
    }

    public function handle(ClickBufferService $buffer): void
    {
        $events = $buffer->events($this->batchId);

        if ($events === []) {
            return;
        }

        try {
            DB::transaction(function () use ($events): void {
                DB::table('processed_click_batches')->insert([
                    'id' => $this->batchId,
                    'processed_at' => now('UTC'),
                ]);

                $rows = [];
                $counts = [];

                foreach ($events as $event) {
                    $rows[] = [
                        'link_id' => $event['link_id'],
                        'clicked_at' => $event['clicked_at'],
                        'visitor_hash' => hex2bin($event['visitor_hash']),
                    ];

                    $id = $event['link_id'];
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }

                DB::table('link_clicks')->insert($rows);

                ksort($counts);

                $existingIds = DB::table('links')
                    ->whereIn('id', array_keys($counts))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($existingIds->count() !== count($counts)) {
                    throw new RuntimeException('Click batch references a missing link.');
                }

                DB::table('link_clicks')->insert($rows);

                foreach ($counts as $linkId => $count) {
                    DB::table('links')
                        ->where('id', $linkId)
                        ->increment('click_count', $count);
                }
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $processed = DB::table('processed_click_batches')
                ->useWritePdo()
                ->where('id', $this->batchId)
                ->exists();

            if (! $processed) {
                throw $exception;
            }
        }

        $buffer->acknowledge($this->batchId);
    }
}
