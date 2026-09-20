<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'channels', 'categories'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'categories' => 'array'];
    }
}
