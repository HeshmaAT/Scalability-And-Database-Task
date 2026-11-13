<?php
namespace App\Services\Part9;

use App\Services\ClickBufferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseClickBufferService extends ClickBufferService
{
    public function record(int $linkId, string $ip): void
    {
        DB::table('part9_click_buffer')->insert([
            'payload' => json_encode([
                'link_id' => $linkId,
                'clicked_at' => now('UTC')->format('Y-m-d H:i:s'),
                'visitor_hash' => hash_hmac('sha256', 'click-visitor:'.$ip, config('app.key')),
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function claim(): ?string
    {
        return DB::transaction(function (): ?string {
            $ids = DB::table('part9_click_buffer')->whereNull('batch_id')
                ->orderBy('id')->limit(config('analytics.batch_size'))
                ->lockForUpdate()->pluck('id');
            if ($ids->isEmpty()) { return null; }
            $id = (string) Str::uuid();
            DB::table('part9_click_buffer')->whereIn('id', $ids)->update(['batch_id' => $id]);
            return $id;
        }, 5);
    }

    public function processingIds(): array
    {
        return DB::table('part9_click_buffer')->useWritePdo()->whereNotNull('batch_id')
            ->distinct()->pluck('batch_id')->all();
    }

    public function events(string $batchId): array
    {
        return DB::table('part9_click_buffer')->useWritePdo()->where('batch_id', $batchId)
            ->orderBy('id')->pluck('payload')
            ->map(fn ($payload) => json_decode($payload, true, 512, JSON_THROW_ON_ERROR))->all();
    }

    public function acknowledge(string $batchId): void
    {
        DB::table('part9_click_buffer')->where('batch_id', $batchId)->delete();
    }
}
