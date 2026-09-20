<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'channels', 'is_enabled'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'is_enabled' => 'boolean'];
    }
}
