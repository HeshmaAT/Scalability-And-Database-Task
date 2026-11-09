<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExportLinks extends Command
{
    protected $signature = 'links:export';

    protected $description = 'Export links as CSV using chunkById';

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $stream = fopen('php://stdout', 'wb');

        if ($stream === false) {
            throw new RuntimeException('Could not open the output stream.');
        }

        $started = hrtime(true);
        $count = 0;

        try {
            $this->writeRow($stream, [
                'id', 'user_id', 'short_code', 'destination_url', 'created_at',
            ]);

            DB::table('links')
                ->select(['id', 'user_id', 'short_code', 'destination_url', 'created_at'])
                ->whereNull('deleted_at')
                ->chunkById(1000, function ($links) use ($stream, &$count): void {
                    foreach ($links as $link) {
                        $this->writeRow($stream, [
                            $link->id,
                            $link->user_id,
                            $link->short_code,
                            $link->destination_url,
                            $link->created_at,
                        ]);

                        $count++;
                    }
                });
        } finally {
            fclose($stream);
        }

        $seconds = round((hrtime(true) - $started) / 1_000_000_000, 2);
        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        fwrite(STDERR, "Exported {$count} links in {$seconds}s; PHP peak memory: {$peakMb} MB.\n");

        return self::SUCCESS;
    }

    private function writeRow($stream, array $values): void
    {
        if (fputcsv($stream, $values, ',', '"', '') === false) {
            throw new RuntimeException('Could not write the CSV row.');
        }
    }
}
