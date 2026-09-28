<?php

namespace App\Providers;

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
        Gate::define('manage-users', function ($user) {
            return $user && in_array($user->role, ['admin', 'hr'], true);
        });

        Gate::define('manage-structure', function ($user) {
            return $user && in_array($user->role, ['admin', 'hr'], true);
        });

        Gate::define('manage-team', function ($user) {
            return $user && $user->isManager();
        });

        Gate::define('view-costs', function ($user) {
            return $user && $user->canViewCosts();
        });
    }
}
