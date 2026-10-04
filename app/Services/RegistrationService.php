<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class RegistrationService
{
    public function register(array $data, ?string $invitationToken = null): User
    {
        $email = mb_strtolower(trim((string) $data['email']));
        $parts = preg_split('/\s+/', trim((string) $data['name']), 2);

        return DB::transaction(function () use ($data, $email, $parts, $invitationToken): User {
            $baseUsername = Str::slug((string) ($parts[0] ?? 'lodgix-user')) ?: 'lodgix-user';
            $username = $baseUsername;
            $suffix = 1;
            while (User::query()->where('username', $username)->exists()) {
                $username = $baseUsername.'-'.$suffix++;
            }

            return User::create([
                'name' => trim((string) $data['name']),
                'first_name' => $parts[0] ?? trim((string) $data['name']),
                'last_name' => $parts[1] ?? null,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make((string) $data['password']),
                'email_verification_required' => true,
                'registration_terms_accepted_at' => now(),
                'registration_terms_version' => (string) config('hotel.onboarding.terms_version', 'current'),
                'registration_source' => $invitationToken ? 'invitation' : 'self_service',
                'is_active' => true,
                'must_change_password' => false,
            ]);
        });
    }
}
