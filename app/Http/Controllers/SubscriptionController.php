<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()->subscriptions()->with('card')->get();
        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create()
    {
        $cards = auth()->user()->cards;
        return view('subscriptions.create', compact('cards'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'card_id' => 'required|exists:cards,id',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,yearly',
            'start_date' => 'required|date',
        ]);

        $card = Card::where('id', $request->card_id)->where('user_id', auth()->id())->firstOrFail();

        Subscription::create([
            'user_id' => auth()->id(),
            'card_id' => $request->card_id,
            'name' => $request->name,
            'category' => $request->category,
            'amount' => $request->amount,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'next_billing_date' => Carbon::parse($request->start_date),
            'status' => 'active',
        ]);

        return redirect()->route('subscriptions.index')->with('success', 'Subscription created successfully.');
    }

    public function edit(Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $cards = auth()->user()->cards;
        return view('subscriptions.edit', compact('subscription', 'cards'));
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $request->validate([
            'card_id' => 'required|exists:cards,id',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,yearly',
        ]);

        $subscription->update($request->only(['card_id', 'name', 'category', 'amount', 'frequency']));

        return redirect()->route('subscriptions.index')->with('success', 'Subscription updated successfully.');
    }

    public function pause(Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $subscription->update(['status' => 'paused']);
        return redirect()->route('subscriptions.index')->with('success', 'Subscription paused.');
    }

    public function resume(Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $subscription->update([
            'status' => 'active',
            'next_billing_date' => Carbon::today(), // Resume with next billing today or adjust as needed
        ]);
        return redirect()->route('subscriptions.index')->with('success', 'Subscription resumed.');
    }

    public function destroy(Subscription $subscription)
    {
        $this->authorize('delete', $subscription);
        $subscription->update(['status' => 'canceled']);
        return redirect()->route('subscriptions.index')->with('success', 'Subscription canceled.');
    }
}
