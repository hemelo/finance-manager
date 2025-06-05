<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Card extends Model
{

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'name',
        'brand',
        'currency_code',
        'limit',
        'due_date',
        'closing_day',
        'cashback_rate',
        'is_cashback_per_transaction',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashbacks() // Renomeado de cashbackEntries para consistência
    {
        return $this->hasMany(Cashback::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getRealBalanceAttribute()
    {
        // Esta lógica pode precisar de conversão de moeda se as transações/faturas puderem ter moedas diferentes do cartão
        // Mas a regra é que transações e faturas do cartão são na moeda do cartão.
        $pendingTransactions = $this->transactions()->whereDoesntHave('invoice')->sum('amount'); //
        $openInvoice = $this->invoices()->where('status', 'open')->sum('amount'); //
        return $this->limit - ($pendingTransactions + $openInvoice);
    }

    public function getTotalCashbackAttribute()
    {
        return $this->cashbacks()->sum('amount');
    }

    public function getPaymentDueDayAttribute(): int|string
    {
        if ($this->due_date) {
            // O campo due_date armazena uma data completa (YYYY-MM-DD)
            // Este acessor extrai apenas o dia.
            return Carbon::parse($this->due_date)->day;
        }
        return 'N/A'; // Ou null, ou um valor padrão apropriado
    }

    // Scope para cartões ativos
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
