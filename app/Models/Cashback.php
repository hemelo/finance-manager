<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashback extends Model
{
    protected $fillable = ['card_id', 'transaction_id', 'invoice_id', 'amount', 'calculation_date'];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
