<?php

namespace App\Providers;

use App\Services\Api\Contracts\BackofficeApiContract;
use App\Services\Api\HttpBackofficeApi;
use App\Services\Api\MockBackofficeApi;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the BO API contract to either the in-memory mock or the
        // real HTTP client. Toggle via KOMOPAY_USE_MOCK_API in .env.
        $this->app->singleton(BackofficeApiContract::class, function () {
            return config('komopay.use_mock_api')
                ? new MockBackofficeApi()
                : new HttpBackofficeApi();
        });
    }

    public function boot(): void
    {
        //
    }
}
