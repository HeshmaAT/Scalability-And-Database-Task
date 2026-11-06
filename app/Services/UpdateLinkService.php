<?php

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Carbon;

class UpdateLinkService
{
    public function update(User $owner, string $id, array $changes): Link
    {
        $link = Link::query()
            ->where('user_id', $owner->getKey())
            ->whereKey($id)
            ->firstOrFail();

        if (array_key_exists('is_active', $changes)) {
            $link->is_active = (bool) $changes['is_active'];
        }

        if (array_key_exists('expires_at', $changes)) {
            $link->expires_at = $changes['expires_at'] === null
                ? null
                : Carbon::parse($changes['expires_at'])->utc();
        }

        $link->save();

        return $link;
    }
}
