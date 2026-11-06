<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LinkBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('links')->exists()) {
            throw new RuntimeException('Run this benchmark on an empty links table.');
        }

        $owner = User::factory()->create();
        $factory = Link::factory()->state(['user_id' => $owner->id]);
        $prefix = 'bench_' . Str::lower(Str::random(8)) . '_';
        $timestamp = now();

        for ($start = 1; $start <= 1000000; $start += 1000) {
            $rows = [];

            for ($number = $start; $number < $start + 1000; $number++) {
                $rows[] = array_replace($factory->raw(), [
                    'short_code' => $prefix . $number,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            DB::table('links')->insert($rows);
        }
    }
}
