@props([
    'target',
    'variant' => 'card', // 'card' or 'badge'
    'size' => 'md',
])

<div wire:loading wire:target="{{ $target }}" {{ $attributes->merge(['class' => 'animate-fade-in']) }}>
    @if ($variant === 'badge')
        <flux:badge color="zinc" size="sm" class="animate-pulse">
            <flux:icon.loading variant="micro" class="mr-1.5" />
            {{ $slot }}
        </flux:badge>
    @else
        <div class="flex items-center gap-3 p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10">
            <flux:icon.loading :variant="$size === 'micro' ? 'micro' : ($size === 'sm' ? 'mini' : 'outline')" class="text-indigo-600 dark:text-indigo-400" />
            <span class="{{ $size === 'micro' || $size === 'sm' ? 'text-xs' : 'text-sm' }} font-semibold text-indigo-900 dark:text-indigo-300">
                {{ $slot }}
            </span>
        </div>
    @endif
</div>
