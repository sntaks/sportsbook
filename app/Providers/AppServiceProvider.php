<?php

namespace App\Providers;

use App\Accounts\Sms\LogSmsSender;
use App\Accounts\Sms\SmsSender;
use App\Accounts\Sms\TextSmsSender;
use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Sport;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\PlayerPolicy;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\LiveOddsApi;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Pricing\Derived\DerivedMarketPricer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        $this->app->bind(SmsSender::class, fn (Application $app): SmsSender => config('services.sms.driver') === 'log'
            ? $app->make(LogSmsSender::class)
            : $app->make(TextSmsSender::class));

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

        $this->app->bind(DerivedMarketPricer::class, fn (): DerivedMarketPricer => new DerivedMarketPricer(
            margin: (float) config('sportsbook.odds.margin'),
            modelMargin: (float) config('sportsbook.odds.derived_market_margin'),
            firstHalfShare: (float) config('sportsbook.odds.first_half_goal_share'),
            maximumModelPrice: (float) config('sportsbook.odds.derived_maximum_price'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAdminAuthorization();
        $this->configureSportsbookViews();
    }

    /**
     * The sports sidebar is on every page, so its competition list is cached briefly. It is cached as plain arrays:
     * the cache will not unserialize Eloquent models.
     */
    protected function configureSportsbookViews(): void
    {
        View::composer('components.sportsbook.sports-sidebar', function (\Illuminate\View\View $view): void {
            $view->with('sports', Cache::remember('sportsbook.sidebar', now()->addMinutes(5), fn (): array => Sport::query()
                ->whereHas('competitions', fn ($competitions) => $competitions->synced())
                ->with(['competitions' => fn ($competitions) => $competitions->synced()->withCount('upcomingFixtures')->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Sport $sport): array => [
                    'name' => $sport->name,
                    'competitions' => $sport->competitions->map(fn ($competition): array => [
                        'name' => $competition->name,
                        'slug' => $competition->slug,
                        'upcoming_fixtures_count' => (int) $competition->upcoming_fixtures_count,
                    ])->all(),
                ])
                ->all()));
        });
    }

    /**
     * Super admins hold every admin permission. Abilities that are not permissions (such as deleting an audit
     * record) stay with their policies, so no role can bypass them.
     */
    protected function configureAdminAuthorization(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);
        // Players are the User model; the name says what the admin panel treats them as.
        Gate::policy(User::class, PlayerPolicy::class);

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
