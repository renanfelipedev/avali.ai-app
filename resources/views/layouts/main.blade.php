@extends('layouts.app')

@section('main')
<div class="flex min-h-screen w-full">
    @include('layouts.sidebar')

    <div class="flex flex-col flex-1 w-full min-w-0">
        @include('layouts.header')

        <flux:main container class="mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
            @hasSection('title')
                <flux:heading size="xl" class="mb-2">@yield('title')</flux:heading>
                <flux:separator variant="subtle" class="mb-6" />
            @endif

            <x-flash />

            @php
                $maintenanceBanner = \App\Models\SystemSetting::getBool('maintenance_banner_enabled', false)
                    ? \App\Models\SystemSetting::get('maintenance_banner_message')
                    : null;
            @endphp

            @if ($maintenanceBanner)
                <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
                    <flux:callout.text>{{ $maintenanceBanner }}</flux:callout.text>
                </flux:callout>
            @endif


            @yield('content')
            {{ $slot ?? '' }}
        </flux:main>
    </div>
</div>
@endsection