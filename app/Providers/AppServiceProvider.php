<?php

namespace App\Providers;

use App\Bridge\ClientRepository;
use App\Http\Controllers\Oauth\AuthorizationController;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\Owner;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\View;
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
            ClientRepository::class
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

        // Event::listen(
        //     Login::class,
        //     LogSuccessfulLogin::class
        // );

        // Event::listen(
        //     Logout::class,
        //     LogSuccessfulLogout::class
        // );

        // Event::listen(
        //     Failed::class,
        //     LogFailedLogin::class
        // );

        if (app()->environment('local', 'testing')) {
            Event::listen(MessageSending::class, function (MessageSending $event) {
                Log::info('E2E_MAIL_SENT: '.$event->message->toString());
            });
        }

        // Resolve version from VERSION file, fallback to 1.0.0
        $versionFile = base_path('VERSION');
        $appVersion = '1.0.0';
        if (file_exists($versionFile)) {
            $appVersion = trim(file_get_contents($versionFile));
            $appVersion = ltrim($appVersion, 'vV');
        }

        config(['app.version' => $appVersion]);
        View::share('appVersion', $appVersion);

        // Register Filament footer hook
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): \Illuminate\Contracts\View\View => view('components.footer')
        );
    }
}
