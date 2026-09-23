<?php

namespace App\Services;

class EmailAddressPolicy
{
    /**
     * Return whether an address is safe to hand to a real mail provider.
     *
     * RFC-reserved domains are useful in tests and seed data, but they must
     * never be sent to from a production deployment. This check intentionally
     * does not perform DNS lookups: a temporary DNS failure should not make a
     * legitimate customer address unusable.
     */
    public function isDeliverable(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ((bool) config('hotel.email.allow_reserved_recipients', app()->environment('testing'))) {
            return true;
        }

        $domain = mb_strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        $domain = rtrim($domain, '.');

        return ! $this->isReservedDomain($domain);
    }

    public function isReservedDomain(string $domain): bool
    {
        $domain = rtrim(mb_strtolower(trim($domain)), '.');

        if (in_array($domain, ['test', 'invalid', 'localhost'], true)) {
            return true;
        }

        foreach (['example.com', 'example.net', 'example.org'] as $reserved) {
            if ($domain === $reserved || str_ends_with($domain, '.'.$reserved)) {
                return true;
            }
        }

        return str_ends_with($domain, '.test')
            || str_ends_with($domain, '.invalid')
            || str_ends_with($domain, '.localhost');
    }

    public function rejectionMessage(): string
    {
        return 'This email address uses a reserved or placeholder domain and cannot receive real mail.';
    }
}
