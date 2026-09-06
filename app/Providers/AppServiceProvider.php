<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Observers\TripObserver;
use App\Observers\VehicleObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        // Fix for MySQL key length error with utf8mb4
        Schema::defaultStringLength(191);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Observe vehicle changes to track driver assignments
        Vehicle::observe(VehicleObserver::class);

        // Observe trip changes to track vehicle assignments
        Trip::observe(TripObserver::class);

        // Log authentication events to the activity log
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);
    }
}
