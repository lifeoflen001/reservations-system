<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomStatus extends Model
{
    protected $fillable = ['code', 'name', 'color', 'is_sellable', 'sort_order', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_sellable' => 'boolean',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
        ];
    }
}
