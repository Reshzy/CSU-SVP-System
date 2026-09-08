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

    public function __construct(public PurchaseRequest $purchaseRequest) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->purchaseRequest->status->label();

        return (new MailMessage)
            ->subject('Purchase Request '.$this->purchaseRequest->pr_number.' status updated')
            ->greeting('Hello,')
            ->line('The status of your purchase request has changed.')
            ->line('PR Number: '.$this->purchaseRequest->pr_number)
            ->line('Current status: '.$statusLabel)
            ->action('View request', url(route('purchase-requests.show', $this->purchaseRequest)))
            ->line('Thank you.');
    }
}
