<?php

namespace App\Providers;

use App\Http\Controllers\Oauth\AuthorizationController;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Owner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(AuthorizationController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard', null)));

        $this->app->bind(
            \Laravel\Passport\Bridge\ClientRepository::class,
            \App\Bridge\ClientRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::viewNamespace('passport');

        Relation::morphMap([
            'Company' => Owner::class,
            'Individual' => Owner::class,
            'Developer' => Owner::class,
        ]);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Event::listen(
            Login::class,
            LogSuccessfulLogin::class
        );

        Event::listen(
            Logout::class,
            LogSuccessfulLogout::class
        );

        Event::listen(
            Failed::class,
            LogFailedLogin::class
        );
    }
}
