<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Card; // Adicionado
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSubscriptionTransactions extends Command
{
    protected $signature = 'app:subscriptions:generate-transactions';
    protected $description = 'Gera transações para assinaturas ativas e com cartão ativo.';

    public function handle(): void
    {
        $today = Carbon::today();
        $subscriptions = Subscription::where('status', 'active')
            ->where('next_billing_date', '<=', $today)
            ->whereHas('card', function ($query) { // Apenas de cartões ativos
                $query->where('status', 'active');
            })
            ->with('card') // Eager load card
            ->get();

        foreach ($subscriptions as $subscription) {
            $card = $subscription->card; // Cartão já carregado

            // Cria transação na moeda do cartão
            Transaction::create([
                'card_id' => $subscription->card_id,
                'subscription_id' => $subscription->id,
                'amount' => $subscription->amount, // Este valor já está na moeda do cartão
                'currency_code' => $card->currency_code, // Moeda da transação = moeda do cartão
                'date' => $today,
                'description' => $subscription->name . ' (Assinatura)',
                'type' => 'subscription_purchase', // Novo tipo para clareza
                'installments' => 1,
            ]);

            $nextBillingDate = $this->calculateNextBillingDate($subscription);
            $subscription->update(['next_billing_date' => $nextBillingDate]);

            $this->info("Transação criada para assinatura: {$subscription->name} (Cartão: {$card->name})");
        }
        $this->info('Transações de assinatura geradas com sucesso.');
    }

    protected function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        // Avança a data de cobrança para o próximo ciclo a partir da data de cobrança atual que acabou de ser processada.
        $currentProcessedBillingDate = Carbon::parse($subscription->next_billing_date);

        // Garante que a próxima data de cobrança seja sempre no futuro, mesmo que o comando rode atrasado.
        // Se a data processada é hoje ou no passado, calcula a partir dela.
        $baseDateForNext = $currentProcessedBillingDate->isFuture() ? $currentProcessedBillingDate : Carbon::today();

        if ($subscription->frequency === 'monthly') {
            // Se a data de cobrança original era, por exemplo, dia 28, e o próximo mês não tem dia 28,
            // o Carbon ajusta para o último dia do mês.
            // Para manter o mesmo dia do mês, se possível:

            $newDate = $baseDateForNext->copy()->addMonthNoOverflow();
            // Se a data atual de cobrança é, por exemplo, dia 31 e o próximo mês não tem 31, ajusta.
            // Se a data de cobrança já passou várias vezes, calcula a próxima data futura.
            while ($newDate->isPast() || $newDate->isToday()) {
                $newDate->addMonthNoOverflow();
            }
            return $newDate;

        } elseif ($subscription->frequency === 'yearly') {
            $newDate = $baseDateForNext->copy()->addYearNoOverflow();
            while ($newDate->isPast() || $newDate->isToday()) {
                $newDate->addYearNoOverflow();
            }
            return $newDate;
        }

        // Fallback (nunca deve acontecer com validação de frequência)
        $newDateFallback = $baseDateForNext->copy()->addMonthNoOverflow();
        while ($newDateFallback->isPast() || $newDateFallback->isToday()) {
            $newDateFallback->addMonthNoOverflow();
        }
        return $newDateFallback;
    }
}
