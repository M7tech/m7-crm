<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\LeadActivity;
use App\Models\User;
use App\Observers\LeadActivityObserver;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class, fn () => new CurrentTenant);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LeadActivity::observe(LeadActivityObserver::class);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::define('viewReports', fn (User $user) => $user->role === UserRole::SuperAdmin
            || ($user->tenant_id !== null && in_array($user->role, [UserRole::CompanyAdmin, UserRole::SalesManager], true)));

        Gate::define('viewOperations', fn (User $user) => $user->role === UserRole::SuperAdmin);

        $this->configureDefaults();
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
