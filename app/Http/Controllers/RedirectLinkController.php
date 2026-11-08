<?php

namespace App\Http\Controllers;

use App\Services\ResolveLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectLinkController extends Controller
{
    public function __invoke(string $shortCode, Request $request, ResolveLinkService $service,): RedirectResponse
    {
        $destination = $service->resolve(
            $shortCode,
            $request->ip() ?? '',
        );

        return redirect()->away(
            $destination,
            302,
            ['Cache-Control' => 'no-store'],
        );
    }
}
