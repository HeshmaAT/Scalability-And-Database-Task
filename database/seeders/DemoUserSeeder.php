<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'Heshmat@monstersgate.test'],
            [
                'name' => 'HeshmaT monstersgate',
                'password' => Hash::make('monstersgateNUM1'),
            ],
        );
    }
}
