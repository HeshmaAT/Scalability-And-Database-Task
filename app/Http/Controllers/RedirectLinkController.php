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

        $response = redirect()->away(
            $destination,
            302,
            ['Cache-Control' => 'no-store'],
        );
        if (app()->environment('local','testing') && str_starts_with($shortCode,'lab7rs_') && $request->header('X-Lab7-Trace')==='1') {
            $response->headers->set('X-Lab7-Append-Confirmed', $request->attributes->get('lab7_click_confirmed') ? 'yes' : 'no');
        }
        return $response;
    }
}
