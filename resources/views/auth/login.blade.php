@extends('layouts.app')

@section('main')
<div class="flex min-h-screen">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <flux:heading class="text-center" size="xl">Login</flux:heading>

            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="flex flex-col gap-6">
                    <flux:input label="Email" name="email" type="email" placeholder="email@exemplo.com" />

                    <flux:input label="Senha" name="password" type="password" placeholder="Sua senha" />

                    <flux:button type="submit" variant="primary" class="w-full">Entrar</flux:button>
                </div>
            </form>

            <flux:subheading>
                Primeira vez aqui? <flux:link href="{{ route('cadastro') }}"> Crie sua conta de graça</flux:link>
            </flux:subheading>

            <flux:subheading>
                <flux:link href="#" class="text-sm">Esqueceu a senha?</flux:link>
            </flux:subheading>
        </div>
    </div>

    <div class="flex-1 p-4 max-lg:hidden">
        <div class="text-white relative rounded-lg h-full w-full bg-zinc-900 flex flex-col items-start justify-end p-16" style="background-image: url('/img/demo/auth_aurora_2x.png'); background-size: cover">
            <div class="flex gap-2 mb-4">
                <flux:icon.star variant="solid" />
                <flux:icon.star variant="solid" />
                <flux:icon.star variant="solid" />
                <flux:icon.star variant="solid" />
                <flux:icon.star variant="solid" />
            </div>

            <div class="mb-6 italic font-base text-3xl xl:text-4xl">
                Flux has enabled me to design, build, and deliver apps faster than ever before.
            </div>

            <div class="flex gap-4">
                <flux:avatar src="https://fluxui.dev/img/demo/caleb.png" size="xl" />

                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">Caleb Porzio</div>
                    <div class="text-zinc-300">Creator of Livewire</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection