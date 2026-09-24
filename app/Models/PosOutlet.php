<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOutlet extends Model
{
    protected $table = 'pos_outlets';
    protected $fillable = ['name', 'code', 'location', 'default_payment_methods', 'receipt_header', 'is_active'];

    protected function casts(): array { return ['default_payment_methods' => 'array', 'is_active' => 'boolean']; }
    public function products(): HasMany { return $this->hasMany(PosProduct::class, 'outlet_id'); }
    public function orders(): HasMany { return $this->hasMany(PosOrder::class, 'outlet_id'); }
    public function shifts(): HasMany { return $this->hasMany(PosShift::class, 'outlet_id'); }
}
