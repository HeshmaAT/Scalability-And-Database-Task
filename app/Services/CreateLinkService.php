<?php

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CreateLinkService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private ShortCodeGenerator $generator)
    {
    }

    public function create(
        User $owner,
        string $destinationUrl,
        ?string $customAlias = null,
        ?string $expiresAt = null,
    ): Link {
        $expiry = $expiresAt === null ? null : Carbon::parse($expiresAt)->utc();
        $attempts = $customAlias === null ? self::MAX_ATTEMPTS : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $link = new Link;
            $link->user_id = $owner->getKey();
            $link->destination_url = $destinationUrl;
            $link->short_code = $customAlias ?? $this->generator->generate($destinationUrl);
            $link->is_active = true;
            $link->expires_at = $expiry;

            try {
                $link->save();

                return $link;
            } catch (UniqueConstraintViolationException $exception) {
                if ($customAlias !== null) {
                    throw new ConflictHttpException('This alias is already taken.', $exception);
                }
            }
        }

        throw new ServiceUnavailableHttpException(null, 'Could not allocate a short code. Try again.');
    }
}
