<flux:header class="flex items-center justify-between px-4 sm:px-6 lg:px-8 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm h-16">
    <div class="flex items-center gap-4">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:breadcrumbs class="hidden sm:flex">
            <flux:breadcrumbs.item href="{{ route('home') }}">Dashboard</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="flex items-center gap-4">
        <livewire:components.notifications-center />
    </div>
</flux:header>
