<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ClickBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection()->disableQueryLog();

        $total = 10_000_000;
        $batchSize = 50_000;

        DB::statement('
            CREATE TEMPORARY TABLE part7_seed_links (
                slot INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                link_id BIGINT UNSIGNED NOT NULL UNIQUE
            ) ENGINE=InnoDB
        ');

        try {
            DB::statement('
                INSERT INTO part7_seed_links (link_id)
                SELECT id FROM links
                WHERE deleted_at IS NULL
                ORDER BY id
                LIMIT 100000
            ');

            $available = DB::selectFromWriteConnection(
                'SELECT COUNT(*) AS total FROM part7_seed_links'
            )[0]->total;

            if ((int) $available !== 100_000) {
                throw new RuntimeException('The benchmark requires 100,000 existing links.');
            }

            $started = hrtime(true);
            $inserted = 0;

            for ($offset = 0; $offset < $total; $offset += $batchSize) {
                $batchId = Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    'url-shortener:part7-clicks-v1:' . $offset,
                )->toString();

                $processed = DB::table('processed_click_batches')
                    ->useWritePdo()
                    ->where('id', $batchId)
                    ->exists();

                if ($processed) {
                    continue;
                }

                $source = "
                    (SELECT seq + {$offset} AS n FROM seq_1_to_50000) AS s
                    JOIN part7_seed_links AS m ON m.slot =
                        CASE
                            WHEN MOD(s.n, 10) < 7
                                THEN 1 + MOD(FLOOR(s.n / 10), 1000)
                            ELSE 1001 + MOD(
                                FLOOR(s.n / 10) * 3 + MOD(s.n, 10),
                                99000
                            )
                        END
                ";

                DB::transaction(function () use ($source, $batchId): void {
                    DB::table('processed_click_batches')->insert([
                        'id' => $batchId,
                        'processed_at' => now('UTC'),
                    ]);

                    DB::statement("
                        INSERT INTO link_clicks (link_id, clicked_at, visitor_hash)
                        SELECT
                            m.link_id,
                            DATE_SUB(
                                '2026-11-09 00:00:00',
                                INTERVAL MOD(s.n * 7919, 7776000) SECOND
                            ),
                            UNHEX(SHA2(
                                CONCAT('part7-visitor-', MOD(s.n, 200000)),
                                256
                            ))
                        FROM {$source}
                    ");

                    DB::statement("
                        UPDATE links AS l
                        JOIN (
                            SELECT m.link_id, COUNT(*) AS amount
                            FROM {$source}
                            GROUP BY m.link_id
                        ) AS counts ON counts.link_id = l.id
                        SET l.click_count = l.click_count + counts.amount
                    ");
                }, 5);

                $inserted += $batchSize;

                $this->command->info(
                    'Completed: ' . ($offset + $batchSize) . '/' . $total
                );
            }

            $seconds = (hrtime(true) - $started) / 1_000_000_000;

            $this->command->info(
                "Inserted this run: {$inserted}; elapsed: "
                . round($seconds, 2) . ' seconds.'
            );
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS part7_seed_links');
        }
    }
}
