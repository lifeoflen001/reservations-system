<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;

class ApiTokenService
{
    public function issue(User $user, string $name, array $abilities): array
    {
        $plain = bin2hex(random_bytes(32));
        $token = ApiToken::create(['user_id' => $user->id, 'name' => $name, 'token_prefix' => substr($plain, 0, 12), 'token_hash' => hash('sha256', $plain), 'abilities' => array_values(array_unique($abilities))]);

        return [$token, $plain];
    }
}
