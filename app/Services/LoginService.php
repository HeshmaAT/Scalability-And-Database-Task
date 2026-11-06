<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function login(string $email, string $password): ?string
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->createToken('api')->plainTextToken;
    }
}
