<?php

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Policies\ActivityPolicy;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\LiveOddsApi;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use App\Sportsbook\Pricing\ConsensusPricer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CreditBudget::class, fn (): CreditBudget => new CreditBudget(
            monthlyCap: (int) config('sportsbook.odds_api.monthly_credit_cap'),
            reserve: (int) config('sportsbook.odds_api.credit_reserve'),
        ));

        $this->app->bind(OddsApi::class, fn (Application $app): OddsApi => config('sportsbook.odds_api.driver') === 'live'
            ? LiveOddsApi::fromConfig($app->make(CreditBudget::class))
            : new ReplayOddsApi((string) config('sportsbook.odds_api.recordings_path')));

        $this->app->bind(ConsensusPricer::class, fn (): ConsensusPricer => new ConsensusPricer(
            margin: (float) config('sportsbook.odds.margin'),
            minimumBookmakers: (int) config('sportsbook.odds.minimum_bookmakers'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAdminAuthorization();
    }

    /**
     * Super admins hold every admin permission. Abilities that are not permissions (such as deleting an audit
     * record) stay with their policies, so no role can bypass them.
     */
    protected function configureAdminAuthorization(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        Gate::before(fn (mixed $user, string $ability): ?bool => $user instanceof Admin
            && AdminPermission::tryFrom($ability) !== null
            && $user->hasRole(AdminRole::SuperAdmin->value)
            ? true
            : null);
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
