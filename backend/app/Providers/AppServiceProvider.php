<?php

namespace App\Providers;

use App\Models\IngestionRun;
use App\Models\Plugin;
use App\Policies\AnalyticsPolicy;
use App\Policies\IngestionPolicy;
use App\Policies\PluginPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Plugin::class, PluginPolicy::class);
        Gate::policy(IngestionRun::class, IngestionPolicy::class);
        Gate::define('analytics.view', [AnalyticsPolicy::class, 'view']);
    }
}
