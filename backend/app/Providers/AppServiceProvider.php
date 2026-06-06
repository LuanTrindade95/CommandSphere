<?php

namespace App\Providers;

use App\Contracts\GitHubClient;
use App\Models\IngestionRun;
use App\Models\Plugin;
use App\Policies\AnalyticsPolicy;
use App\Policies\IngestionPolicy;
use App\Policies\PluginPolicy;
use App\Services\GitHub\FixtureGitHubClient;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GitHubClient::class, function (): GitHubClient {
            if (
                config('commandsphere.github.client') === 'fixture'
                && app()->environment(['local', 'testing'])
            ) {
                return new FixtureGitHubClient((string) config('commandsphere.github.fixture_path'));
            }

            return new HttpGitHubClient;
        });
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
