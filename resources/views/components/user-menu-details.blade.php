{{-- Name, employee no. and role, shown at the top of the user menu (desktop and mobile). --}}
<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
    <flux:avatar
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
    />
    <div class="grid flex-1 text-start text-sm leading-tight">
        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
        <flux:text class="truncate">{{ auth()->user()->employee_no }}</flux:text>
        <flux:text class="truncate">{{ auth()->user()->roleLabel() }}</flux:text>
    </div>
</div>
