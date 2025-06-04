<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['card_id', 'invoice_id', 'subscription_id', 'amount', 'date', 'description', 'installments', 'type'];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
