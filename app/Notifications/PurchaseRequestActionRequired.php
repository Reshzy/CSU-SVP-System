<?php

namespace App\Notifications;

use App\Enums\WorkflowStepName;
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
        $step = WorkflowStepName::tryFrom($this->stepName);
        $stepLabel = $step?->label() ?? $this->stepName;

        return (new MailMessage)
            ->subject('Action needed: '.$this->purchaseRequest->pr_number)
            ->greeting('Hello,')
            ->line('A purchase request needs your office to act.')
            ->line('PR Number: '.$this->purchaseRequest->pr_number)
            ->line('Step: '.$stepLabel)
            ->line('Purpose: '.$this->purchaseRequest->purpose)
            ->action('Open dashboard', url(route('dashboard')))
            ->line('Thank you.');
    }
}
