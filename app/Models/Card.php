<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = ['user_id', 'bank_account_id', 'name', 'brand', 'limit', 'due_date', 'cashback_rate', 'is_cashback_per_transaction'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashbacks()
    {
        return $this->hasMany(Cashback::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getRealBalanceAttribute()
    {
        $pendingTransactions = $this->transactions()->whereDoesntHave('invoices')->sum('amount');
        $openInvoice = $this->invoices()->where('status', 'open')->sum('amount');
        return $this->limit - ($pendingTransactions + $openInvoice);
    }

    public function cashbackEntries()
    {
        return $this->hasMany(Cashback::class);
    }

    public function getTotalCashbackAttribute()
    {
        return $this->cashbackEntries()->sum('amount');
    }
}
