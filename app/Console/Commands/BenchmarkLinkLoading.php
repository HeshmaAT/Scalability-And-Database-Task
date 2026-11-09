<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BenchmarkLinkLoading extends Command
{
    protected $signature = 'links:benchmark-loading';

    protected $description = 'Compare lazy and eager loading of link owners';

    public function handle(): int
    {
        $results = [];
        $outputs = [];

        try {
            foreach (['Lazy loading', 'Eager loading'] as $method) {
                DB::flushQueryLog();
                DB::enableQueryLog();

                $started = hrtime(true);

                $query = Link::query()
                    ->select(['id', 'user_id', 'short_code', 'created_at'])
                    ->orderBy('id')
                    ->limit(100);

                if ($method === 'Eager loading') {
                    $query->with('owner:id,name');
                }

                $outputs[$method] = $query->get()
                    ->map(fn (Link $link) => [
                        'id' => $link->id,
                        'short_code' => $link->short_code,
                        'owner_name' => $link->owner->name,
                    ])
                    ->all();

                $milliseconds = (hrtime(true) - $started) / 1_000_000;
                $queryCount = count(DB::getQueryLog());

                DB::disableQueryLog();
                DB::flushQueryLog();

                $results[] = [
                    $method,
                    count($outputs[$method]),
                    $queryCount,
                    round($milliseconds, 3),
                ];
            }

            if ($outputs['Lazy loading'] !== $outputs['Eager loading']) {
                throw new RuntimeException('The two methods returned different results.');
            }

            $this->table(
                ['Method', 'Links', 'Queries', 'Elapsed ms'],
                $results,
            );

            $this->info('Both methods returned the same data.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return self::SUCCESS;
    }
}
