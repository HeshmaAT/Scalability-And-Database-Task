<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ClickMaintenanceService
{
    private const LOCK = 'analytics:partition-maintenance';

    public function run(
        callable $callback,
        bool $allowIncompleteArchive = false,
    ): bool {
        $connection = DB::connection();

        $lock = $connection->selectOne(
            'SELECT GET_LOCK(?, 0) AS acquired',
            [self::LOCK],
            false,
        );

        if ((int) $lock->acquired !== 1) {
            return false;
        }

        try {
            if (! $allowIncompleteArchive) {
                $incomplete = $connection
                    ->table('click_archive_runs')
                    ->useWritePdo()
                    ->where('status', '!=', 'completed')
                    ->exists();

                if ($incomplete) {
                    return false;
                }
            }

            $callback();

            return true;
        } finally {
            $connection->selectOne(
                'SELECT RELEASE_LOCK(?)',
                [self::LOCK],
                false,
            );
        }
    }
}
