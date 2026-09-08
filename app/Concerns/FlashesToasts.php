<?php

namespace App\Concerns;

use Inertia\Inertia;

/**
 * Sends a message to the global Sonner toaster, which listens for the `toast`
 * key on Inertia flash data.
 */
trait FlashesToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
