<?php

use App\Http\Middleware\CacheResponse;
use App\Http\Middleware\LogPortViews;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'cache.response' => CacheResponse::class,
            'log.views' => LogPortViews::class,
        ]);

        // Apply global middleware to web routes
        $middleware->web(append: [
            SecurityHeaders::class,
            LogPortViews::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
