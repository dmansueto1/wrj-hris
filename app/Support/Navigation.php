<?php

namespace App\Support;

use App\Livewire\Materials;
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
     * @return list<array{label: string, url: string, route: string, group: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Clock In/Out', 'url' => 'clock', 'route' => 'clock', 'group' => 'Time', 'icon' => 'clock', 'permissions' => ['attendance.clock']],
            ['label' => 'My Attendance', 'url' => 'attendance/mine', 'route' => 'attendance.mine', 'group' => 'Time', 'icon' => 'calendar-days', 'permissions' => ['attendance.view-own']],
            ['label' => 'All Attendance', 'url' => 'attendance', 'route' => 'attendance.index', 'group' => 'Time', 'icon' => 'table-cells', 'permissions' => ['attendance.view-all']],
            ['label' => 'Projects', 'url' => 'projects', 'route' => 'projects.index', 'group' => 'Operations', 'icon' => 'building-office-2', 'permissions' => ['expenses.view', 'projects.manage'], 'component' => Projects\Index::class],
            ['label' => 'Material Entries', 'url' => 'material-entries', 'route' => 'material-entries.index', 'group' => 'Operations', 'icon' => 'shopping-cart', 'permissions' => ['material-entries.create']],
            ['label' => 'Materials', 'url' => 'materials', 'route' => 'materials.index', 'group' => 'Operations', 'icon' => 'cube', 'permissions' => ['materials.manage'], 'component' => Materials\Index::class],
            ['label' => 'Users', 'url' => 'users', 'route' => 'users.index', 'group' => 'Admin', 'icon' => 'users', 'permissions' => ['users.manage'], 'component' => Users\Index::class, 'middleware' => ['password.confirm']],
            ['label' => 'Reports', 'url' => 'reports', 'route' => 'reports.index', 'group' => 'Admin', 'icon' => 'document-chart-bar', 'permissions' => ['reports.export']],
        ];
    }

    /**
     * The menu items the given user may see.
     *
     * @return list<array{label: string, url: string, route: string, group: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(
            self::items(),
            fn (array $item) => $user->hasAnyPermission($item['permissions']),
        ));
    }

    /**
     * The user's menu items grouped under their section label ("Time", "Operations", "Admin").
     * Empty groups are left out.
     *
     * @return array<string, list<array{label: string, url: string, route: string, group: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}>>
     */
    public static function groupedFor(User $user): array
    {
        $groups = [];

        foreach (self::for($user) as $item) {
            $groups[$item['group']][] = $item;
        }

        return $groups;
    }

    /**
     * The menu item a page belongs to: an exact match ("attendance.mine"), otherwise the
     * section's list page ("users.edit" belongs to "users.index").
     *
     * @return array{label: string, url: string, route: string, group: string, icon: string, permissions: list<string>, component?: class-string, middleware?: list<string>}|null
     */
    public static function owner(?string $routeName): ?array
    {
        if ($routeName === null) {
            return null;
        }

        $prefix = explode('.', $routeName)[0];

        return collect(self::items())->first(fn (array $item) => $item['route'] === $routeName)
            ?? collect(self::items())->first(fn (array $item) => $item['route'] === $prefix.'.index');
    }

    /**
     * Breadcrumbs for the top bar: section, menu page (linked) and, on sub-pages, the page title.
     * "users.edit" belongs to the "users.index" menu item; "attendance.mine" matches itself.
     *
     * @return list<array{label: string, url: ?string}>
     */
    public static function breadcrumbs(?string $routeName, ?string $pageTitle = null): array
    {
        if ($routeName === null) {
            return $pageTitle ? [['label' => $pageTitle, 'url' => null]] : [];
        }

        $item = self::owner($routeName);

        if ($item === null) {
            return $pageTitle ? [['label' => $pageTitle, 'url' => null]] : [];
        }

        $crumbs = [
            ['label' => $item['group'], 'url' => null],
            ['label' => $item['label'], 'url' => route($item['route'])],
        ];

        if ($item['route'] !== $routeName && $pageTitle) {
            $crumbs[] = ['label' => $pageTitle, 'url' => null];
        }

        return $crumbs;
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
