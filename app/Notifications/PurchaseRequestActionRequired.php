<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseRequestActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $stepName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = str_replace('_', ' ', $this->stepName);

        return (new MailMessage)
            ->subject("Action required: {$this->purchaseRequest->pr_number} ({$label})")
            ->line("Purchase request {$this->purchaseRequest->pr_number} needs your attention for step: {$label}.")
            ->action('Open dashboard', url('/dashboard'));
    }
}
