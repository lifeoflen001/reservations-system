<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosProduct extends Model
{
    protected $table = 'pos_products';
    protected $fillable = ['category_id', 'outlet_id', 'name', 'sku', 'description', 'selling_price', 'tax_rate', 'tax_inclusive', 'cost_price', 'is_active', 'track_stock', 'stock_quantity', 'reorder_level', 'image_path'];
    protected function casts(): array { return ['selling_price' => 'decimal:2', 'tax_rate' => 'decimal:3', 'tax_inclusive' => 'boolean', 'cost_price' => 'decimal:2', 'is_active' => 'boolean', 'track_stock' => 'boolean', 'stock_quantity' => 'decimal:3', 'reorder_level' => 'decimal:3']; }
    public function category(): BelongsTo { return $this->belongsTo(PosCategory::class, 'category_id'); }
    public function outlet(): BelongsTo { return $this->belongsTo(PosOutlet::class, 'outlet_id'); }
    public function items(): HasMany { return $this->hasMany(PosOrderItem::class, 'product_id'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
