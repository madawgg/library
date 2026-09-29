<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('users', 'admin.users.index')
        ->middleware('can:viewAny,App\Models\User')
        ->name('users.index');

    Volt::route('users/create', 'admin.users.create')
        ->middleware('can:create,App\Models\User')
        ->name('users.create');

    Volt::route('users/{user}/edit', 'admin.users.edit')
        ->middleware('can:update,user')
        ->name('users.edit');
});

require __DIR__.'/auth.php';
