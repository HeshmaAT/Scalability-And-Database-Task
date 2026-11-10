<?php

namespace App\Jobs;

use App\Services\ClickBufferService;
use App\Services\ClickMaintenanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessClickBatch implements ShouldQueue
{
    use Queueable;

    // Waiting for maintenance should not exhaust job attempts.
    public int $tries = 0;

    // Actual unhandled failures still have a limit.
    public int $maxExceptions = 5;

    public function __construct(public string $batchId)
    {
    }

    public function handle(
        ClickBufferService $buffer,
        ClickMaintenanceService $maintenance,
    ): void {
        $events = $buffer->events($this->batchId);

        if ($events === []) {
            return;
        }

        $processed = $maintenance->run(function () use ($events): void {
            $this->persist($events);
        });

        if (! $processed) {
            $this->release(10);

            return;
        }

        $buffer->acknowledge($this->batchId);
    }

    private function persist(array $events): void
    {
        try {
            DB::transaction(function () use ($events): void {
                DB::table('processed_click_batches')->insert([
                    'id' => $this->batchId,
                    'processed_at' => now('UTC'),
                ]);

                $archiveBoundary = DB::table('click_archive_runs')
                    ->useWritePdo()
                    ->where('status', 'completed')
                    ->max('upper_bound');

                $rows = [];
                $counts = [];
                $archivedCounts = [];

                foreach ($events as $event) {
                    $linkId = $event['link_id'];

                    $counts[$linkId] = ($counts[$linkId] ?? 0) + 1;

                    if (
                        $archiveBoundary !== null
                        && $event['clicked_at'] < $archiveBoundary
                    ) {
                        $archivedCounts[$linkId] =
                            ($archivedCounts[$linkId] ?? 0) + 1;

                        continue;
                    }

                    $rows[] = [
                        'link_id' => $linkId,
                        'clicked_at' => $event['clicked_at'],
                        'visitor_hash' => hex2bin($event['visitor_hash']),
                    ];
                }

                ksort($counts);

                $existingIds = DB::table('links')
                    ->whereIn('id', array_keys($counts))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($existingIds->count() !== count($counts)) {
                    throw new RuntimeException(
                        'Click batch references a missing link.'
                    );
                }

                if ($rows !== []) {
                    DB::table('link_clicks')->insert($rows);
                }

                foreach ($counts as $linkId => $count) {
                    DB::table('links')
                        ->where('id', $linkId)
                        ->incrementEach([
                            'click_count' => $count,
                            'archived_click_count' =>
                                $archivedCounts[$linkId] ?? 0,
                        ]);
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
    }
}
