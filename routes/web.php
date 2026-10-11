<?php

use App\Livewire\Auth\ChangePassword;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect('/clock') : redirect()->route('login');
})->name('home');

Route::livewire('password/change', ChangePassword::class)
    ->middleware('auth')
    ->name('password.change');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
