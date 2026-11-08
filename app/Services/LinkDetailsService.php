<?php

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LinkDetailsService
{
    public function stats(User $owner, string $id): array
    {
        $link = $this->ownedLink($owner, $id);
        $clicks = DB::table('link_clicks')->where('link_id', $link->id);

        $uniqueVisitors = (clone $clicks)
            ->distinct()
            ->count('visitor_hash');

        $daily = $clicks
            ->selectRaw('DATE(clicked_at) AS date, COUNT(*) AS clicks')
            ->groupByRaw('DATE(clicked_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($day) => [
                'date' => $day->date,
                'clicks' => (int) $day->clicks,
            ]);

        return [
            'link_id' => $link->id,
            'total_clicks' => (int) $link->click_count,
            'unique_visitors' => $uniqueVisitors,
            'clicks_per_day' => $daily,
        ];
    }
    public function requestQr(User $owner, string $id): object
    {
        $link = $this->ownedLink($owner, $id);
        $records = DB::table('link_qr_codes');

        try {
            $records->insert([
                'link_id' => $link->id,
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
        }

        return $this->qrRecord($link);
    }

    public function qr(User $owner, string $id): object
    {
        return $this->qrRecord($this->ownedLink($owner, $id));
    }

    private function ownedLink(User $owner, string $id): Link
    {
        return Link::query()
            ->where('user_id', $owner->getKey())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function qrRecord(Link $link): object
    {
        return DB::table('link_qr_codes')->where('link_id', $link->id)->first()
            ?? throw new NotFoundHttpException('QR has not been requested.');
    }
}
