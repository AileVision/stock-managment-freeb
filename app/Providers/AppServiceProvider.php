<?php

namespace App\Providers;

use App\Models\StockMovement;
use App\Models\User;
use App\Observers\StockMovementObserver;
use App\Services\StockAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StockAlertService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSuperAdminAccess();
        $this->configureObservers();
    }

    /**
     * Grant every ability to the superadmin, whatever the permissions defined
     * for its roles. This makes the superadmin bypass all permission checks.
     */
    protected function configureSuperAdminAccess(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole('superadmin') ? true : null);
    }

    protected function configureObservers(): void
    {
        StockMovement::observe(StockMovementObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
