<?php

namespace App\Jobs;

use App\Models\Link;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateQrCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $linkId)
    {
    }

    public function handle(): void
    {
        try {
            Cache::store('link_locks')
                ->lock('qr-generation:' . $this->linkId, 120)
                ->block(3, function (): void {
                    $metadata = DB::table('link_qr_codes')
                        ->useWritePdo()
                        ->where('link_id', $this->linkId)
                        ->first();

                    if ($metadata === null || $metadata->status === 'ready') {
                        return;
                    }

                    $link = Link::query()
                        ->useWritePdo()
                        ->find($this->linkId);

                    if ($link === null) {
                        return;
                    }

                    $shortUrl = rtrim(config('app.url'), '/')
                        . '/' . $link->short_code;

                    $qrCode = QrCode::create($shortUrl)
                        ->setSize(300)
                        ->setMargin(10);

                    $image = (new SvgWriter())->write($qrCode);
                    $objectKey = 'qr/' . $link->id . '.svg';

                    Storage::disk('s3')->put(
                        $objectKey,
                        $image->getString(),
                        [
                            'ContentType' => 'image/svg+xml',
                            'CacheControl' => 'private, max-age=300',
                        ],
                    );

                    DB::table('link_qr_codes')
                        ->where('link_id', $link->id)
                        ->update([
                            'object_key' => $objectKey,
                            'status' => 'ready',
                            'generated_at' => now('UTC'),
                        ]);
                });
        } catch (LockTimeoutException) {
            $this->release(5);
        }
    }
}
