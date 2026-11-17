<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Billing\Markup;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PixelforgeClient::class, fn () => new PixelforgeClient(
            baseUrl: config('pixelforge.base_url'),
            apiKey: config('pixelforge.api_key'),
            timeout: config('pixelforge.timeout'),
        ));

        $this->app->singleton(Markup::class, fn () => new Markup(config('billing.markup_bps')));
    }

    public function boot(): void
    {
        Gate::define('view-admin', fn (User $user) => $user->is_admin);
    }
}
