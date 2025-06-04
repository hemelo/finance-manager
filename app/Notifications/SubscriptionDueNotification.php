<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionDueNotification extends Notification
{
    use Queueable;

    protected $subscription;

    /**
     * Create a new notification instance.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Upcoming Subscription Billing: ' . $this->subscription->name)
            ->line('Your subscription **' . $this->subscription->name . '** is due for billing soon.')
            ->line('**Subscription Details:**')
            ->line('Card: ' . $this->subscription->card->name)
            ->line('Category: ' . $this->subscription->category)
            ->line('Amount: $' . number_format($this->subscription->amount, 2))
            ->line('Next Billing Date: ' . $this->subscription->next_billing_date->format('Y-m-d'))
            ->line('Please ensure your card has sufficient funds.')
            ->action('Manage Subscription', route('subscriptions.edit', $this->subscription))
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'name' => $this->subscription->name,
            'card_name' => $this->subscription->card->name,
            'amount' => $this->subscription->amount,
            'next_billing_date' => $this->subscription->next_billing_date->format('Y-m-d'),
        ];
    }
}
