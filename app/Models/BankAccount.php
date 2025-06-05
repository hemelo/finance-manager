<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BankAccount extends Model
{
    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'currency_code',
        'balance',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function transactions()
    {
        // Transações onde esta conta bancária é a origem/destino direto
        return $this->hasMany(Transaction::class, 'bank_account_id');
    }

    // Scope para contas ativas
    public function scopeActive(Builder $query): Builder //
    {
        return $query->where('status', 'active');
    }
}
