<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use RuntimeException;

class PrepareLoadTest extends Command
{
    protected $signature = 'loadtest:prepare';

    protected $description = 'Prepare a local load-test user and 1000 active links';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('This command is for local testing only.');
        }

        $user = User::query()
            ->useWritePdo()
            ->firstOrCreate(
                ['email' => 'part9-loadtest@example.test'],
                [
                    'name' => 'Part 9 Load Test',
                    'password' => Hash::make(Str::random(40)),
                ],
            );

        $prefix = 'p9_'.Str::lower(Str::random(8)).'_';
        $codes = [];
        $rows = [];
        $now = now('UTC');

        DB::transaction(function () use (
            $user,
            $prefix,
            $now,
            &$codes,
            &$rows,
        ): void {
            for ($i = 0; $i < 1000; $i++) {
                $code = $prefix.$i;
                $codes[] = $code;

                $rows[] = [
                    'user_id' => $user->id,
                    'short_code' => $code,
                    'destination_url' => 'https://example.com/part9/'.$i,
                    'is_active' => true,
                    'expires_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('links')->insert($chunk);
            }
        });

        $token = $user->createToken(
            'part9-loadtest',
            ['*'],
            now()->addHours(12),
        )->plainTextToken;

        $this->line(json_encode([
            'user_id' => $user->id,
            'token' => $token,
            'codes' => $codes,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
