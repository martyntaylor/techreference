<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Port;
use App\Models\Software;
use App\Observers\CategoryObserver;
use App\Observers\PortObserver;
use App\Observers\SoftwareObserver;
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
    }
}
