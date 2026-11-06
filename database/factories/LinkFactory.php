<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        $length = fake()->randomElement([40, 80, 120, 180, 260, 500, 1000]);

        return [
            'user_id' => User::factory(),
            'destination_url' => 'https://monstersgate.test/' . Str::random($length),
            'short_code' => Str::random(16),
            'is_active' => true,
            'expires_at' => null,
        ];
    }
}
