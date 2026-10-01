<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsitePricingFeature extends Model
{
    protected $fillable = ['plan_id', 'feature', 'included', 'position'];
    protected function casts(): array { return ['included' => 'boolean']; }
}
