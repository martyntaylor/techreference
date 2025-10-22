<?php

use App\Http\Controllers\Ports\CategoryController;
use App\Http\Controllers\Ports\PortController;
use App\Http\Controllers\Ports\RangeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Port Routes
|--------------------------------------------------------------------------
*/

// Individual port page
Route::get('/port/{portNumber}', [PortController::class, 'show'])
    ->name('port.show')
    ->whereNumber('portNumber') // 1-65535 validated in ShowPortRequest
    ->middleware('cache.response');

// Ports landing page (must come before other /ports/* routes)
Route::get('/ports', [\App\Http\Controllers\Ports\PortsHomeController::class, 'index'])
    ->name('ports.index')
    ->middleware('cache.response');

// Port range view
Route::get('/ports/range/{start}-{end}', [RangeController::class, 'show'])
    ->name('ports.range')
    ->whereNumber(['start', 'end']) // 1-65535 validated in RangeRequest
    ->middleware('cache.response');

// Category listing (keep this last among /ports/* routes)
Route::get('/ports/{slug}', [CategoryController::class, 'show'])
    ->name('ports.category')
    ->where('slug', '[a-z0-9-]+');

// Unified search
Route::get('/search', [SearchController::class, 'index'])
    ->name('search')
    ->middleware('cache.response');

/*
|--------------------------------------------------------------------------
| Dashboard & Settings Routes
|--------------------------------------------------------------------------
*/

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('settings/profile', [Settings\ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::put('settings/profile', [Settings\ProfileController::class, 'update'])->name('settings.profile.update');
    Route::delete('settings/profile', [Settings\ProfileController::class, 'destroy'])->name('settings.profile.destroy');
    Route::get('settings/password', [Settings\PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [Settings\PasswordController::class, 'update'])->name('settings.password.update');
    Route::get('settings/appearance', [Settings\AppearanceController::class, 'edit'])->name('settings.appearance.edit');
});

require __DIR__.'/auth.php';
