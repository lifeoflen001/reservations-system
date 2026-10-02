<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['uuid', 'code', 'name', 'status'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
