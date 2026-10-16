<?php

namespace App\Support;

use App\Livewire\Projects;
use App\Livewire\Users;
use App\Models\User;
use InvalidArgumentException;

class Navigation
{
    /**
     * Main menu, in display order. A user sees an item when they have any of its permissions.
     * routes/web.php registers one route per item from this list: the Livewire `component` when
     * the page is built, otherwise a placeholder. `middleware` adds route middleware (e.g. password.confirm).
     *
     * @return list<array{label: string, url: string, route: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Clock In/Out', 'url' => 'clock', 'route' => 'clock', 'icon' => 'clock', 'permissions' => ['attendance.clock']],
            ['label' => 'My Attendance', 'url' => 'attendance/mine', 'route' => 'attendance.mine', 'icon' => 'calendar-days', 'permissions' => ['attendance.view-own']],
            ['label' => 'All Attendance', 'url' => 'attendance', 'route' => 'attendance.index', 'icon' => 'table-cells', 'permissions' => ['attendance.view-all']],
            ['label' => 'Projects', 'url' => 'projects', 'route' => 'projects.index', 'icon' => 'building-office-2', 'permissions' => ['expenses.view', 'projects.manage'], 'component' => Projects\Index::class],
            ['label' => 'Material Entries', 'url' => 'material-entries', 'route' => 'material-entries.index', 'icon' => 'shopping-cart', 'permissions' => ['material-entries.create']],
            ['label' => 'Materials', 'url' => 'materials', 'route' => 'materials.index', 'icon' => 'cube', 'permissions' => ['materials.manage']],
            ['label' => 'Users', 'url' => 'users', 'route' => 'users.index', 'icon' => 'users', 'permissions' => ['users.manage'], 'component' => Users\Index::class, 'middleware' => ['password.confirm']],
            ['label' => 'Reports', 'url' => 'reports', 'route' => 'reports.index', 'icon' => 'document-chart-bar', 'permissions' => ['reports.export']],
        ];
    }

    /**
     * The menu items the given user may see.
     *
     * @return list<array{label: string, url: string, route: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(
            self::items(),
            fn (array $item) => $user->hasAnyPermission($item['permissions']),
        ));
    }

    /**
     * Route middleware for an item, e.g. "permission:expenses.view|projects.manage".
     */
    public static function middlewareFor(string $route): string
    {
        foreach (self::items() as $item) {
            if ($item['route'] === $route) {
                return 'permission:'.implode('|', $item['permissions']);
            }
        }

        throw new InvalidArgumentException("Unknown menu route [{$route}].");
    }
}
