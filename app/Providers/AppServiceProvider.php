<?php

namespace App\Providers;

use App\Models\Bookcase;
use App\Models\Room;
use App\Models\User;
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
        // Volt routes have no typed action, so implicit binding does not apply: without these,
        // "can:" middleware would receive the raw id instead of the model.
        Route::model('user', User::class);
        Route::model('room', Room::class);
        Route::model('bookcase', Bookcase::class);
    }
}
