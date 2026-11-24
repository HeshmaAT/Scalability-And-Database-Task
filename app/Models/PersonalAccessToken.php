<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    // Credentials must see new tokens and revocations immediately across requests.
    public function newQuery()
    {
        return parent::newQuery()->useWritePdo();
    }
}
