<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
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
        // Restrict OpenAPI documentation to active administrators.
        // This applies to both the Swagger UI and JSON specification.
        Gate::define('viewApiDocs', function (User $user): bool {
            return $user->is_active
                && $user->role === UserRole::ADMIN;
        });
    }
}
