<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_UNSUBSCRIBED];

    protected $fillable = [
        'email', 'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at',
        'verification_token', 'unsubscribe_token', 'ip_hash', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public static function findByToken(string $column, string $token): ?self
    {
        abort_unless(in_array($column, ['verification_token', 'unsubscribe_token'], true), 400);

        return static::query()->where($column, hash('sha256', $token))->first();
    }
}
