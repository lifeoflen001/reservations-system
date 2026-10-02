<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function expenses(): HasMany { return $this->hasMany(Expense::class, 'category_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
