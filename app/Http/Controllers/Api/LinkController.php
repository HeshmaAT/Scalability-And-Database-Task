<?php

namespace App\Console\Http\Controllers\Api;

use App\Console\Http\Controllers\Controller;
use App\Console\Http\Requests\Api\StoreLinkRequest;
use App\Console\Http\Requests\Api\UpdateLinkRequest;
use App\Services\CreateLinkService;
use App\Services\LinkDetailsService;
use App\Services\UpdateLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

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



    public function update(UpdateLinkRequest $request, string $id, UpdateLinkService $service,): JsonResponse {
        $link = $service->update($request->user(), $id, $request->validated());

        return response()->json([
            'id' => $link->id,
            'short_code' => $link->short_code,
            'is_active' => (bool) $link->is_active,
            'expires_at' => $link->expires_at === null
                ? null
                : Carbon::parse($link->expires_at, 'UTC')->toISOString(),
        ]);
    }


    public function stats(Request $request, string $id, LinkDetailsService $service): JsonResponse
    {
        return response()->json($service->stats($request->user(), $id));
    }

    public function requestQr(Request $request, string $id, LinkDetailsService $service): JsonResponse
    {
        return $this->qrResponse($service->requestQr($request->user(), $id));
    }

    public function showQr(Request $request, string $id, LinkDetailsService $service): JsonResponse
    {
        return $this->qrResponse($service->qr($request->user(), $id));
    }

    private function qrResponse(object $qr): JsonResponse
    {
        return response()->json([
            'link_id' => $qr->link_id,
            'status' => $qr->status,
            'generated_at' => $qr->generated_at,
            'url' => $qr->status === 'ready'
                ? URL::temporarySignedRoute('qr.image', now()->addMinutes(5), ['linkId' => $qr->link_id],)
                : null,
        ], $qr->status === 'pending' ? 202 : 200);
    }
}
