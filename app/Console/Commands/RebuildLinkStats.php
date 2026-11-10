<?php

namespace App\Console\Commands;

use App\Services\ClickMaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildLinkStats extends Command
{
    protected $signature = 'stats:rebuild {--link= : Rebuild one link only}';

    protected $description = 'Rebuild lifetime click counters';

    public function handle(ClickMaintenanceService $maintenance): int
    {
        $linkId = $this->option('link');

        if ($linkId !== null && ! preg_match('/\A[1-9][0-9]*\z/', $linkId)) {
            $this->error('The link ID must be a positive integer.');

            return self::FAILURE;
        }

        $rebuilt = 0;

        $executed = $maintenance->run(function () use ($linkId, &$rebuilt): void {
            $query = DB::table('links')
                ->useWritePdo()
                ->select('id');

            if ($linkId !== null) {
                $query->where('id', $linkId);
            }

            $query->chunkById(500, function ($links) use (&$rebuilt): void {
                foreach ($links as $link) {
                    DB::transaction(function () use ($link): void {
                        $locked = DB::table('links')
                            ->where('id', $link->id)
                            ->lockForUpdate()
                            ->first(['id', 'archived_click_count']);

                        if ($locked === null) {
                            return;
                        }

                        $rawClicks = DB::table('link_clicks')
                            ->useWritePdo()
                            ->where('link_id', $link->id)
                            ->count();

                        DB::table('links')
                            ->where('id', $link->id)
                            ->update([
                                'click_count' =>
                                    $locked->archived_click_count + $rawClicks,
                            ]);
                    }, 5);

                    $rebuilt++;
                }
            });
        });

        if (! $executed) {
            $this->warn('Maintenance is busy or an archive needs recovery.');

            return self::FAILURE;
        }

        $this->info("Rebuilt counters for {$rebuilt} links.");

        return self::SUCCESS;
    }
}
