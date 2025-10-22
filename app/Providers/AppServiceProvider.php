<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Port;
use App\Models\Software;
use App\Observers\CategoryObserver;
use App\Observers\PortObserver;
use App\Observers\SoftwareObserver;
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

        // Register custom route model bindings
        $this->registerRouteModelBindings();
    }

    /**
     * Register custom route model bindings.
     */
    protected function registerRouteModelBindings(): void
    {
        // Bind Port model by port_number instead of ID
        Route::bind('portNumber', function (string $value) {
            return Port::where('port_number', $value)->firstOrFail();
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
