<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use App\Models\Category;
use App\Models\Port;
use App\Models\Software;
use App\Observers\CategoryObserver;
use App\Observers\PortObserver;
use App\Observers\SoftwareObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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
        // Register model observers
        Port::observe(PortObserver::class);
        Software::observe(SoftwareObserver::class);
        Category::observe(CategoryObserver::class);

        // Register authentication event listeners
        $this->registerAuthenticationListeners();

        // Register custom route model bindings
        $this->registerRouteModelBindings();
    }

    /**
     * Register authentication event listeners for audit logging.
     */
    protected function registerAuthenticationListeners(): void
    {
        $listener = new LogAuthenticationEvents;

        Event::listen(Login::class, [$listener, 'handleLogin']);
        Event::listen(Logout::class, [$listener, 'handleLogout']);
        Event::listen(Failed::class, [$listener, 'handleFailed']);
        Event::listen(Registered::class, [$listener, 'handleRegistered']);
        Event::listen(PasswordReset::class, [$listener, 'handlePasswordReset']);
    }

    /**
     * Register custom route model bindings.
     */
    protected function registerRouteModelBindings(): void
    {
        // Bind Port model by port_number - returns collection of all protocols for that port
        Route::bind('ports', function (string $value) {
            $ports = Port::where('port_number', $value)->get();

            if ($ports->isEmpty()) {
                abort(404);
            }

            return $ports;
        });

        // Bind Category by slug
        Route::bind('slug', function (string $value) {
            return Category::where('slug', $value)
                ->where('is_active', true)
                ->firstOrFail();
        });

        // Note: Software already uses slug via getRouteKeyName() in the model
        // Port uses port_number via getRouteKeyName() in the model
    }
}
