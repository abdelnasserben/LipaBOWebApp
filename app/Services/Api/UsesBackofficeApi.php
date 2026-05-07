<?php

namespace App\Services\Api;

use App\Exceptions\BackofficeApiException;
use App\Services\Api\Contracts\BackofficeApiContract;

trait UsesBackofficeApi
{
    protected function api(): BackofficeApiContract
    {
        return app(BackofficeApiContract::class);
    }

    public function exception($e, $stopPropagation): void
    {
        if (! $e instanceof BackofficeApiException) {
            return;
        }

        $isLivewireRequest = request()->hasHeader('X-Livewire')
            || str_starts_with((string) request()->path(), 'livewire/');

        if (! $isLivewireRequest) {
            return;
        }

        $stopPropagation();

        if ($e->status === 401) {
            session()->forget(['bo_user', 'bo_access_token', 'bo_refresh_token', 'bo_token_expires_at']);
            $this->redirectRoute('login');

            return;
        }

        $message = $e->userMessage();

        if (property_exists($this, 'notification') && property_exists($this, 'notificationType')) {
            $this->notification = $message;
            $this->notificationType = 'danger';
        }

        $this->dispatch(
            'api-error',
            message: $message,
            code: $e->errorCode,
            correlationId: $e->correlationId,
        );
    }
}
