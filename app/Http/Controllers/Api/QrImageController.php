<?php

namespace App\Console\Http\Controllers\Api;

use App\Console\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Storage;

class QrImageController extends Controller
{
    public function __invoke(string $linkId): Response
    {
        Link::query()->useWritePdo()->findOrFail($linkId);

        $metadata = DB::table('link_qr_codes')
            ->useWritePdo()
            ->where('link_id', $linkId)
            ->where('status', 'ready')
            ->first();

        abort_if($metadata === null || $metadata->object_key === null, 404);

        return response(
            Storage::disk('s3')->get($metadata->object_key),
            200,
            [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
