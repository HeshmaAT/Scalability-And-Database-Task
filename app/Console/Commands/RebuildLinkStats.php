<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildLinkStats extends Command
{
    protected $signature = 'stats:rebuild {--link= : Rebuild one link only}';

    protected $description = 'Rebuild click counters from stored click events';

    public function handle(): int
    {
        $linkId = $this->option('link');

        if ($linkId !== null && ! preg_match('/\A[1-9][0-9]*\z/', $linkId)) {
            $this->error('The link ID must be a positive integer.');

            return self::FAILURE;
        }

        $query = DB::table('links')->select('id');

        if ($linkId !== null) {
            $query->where('id', $linkId);
        }

        $rebuilt = 0;

        $query->chunkById(500, function ($links) use (&$rebuilt): void {
            foreach ($links as $link) {
                DB::transaction(function () use ($link): void {
                    $locked = DB::table('links')
                        ->where('id', $link->id)
                        ->lockForUpdate()
                        ->first(['id']);

                    if ($locked === null) {
                        return;
                    }

                    $count = DB::table('link_clicks')
                        ->useWritePdo()
                        ->where('link_id', $link->id)
                        ->count();

                    DB::table('links')
                        ->where('id', $link->id)
                        ->update(['click_count' => $count]);
                }, 5);

                $rebuilt++;
            }
        });

        $this->info("Rebuilt counters for {$rebuilt} links.");

        return self::SUCCESS;
    }
}
