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
        Gate::before(function ($user, string $ability) {
            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        Gate::define('manage-users', function ($user) {
            return $user && (in_array($user->role, ['admin', 'hr'], true) || $user->hasPermission('users.administer'));
        });

        Gate::define('manage-access-profiles', function ($user) {
            return $user && (in_array($user->role, ['admin', 'hr'], true) || collect(['access_profiles.view', 'access_profiles.create', 'access_profiles.edit', 'access_profiles.administer'])->contains(fn (string $permission) => $user->hasPermission($permission)));
        });

        Gate::define('manage-structure', function ($user) {
            return $user && (in_array($user->role, ['admin', 'hr'], true) || collect(['teams.view', 'teams.create', 'job_positions.view', 'job_positions.create', 'job_positions.edit', 'job_positions.deactivate'])->contains(fn (string $permission) => $user->hasPermission($permission)));
        });

        Gate::define('manage-team', function ($user) {
            return $user && $user->isManager();
        });

        Gate::define('view-costs', function ($user) {
            return $user && $user->canViewCosts();
        });
    }
}
