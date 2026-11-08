<?php

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveLinkService
{
    public function __construct(private LinkCacheService $cache, private ClickBufferService $buffer,)
    {
    }

    public function resolve(string $shortCode, string $visitorIp): string
    {
        $link = $this->cache->get($shortCode);

        if (($link['missing'] ?? false) || ! $link['is_active']) {
            throw new NotFoundHttpException('Link not found.');
        }

        if ($link['expires_at'] !== null
            && $link['expires_at'] <= now('UTC')->getTimestamp()) {
            throw new GoneHttpException('Link has expired.');
        }

        $this->buffer->record($link['id'], $visitorIp);

        return $link['destination_url'];
    }
}
