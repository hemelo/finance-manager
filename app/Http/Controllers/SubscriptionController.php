<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Adicionado para clareza
use Carbon\Carbon;
use Illuminate\Validation\Rule;

// Adicionado para clareza

class SubscriptionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Subscription::class); // Assumindo que existe ou será adicionado à SubscriptionPolicy
        $subscriptions = Auth::user()->subscriptions()->with('card')->latest()->get(); // Usando Auth::user() explicitamente
        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        $this->authorize('create', Subscription::class); // Assumindo que existe ou será adicionado
        $cards = Auth::user()->cards;
        if ($cards->isEmpty()) {
            return redirect()->route('cards.create')->with('warning', 'Você precisa cadastrar um cartão antes de adicionar uma assinatura.');
        }
        return view('subscriptions.create', compact('cards'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Subscription::class);
        $user = Auth::user();
        $request->validate([
            'card_id' => ['required', Rule::exists('cards', 'id')->where('user_id', $user->id)], // Garante que o cartão é do usuário
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:monthly,yearly',
            'start_date' => 'required|date|after_or_equal:today', // Geralmente data de início não é no passado
        ]);

        // A validação acima já garante que o card pertence ao usuário.
        // $card = Card::where('id', $request->card_id)->where('user_id', $user->id)->firstOrFail();

        Subscription::create([
            'user_id' => $user->id,
            'card_id' => $request->card_id,
            'name' => $request->name,
            'category' => $request->category,
            'amount' => $request->amount,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'next_billing_date' => Carbon::parse($request->start_date), // Primeira cobrança na data de início
            'status' => 'active',
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura criada com sucesso.');
    }

    public function edit(Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $cards = Auth::user()->cards;
        return view('subscriptions.edit', compact('subscription', 'cards'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $user = Auth::user(); //
        $request->validate([
            'card_id' => ['required', Rule::exists('cards', 'id')->where('user_id', $user->id)], // Garante que o cartão é do usuário
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:monthly,yearly',
            // Não permitir alterar start_date ou next_billing_date diretamente aqui,
            // a menos que haja uma lógica de recálculo específica.
        ]);

        $subscription->update($request->only(['card_id', 'name', 'category', 'amount', 'frequency']));

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura atualizada com sucesso.');
    }

    public function pause(Subscription $subscription)
    {
        $this->authorize('update', $subscription); // Ou uma policy 'pause'
        $subscription->update(['status' => 'paused']);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura pausada.');
    }

    public function resume(Subscription $subscription)
    {
        $this->authorize('update', $subscription); // Ou uma policy 'resume'

        // Define a próxima data de cobrança. Pode ser hoje ou a data original se for no futuro.
        $nextBilling = Carbon::parse($subscription->next_billing_date);
        if ($nextBilling->isPast()) {
            $nextBilling = Carbon::today(); // Ou lógica para recalcular com base na frequência e última data de cobrança
            // Se quiser que a cobrança seja no próximo ciclo normal a partir de hoje:
            // $nextBilling = $subscription->frequency === 'monthly' ? Carbon::today()->addMonth() : Carbon::today()->addYear();
            // A lógica do comando GenerateSubscriptionTransactions pode precisar ser ajustada
            // para lidar com datas de cobrança retroativas após resumir.
            // Por simplicidade, definimos para hoje se a data original já passou.
        }

        $subscription->update([
            'status' => 'active',
            'next_billing_date' => $nextBilling,
        ]);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura retomada.');
    }

    public function destroy(Subscription $subscription) // Este método está implementado como cancelamento
    {
        $this->authorize('delete', $subscription); // Ou uma policy 'cancel'
        $subscription->update(['status' => 'canceled']); // Não deleta, apenas marca como cancelada
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura cancelada.');
    }
}
