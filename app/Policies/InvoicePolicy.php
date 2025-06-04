<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->card->user_id;
    }

    /**
     * Determine whether the user can create models.
     * Faturas são geralmente geradas pelo sistema.
     */
    public function create(User $user): bool
    {
        return false; // Ou true se houver um caso de uso para criação manual
    }

    /**
     * Determine whether the user can update the model.
     * Usado para marcar como paga, por exemplo.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->card->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Geralmente não se deleta faturas.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return false; // Ou uma lógica mais complexa se permitido
    }

    /**
     * Determine whether the user can pay the invoice.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->card->user_id && $invoice->status !== 'paid';
    }
}
