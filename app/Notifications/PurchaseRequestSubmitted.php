<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseRequestSubmitted extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('New Purchase Request Submitted: '.$this->purchaseRequest->pr_number)
            ->greeting('Hello Supply Office,')
            ->line('A new Purchase Request has been submitted and is awaiting review.')
            ->line('PR Number: '.$this->purchaseRequest->pr_number)
            ->line('Purpose: '.$this->purchaseRequest->purpose)
            ->action('Open PRs', url(route('supply.purchase-requests.index')))
            ->line('Thank you.');
    }
}
