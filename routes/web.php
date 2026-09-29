<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// No public landing page (spec 003): home goes to the dashboard, or to login for guests.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// Library structure: rooms, bookcases, shelves and compartments (spec 004).
Route::middleware(['auth'])->group(function () {
    Volt::route('rooms', 'shelving.index')->name('rooms.index');

    Volt::route('rooms/{room}/bookcases/create', 'shelving.bookcase-form')
        ->middleware('can:update,room')
        ->name('bookcases.create');

    Volt::route('bookcases/{bookcase}/edit', 'shelving.bookcase-form')
        ->middleware('can:update,bookcase')
        ->name('bookcases.edit');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('users/{user}/rooms', 'shelving.index')
        ->middleware('can:manageLibrary,user')
        ->name('users.rooms');

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
