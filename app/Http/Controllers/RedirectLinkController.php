<?php

namespace App\Http\Controllers;

use App\Services\ResolveLinkService;
use Illuminate\Http\RedirectResponse;

class RedirectLinkController extends Controller
{
    public function __invoke(string $shortCode, ResolveLinkService $service): RedirectResponse
    {
        return redirect()->away(
            $service->resolve($shortCode),
            302,
            ['Cache-Control' => 'no-store'],
        );
    }
}
