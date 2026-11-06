<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLinkRequest;
use App\Services\CreateLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LinkController extends Controller
{
    public function store(StoreLinkRequest $request, CreateLinkService $service): JsonResponse
    {
        $data = $request->validated();

        $link = $service->create(
            owner: $request->user(),
            destinationUrl: $data['destination_url'],
            customAlias: $data['custom_alias'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
        );

        return response()->json([
            'id' => $link->id,
            'short_code' => $link->short_code,
            'short_url' => url('/' . $link->short_code),
            'destination_url' => $link->destination_url,
            'expires_at' => $link->expires_at === null
                ? null
                : Carbon::parse($link->expires_at, 'UTC')->toISOString(),
        ], 201);
    }
}
