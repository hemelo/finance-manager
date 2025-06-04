<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
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
    public function view(User $user, Transaction $transaction): bool
    {
        // A transação pode pertencer a um cartão do usuário ou a uma conta bancária do usuário
        if ($transaction->card_id && $transaction->card && $transaction->card->user_id === $user->id) {
            return true;
        }
        if ($transaction->bank_account_id && $transaction->bankAccount && $transaction->bankAccount->user_id === $user->id) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Não permitir edição de transações geradas por faturas, assinaturas ou pagamentos de fatura
        if ($transaction->invoice_id || $transaction->subscription_id || $transaction->type === 'invoice_payment') {
            return false;
        }
        return $this->view($user, $transaction); // Reutiliza a lógica de visualização para propriedade
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        // Não permitir exclusão de transações geradas por faturas, assinaturas ou pagamentos de fatura
        if ($transaction->invoice_id || $transaction->subscription_id || $transaction->type === 'invoice_payment') {
            return false;
        }
        return $this->view($user, $transaction); // Reutiliza a lógica de visualização para propriedade
    }
}
