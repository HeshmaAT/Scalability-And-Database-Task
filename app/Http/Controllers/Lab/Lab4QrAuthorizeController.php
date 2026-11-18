<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class Lab4QrAuthorizeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(config('lab4.enabled') && app()->environment('local', 'testing'), 403);

        $original = $request->header('X-Lab4-Original-URI', '');
        abort_unless(is_string($original) && strlen($original) < 8192
            && preg_match('~\A/qr/([1-9][0-9]*)\?[^\r\n]*\z~', $original, $matches), 403);

        $signedRequest = Request::create(rtrim(config('app.url'), '/').$original, 'GET');
        abort_unless(URL::hasValidSignature($signedRequest), 403);

        $link = Link::query()->useWritePdo()->find($matches[1]);
        abort_if($link === null, 403);

        $metadata = DB::table('link_qr_codes')->useWritePdo()
            ->where('link_id', $link->id)->where('status', 'ready')->first();
        abort_if($metadata === null || ! $metadata->object_key, 403);

        // Authorization reads metadata only; no S3 image download on cache HIT.
        $version = hash('sha256', $metadata->object_key.'|'.$metadata->generated_at);

        return response('', 204, [
            'X-Lab4-Cache-Version' => $version,
            'Cache-Control' => 'no-store',
        ]);
    }
}
