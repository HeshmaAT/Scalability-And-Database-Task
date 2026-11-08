<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BenchmarkClickAnalytics extends Command
{
    protected $signature = 'analytics:benchmark';

    protected $description = 'Compare click insertion and aggregate reads';

    public function handle(): int
    {
        if (DB::connection()->getDatabaseName() !== 'url_shortener_test') {
            throw new RuntimeException('Run this command on url_shortener_test only.');
        }

        DB::connection()->disableQueryLog();

        $total = 20_000;
        $batchSize = 500;

        DB::beginTransaction();

        try {
            $link = Link::factory()->create();
            $timestamp = now('UTC')->format('Y-m-d H:i:s');
            $rows = [];

            for ($number = 0; $number < $total; $number++) {
                $rows[] = [
                    'link_id' => $link->id,
                    'clicked_at' => $timestamp,
                    'visitor_hash' => hash('sha256', 'visitor-' . $number, true),
                ];
            }

            $this->info('Measuring single-row inserts...');

            $started = hrtime(true);

            foreach ($rows as $row) {
                DB::table('link_clicks')->insert($row);
            }

            $singleSeconds = (hrtime(true) - $started) / 1_000_000_000;

            DB::table('link_clicks')->where('link_id', $link->id)->delete();

            $this->info('Measuring batch inserts...');

            $started = hrtime(true);

            foreach (array_chunk($rows, $batchSize) as $batch) {
                DB::table('link_clicks')->insert($batch);
            }

            $batchSeconds = (hrtime(true) - $started) / 1_000_000_000;

            DB::table('links')
                ->where('id', $link->id)
                ->update(['click_count' => $total]);

            $rawQuery = fn () => DB::table('link_clicks')
                ->where('link_id', $link->id)
                ->count();

            $counterQuery = fn () => DB::table('links')
                ->where('id', $link->id)
                ->value('click_count');

            // Warm both queries before collecting timings.
            $rawQuery();
            $counterQuery();

            $readTimes = ['raw' => [], 'counter' => []];

            for ($number = 0; $number < 100; $number++) {
                foreach (['raw' => $rawQuery, 'counter' => $counterQuery] as $name => $query) {
                    $started = hrtime(true);
                    $value = (int) $query();
                    $readTimes[$name][] = (hrtime(true) - $started) / 1_000_000;

                    if ($value !== $total) {
                        throw new RuntimeException('Unexpected click total.');
                    }
                }
            }

            sort($readTimes['raw']);
            sort($readTimes['counter']);

            $this->table(
                ['Insert method', 'Rows', 'INSERT statements', 'Seconds', 'Rows/second'],
                [
                    [
                        'Single',
                        $total,
                        $total,
                        round($singleSeconds, 3),
                        round($total / $singleSeconds),
                    ],
                    [
                        'Batch',
                        $total,
                        (int) ceil($total / $batchSize),
                        round($batchSeconds, 3),
                        round($total / $batchSeconds),
                    ],
                ],
            );

            $this->table(
                ['Read method', 'Queries/read', 'Samples', 'Median ms', 'p95 ms'],
                [
                    [
                        'COUNT(*)',
                        1,
                        100,
                        round(($readTimes['raw'][49] + $readTimes['raw'][50]) / 2, 3),
                        round($readTimes['raw'][94], 3),
                    ],
                    [
                        'click_count',
                        1,
                        100,
                        round(($readTimes['counter'][49] + $readTimes['counter'][50]) / 2, 3),
                        round($readTimes['counter'][94], 3),
                    ],
                ],
            );

            $this->line('Both insert methods share one transaction; commit cost is excluded.');
            $this->line('Read timings use warmed queries over 20,000 click rows.');
        } finally {
            DB::rollBack();
        }

        return self::SUCCESS;
    }
}
