<?php

namespace App\Providers;

use App\Services\Api\Contracts\BackofficeApiContract;
use App\Services\Api\HttpBackofficeApi;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackofficeApiContract::class, HttpBackofficeApi::class);
    }

    public function boot(): void
    {
        //
    }
}
