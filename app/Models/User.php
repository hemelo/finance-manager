<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'primary_currency_code', // Adicionado
    ];

    protected $hidden = [ //
        'password',
        'remember_token',
    ];

    protected function casts(): array //
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
