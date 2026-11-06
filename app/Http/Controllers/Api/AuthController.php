<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Services\LoginService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginService $service): JsonResponse
    {
        $data = $request->validated();
        $token = $service->login($data['email'], $data['password']);

        if ($token === null) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }
}
