<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $fillable = ['card_id', 'month_reference', 'amount', 'currency_code', 'cashback_rate', 'due_date', 'status'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashback(): HasOneaa
    {
        return $this->hasOne(Cashback::class);
    }

    // Como o cashback pode ser por transação ou por fatura, vamos calcular o total de cashback de acordo com a configuração do cartão
    public function getTotalCashbackAttribute()
    {
        if ($this->card->is_cashback_per_transaction) {
            $transactionIds = $this->transactions()->pluck('id');
            return Cashback::whereIn('transaction_id', $transactionIds)->sum('amount');
        } else {
            return Cashback::where('invoice_id', $this->id)->sum('amount');
        }
    }
}
