<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;

class QrDeliveryUrlService
{
    public function forLink(int|string $linkId): string
    {
        $signed = URL::temporarySignedRoute('qr.image', now()->addMinutes(5), ['linkId' => $linkId]);
        $edge = rtrim((string) config('lab4.edge_url', ''), '/');

        if (! config('lab4.enabled') || $edge === '') {
            return $signed;
        }

        // Sign against the canonical APP_URL. The configured edge delegates
        // verification to Laravel with that same canonical URL before cache use.
        return $edge.parse_url($signed, PHP_URL_PATH).'?'.parse_url($signed, PHP_URL_QUERY);
    }
}
