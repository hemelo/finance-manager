<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Subscription::class);
        $subscriptions = Auth::user()->subscriptions()->with('card.bankAccount')->latest()->get(); // Adicionado bankAccount para exibir moeda
        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        $this->authorize('create', Subscription::class);
        $cards = Auth::user()->cards()->active()->with('bankAccount')->get(); // Apenas cartões ativos
        if ($cards->isEmpty()) {
            return redirect()->route('cards.create')->with('warning', 'Você precisa de um cartão ativo para adicionar uma assinatura.');
        }
        return view('subscriptions.create', compact('cards'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Subscription::class);
        $user = Auth::user();
        $request->validate([
            // Validar se o cartão existe, pertence ao usuário e está ativo
            'card_id' => ['required', Rule::exists('cards', 'id')->where('user_id', $user->id)->where('status', 'active')],
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01', // Este valor será na moeda do cartão
            'frequency' => 'required|in:monthly,yearly',
            'start_date' => 'required|date|after_or_equal:today',
        ]);

        // $card = Card::findOrFail($request->card_id); // A validação já garante isso

        Subscription::create([
            'user_id' => $user->id,
            'card_id' => $request->card_id,
            'name' => $request->name,
            'category' => $request->category,
            'amount' => $request->amount, // Na moeda do cartão
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'next_billing_date' => Carbon::parse($request->start_date),
            'status' => 'active',
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura criada com sucesso.');
    }

    public function edit(Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $cards = Auth::user()->cards()->active()->with('bankAccount')->get();
        return view('subscriptions.edit', compact('subscription', 'cards'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $user = Auth::user();
        $request->validate([
            'card_id' => ['required', Rule::exists('cards', 'id')->where('user_id', $user->id)->where('status', 'active')],
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:monthly,yearly',
            // Se o cartão for alterado, a moeda da assinatura efetivamente muda.
            // O valor do 'amount' deve ser interpretado como na moeda do NOVO cartão.
        ]);

        // $newCard = Card::findOrFail($request->card_id);
        // A validação já garante que o novo cartão é válido e pertence ao usuário

        $subscription->update($request->only(['card_id', 'name', 'category', 'amount', 'frequency']));

        return redirect()->route('subscriptions.index')->with('success', 'Assinatura atualizada com sucesso.');
    }

    // pause, resume, destroy (cancel) como na resposta anterior, usando authorize.
    public function pause(Subscription $subscription) //
    {
        $this->authorize('update', $subscription);
        $subscription->update(['status' => 'paused']);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura pausada.');
    }

    public function resume(Subscription $subscription) //
    {
        $this->authorize('update', $subscription);
        $nextBilling = Carbon::parse($subscription->next_billing_date);
        if ($nextBilling->isPast()) {
            $nextBilling = Carbon::today();
        }
        $subscription->update([
            'status' => 'active',
            'next_billing_date' => $nextBilling,
        ]);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura retomada.');
    }

    public function destroy(Subscription $subscription) //
    {
        $this->authorize('delete', $subscription);
        $subscription->update(['status' => 'canceled']);
        return redirect()->route('subscriptions.index')->with('success', 'Assinatura cancelada.');
    }
}
