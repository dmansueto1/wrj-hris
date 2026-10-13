<?php

use App\Livewire\Auth\ChangePassword;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('clock') : redirect()->route('login');
})->name('home');

Route::livewire('password/change', ChangePassword::class)
    ->middleware('auth')
    ->name('password.change');

// Main menu pages. Placeholders for now; later tickets replace each one.
// URL, name, title and permission all come from App\Support\Navigation, the same list the menu uses.
Route::middleware(['auth', 'verified'])->group(function () {
    foreach (Navigation::items() as $item) {
        Route::view($item['url'], 'placeholder', ['title' => $item['label']])
            ->middleware(Navigation::middlewareFor($item['route']))
            ->name($item['route']);
    }
});

require __DIR__.'/settings.php';
