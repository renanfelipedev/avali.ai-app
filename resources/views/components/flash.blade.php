@if (session('status') || session('success'))
    <flux:callout variant="success" icon="check" class="mb-4">
        <flux:callout.text>
            {{ session('status') ?? session('success') }}
        </flux:callout.text>
    </flux:callout>
@endif

@if (session('error') || session('danger'))
    <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
        <flux:callout.text>
            {{ session('error') ?? session('danger') }}
        </flux:callout.text>
    </flux:callout>
@endif

@if (session('warning'))
    <flux:callout variant="warning" icon="exclamation-circle" class="mb-4">
        <flux:callout.text>
            {{ session('warning') }}
        </flux:callout.text>
    </flux:callout>
@endif

@if (session('info'))
    <flux:callout variant="info" icon="information-circle" class="mb-4">
        <flux:callout.text>
            {{ session('info') }}
        </flux:callout.text>
    </flux:callout>
@endif
