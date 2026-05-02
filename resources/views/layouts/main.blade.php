@extends('layouts.app')

@section('main')
<div class="flex min-h-screen w-full">
    @include('layouts.sidebar')

    @include('layouts.header')

    <flux:main container>
        <flux:heading size="xl">@yield('title', 'Página inicial')</flux:heading>
        <flux:separator variant="subtle" class="my-2" />

        @yield('content')



    </flux:main>
</div>
@endsection