<?php

use App\Livewire\Auth\ChangePassword;
use App\Livewire\Projects;
use App\Livewire\Users;
use App\Models\Project;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('clock') : redirect()->route('login');
})->name('home');

Route::livewire('password/change', ChangePassword::class)
    ->middleware('auth')
    ->name('password.change');

// Main menu pages. Pages not built yet show a placeholder; later tickets replace each one.
// URL, name, title and permission all come from App\Support\Navigation, the same list the menu uses.
Route::middleware(['auth', 'verified'])->group(function () {
    foreach (Navigation::items() as $item) {
        $route = isset($item['component'])
            ? Route::livewire($item['url'], $item['component'])
            : Route::view($item['url'], 'placeholder', ['title' => $item['label']]);

        $route->middleware([Navigation::middlewareFor($item['route']), ...($item['middleware'] ?? [])])
            ->name($item['route']);
    }

    // Projects masterfile (T-07). Add/edit are Admin only; "create" is registered before {project}.
    Route::middleware('permission:projects.manage')->group(function () {
        Route::livewire('projects/create', Projects\Create::class)->name('projects.create');
        Route::livewire('projects/{project}/edit', Projects\Edit::class)->name('projects.edit');
    });

    // Project page: placeholder until T-16 adds running expenses.
    Route::get('projects/{project}', fn (Project $project) => view('placeholder', ['title' => $project->name]))
        ->middleware(Navigation::middlewareFor('projects.index'))
        ->name('projects.show');

    // Users masterfile (T-06): add and edit screens, same protection as the list.
    Route::middleware([Navigation::middlewareFor('users.index'), 'password.confirm'])->group(function () {
        Route::livewire('users/create', Users\Create::class)->name('users.create');
        Route::livewire('users/{user}/edit', Users\Edit::class)->name('users.edit');
    });
});

require __DIR__.'/settings.php';
