<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnsureClickPartitions extends Command
{
    protected $signature = 'analytics:partitions:ensure';

    protected $description = 'Prepare monthly click partitions two months ahead';

    public function handle(): int
    {
        $connection = DB::connection();

        $lock = $connection->selectOne(
            "SELECT GET_LOCK('analytics:partition-maintenance', 0) AS acquired",
            [],
            false,
        );

        if ((int) $lock->acquired !== 1) {
            $this->warn('Another partition maintenance process is running.');

            return self::FAILURE;
        }

        try {
            $partitions = collect($connection->select(
                <<<'SQL'
                SELECT PARTITION_NAME, PARTITION_DESCRIPTION
                FROM information_schema.PARTITIONS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'link_clicks'
                ORDER BY PARTITION_ORDINAL_POSITION
                SQL,
                [],
                false,
            ));

            if (! $partitions->contains('PARTITION_NAME', 'p_future')) {
                throw new RuntimeException('Expected p_future partition is missing.');
            }

            $lastBound = $partitions
                ->filter(fn ($partition) =>
                    $partition->PARTITION_DESCRIPTION !== null
                    && $partition->PARTITION_DESCRIPTION !== 'MAXVALUE'
                )
                ->last();

            if ($lastBound === null) {
                throw new RuntimeException('No finite partition boundary found.');
            }

            $month = CarbonImmutable::parse(
                trim($lastBound->PARTITION_DESCRIPTION, "'"),
                'UTC',
            );

            $target = CarbonImmutable::now('UTC')
                ->startOfMonth()
                ->addMonths(2);

            while ($month->lessThanOrEqualTo($target)) {
                $name = 'p'.$month->format('Ym');
                $upperBound = $month->addMonth()->format('Y-m-d');

                $connection->statement(
                    "ALTER TABLE link_clicks
                     REORGANIZE PARTITION p_future INTO (
                         PARTITION `{$name}` VALUES LESS THAN ('{$upperBound}'),
                         PARTITION p_future VALUES LESS THAN (MAXVALUE)
                     )"
                );

                $this->info("Created {$name}.");

                $month = $month->addMonth();
            }

            $this->info('Future click partitions are ready.');

            return self::SUCCESS;
        } finally {
            $connection->selectOne(
                "SELECT RELEASE_LOCK('analytics:partition-maintenance')",
                [],
                false,
            );
        }
    }
}
