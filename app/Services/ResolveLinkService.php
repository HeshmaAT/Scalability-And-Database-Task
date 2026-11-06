<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveLinkService
{
    public function resolve(string $shortCode): string
    {
        $link = Link::query()
            ->where('short_code', $shortCode)
            ->first();

        if ($link === null || ! $link->is_active) {
            throw new NotFoundHttpException('Link not found.');
        }

        if ($link->expires_at !== null
            && Carbon::parse($link->expires_at, 'UTC')->lte(now('UTC'))) {
            throw new GoneHttpException('Link has expired.');
        }

        return $link->destination_url;
    }
}
