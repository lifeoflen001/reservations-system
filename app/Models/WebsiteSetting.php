<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'updated_by'];
    protected function casts(): array { return ['updated_by' => 'integer']; }
}
