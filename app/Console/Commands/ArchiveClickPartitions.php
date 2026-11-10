<?php

namespace App\Console\Commands;

use App\Services\ClickMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArchiveClickPartitions extends Command
{
    protected $signature = 'analytics:partitions:archive
                            {--execute : Archive counts and drop expired partitions}';

    protected $description = 'Keep the current month and five previous months of raw clicks';

    public function handle(ClickMaintenanceService $maintenance): int
    {
        $executed = $maintenance->run(
            function (): void {
                $cutoff = CarbonImmutable::now('UTC')
                    ->startOfMonth()
                    ->subMonths(5);

                $this->info(
                    'Keep raw clicks from '.$cutoff->format('Y-m-d').' onward.'
                );

                $pendingRuns = DB::table('click_archive_runs')
                    ->useWritePdo()
                    ->where('status', '!=', 'completed')
                    ->orderBy('upper_bound')
                    ->get();

                foreach ($pendingRuns as $run) {
                    $this->line("Pending recovery: {$run->partition_name}");

                    if ($this->option('execute')) {
                        $this->finishArchive($run);
                    }
                }

                foreach ($this->partitions() as $partition) {
                    $name = $partition->PARTITION_NAME;
                    $description = $partition->PARTITION_DESCRIPTION;

                    if ($description === 'MAXVALUE') {
                        continue;
                    }

                    $upperBound = CarbonImmutable::parse(
                        trim($description, "'"),
                        'UTC',
                    );

                    if ($upperBound->greaterThan($cutoff)) {
                        continue;
                    }

                    $this->validateName($name);

                    $this->line(
                        "{$name}: archive raw clicks before ".
                        $upperBound->format('Y-m-d')
                    );

                    if (! $this->option('execute')) {
                        continue;
                    }

                    $run = $this->snapshot(
                        $name,
                        $upperBound->format('Y-m-d H:i:s'),
                    );

                    $this->finishArchive($run);
                }

                $this->info(
                    $this->option('execute')
                        ? 'Archive maintenance completed.'
                        : 'Preview only. No data changed.'
                );
            },
            allowIncompleteArchive: true,
        );

        if (! $executed) {
            $this->warn('Another maintenance process is running.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function partitions(): array
    {
        $partitions = DB::select(
            <<<'SQL'
            SELECT PARTITION_NAME, PARTITION_DESCRIPTION
            FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'link_clicks'
            ORDER BY PARTITION_ORDINAL_POSITION
            SQL,
            [],
            false,
        );

        if (
            $partitions === []
            || $partitions[0]->PARTITION_NAME === null
        ) {
            throw new RuntimeException('link_clicks must be partitioned.');
        }

        return $partitions;
    }

    private function snapshot(string $name, string $upperBound): object
    {
        $this->validateName($name);

        return DB::transaction(function () use ($name, $upperBound): object {
            $runId = DB::table('click_archive_runs')->insertGetId([
                'partition_name' => $name,
                'upper_bound' => $upperBound,
                'status' => 'preparing',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            DB::insert(
                "INSERT INTO click_archive_counts
                    (archive_run_id, link_id, clicks)
                 SELECT ?, link_id, COUNT(*)
                 FROM link_clicks PARTITION (`{$name}`)
                 GROUP BY link_id",
                [$runId],
            );

            DB::table('click_archive_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'snapshot_ready',
                    'updated_at' => now('UTC'),
                ]);

            return DB::table('click_archive_runs')
                ->useWritePdo()
                ->where('id', $runId)
                ->first();
        });
    }

    private function finishArchive(object $run): void
    {
        $name = $run->partition_name;

        $this->validateName($name);

        if ($run->status !== 'snapshot_ready') {
            throw new RuntimeException(
                "Unexpected archive status for {$name}: {$run->status}"
            );
        }

        $partition = collect($this->partitions())
            ->firstWhere('PARTITION_NAME', $name);

        if ($partition !== null) {
            $actualBound = CarbonImmutable::parse(
                trim($partition->PARTITION_DESCRIPTION, "'"),
                'UTC',
            );

            if (! $actualBound->equalTo(
                CarbonImmutable::parse($run->upper_bound, 'UTC')
            )) {
                throw new RuntimeException(
                    "Partition boundary changed for {$name}."
                );
            }

            DB::statement(
                "ALTER TABLE link_clicks DROP PARTITION `{$name}`"
            );
        }

        DB::transaction(function () use ($run, $name): void {
            $locked = DB::table('click_archive_runs')
                ->where('id', $run->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status === 'completed') {
                return;
            }

            DB::update(
                <<<'SQL'
                UPDATE links AS l
                JOIN click_archive_counts AS c ON c.link_id = l.id
                SET l.archived_click_count = l.archived_click_count + c.clicks
                WHERE c.archive_run_id = ?
                SQL,
                [$run->id],
            );

            DB::table('click_archive_runs')
                ->where('id', $run->id)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
        });

        $this->info("Archived {$name}.");
    }

    private function validateName(string $name): void
    {
        if (! preg_match('/\A(?:p_history|p[0-9]{6})\z/', $name)) {
            throw new RuntimeException('Unexpected partition name.');
        }
    }
}
