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

            @yield('content')
            {{ $slot ?? '' }}
        </flux:main>
    </div>
</div>
@endsection