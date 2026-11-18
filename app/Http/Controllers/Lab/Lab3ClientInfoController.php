<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Lab3ClientInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            app()->environment('local', 'testing') && config('lab3.enabled'),
            404,
        );

        return response()->json([
            'client_ip' => $request->ip(),
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'is_secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'forwarded_for' => $request->header('X-Forwarded-For'),
            'forwarded_proto' => $request->header('X-Forwarded-Proto'),
            'visitor_hash' => hash_hmac(
                'sha256',
                'click-visitor:'.$request->ip(),
                (string) config('app.key'),
            ),
        ])->header('Cache-Control', 'no-store');
    }
}
