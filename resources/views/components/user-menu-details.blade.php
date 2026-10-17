{{-- Name, employee no. and role, shown at the top of the user menu (desktop and mobile). --}}
<div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
    <x-avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="lg" />
    <div class="grid min-w-0 flex-1 leading-tight">
        <span class="truncate font-semibold text-brand-navy dark:text-white">{{ auth()->user()->name }}</span>
        <span class="truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->employee_no }}</span>
        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->roleLabel() }}</span>
    </div>
</div>
