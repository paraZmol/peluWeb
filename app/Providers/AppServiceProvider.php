<?php

namespace App\Providers;

use App\Support\CurrentSalon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset for every request and queued job, never leaks between them.
        $this->app->scoped(CurrentSalon::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        Vite::useBuildDirectory(config('app.build_directory'));
    }

    /**
     * Invitations send email to arbitrary addresses: cap them per user and
     * per salon so an account cannot be used to spam.
     */
    protected function configureRateLimiting(): void
    {
        // Guessing a password is slowed down per address and per account:
        // changing address does not buy more tries against one email.
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(5)->by('api-login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('api-login-account:'.sha1(mb_strtolower($request->string('email')->toString()))),
            Limit::perHour(20)->by('api-login-account-hour:'.sha1(mb_strtolower($request->string('email')->toString()))),
        ]);

        RateLimiter::for('invitations', fn (Request $request) => [
            Limit::perMinute(20)->by('user:'.$request->user()?->id),
            Limit::perDay(50)->by('salon:'.app(CurrentSalon::class)->id()),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Fail loudly in development on N+1 queries, unfillable attributes and
        // missing attributes, instead of silently shipping slow or wrong code.
        Model::shouldBeStrict(! app()->isProduction());

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
