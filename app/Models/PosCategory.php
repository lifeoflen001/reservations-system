<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCategory extends Model
{
    use AssignsTenantOwnership;
    protected $table = 'pos_categories';
    protected $fillable = ['name', 'code', 'description', 'sort_order', 'is_active'];
    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
    public function products(): HasMany { return $this->hasMany(PosProduct::class, 'category_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
