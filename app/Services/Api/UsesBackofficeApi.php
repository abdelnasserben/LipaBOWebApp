<?php

namespace App\Services\Api;

use App\Services\Api\Contracts\BackofficeApiContract;

/**
 * Tiny convenience trait for Livewire components: gives them a single
 * `api()` accessor that resolves the bound implementation. UI code
 * MUST go through this contract — never reach for MockDataService or
 * raw Http facades directly.
 */
trait UsesBackofficeApi
{
    protected function api(): BackofficeApiContract
    {
        return app(BackofficeApiContract::class);
    }
}
