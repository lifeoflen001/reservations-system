<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = ['name', 'code', 'type', 'currency', 'bank_name', 'account_number', 'branch', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean', 'account_number' => 'encrypted']; }

    public function transactions(): HasMany { return $this->hasMany(FinancialTransaction::class, 'account_id'); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class, 'account_id'); }
}
