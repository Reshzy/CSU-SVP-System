<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseRequestStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $previousStatus,
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
        $status = $this->purchaseRequest->statusLabel();

        return (new MailMessage)
            ->subject("Purchase request {$this->purchaseRequest->pr_number} updated")
            ->line("Your purchase request {$this->purchaseRequest->pr_number} is now: {$status}.")
            ->action('View purchase request', url('/purchase-requests/'.$this->purchaseRequest->id));
    }
}
