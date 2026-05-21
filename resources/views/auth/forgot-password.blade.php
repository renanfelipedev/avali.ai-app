@extends('layouts.app')

@section('main')
    <div class="flex min-h-screen">
        <div class="flex-1 flex justify-center items-center">
            <div class="w-80 max-w-80 space-y-6">
                <div class="flex justify-center mb-6">
                    <a href="/" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo.png') }}" alt="avali.ai logo" class="h-10 w-auto">
                        <span class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">avali.ai</span>
                    </a>
                </div>
                <flux:heading class="text-center" size="xl">Recuperar Senha</flux:heading>
                <flux:subheading class="text-center">Insira seu e-mail cadastrado e enviaremos um link de redefinição.</flux:subheading>

                <x-flash />

                @if (session('status'))
                    <div class="text-center mt-6">
                        <flux:subheading>
                            <flux:link href="{{ route('login') }}">Voltar para o login</flux:link>
                        </flux:subheading>
                    </div>
                @else
                    <form action="{{ route('password.email') }}" method="POST">
                        @csrf
                        <div class="flex flex-col gap-6">
                            <flux:input label="E-mail" name="email" type="email" placeholder="seu-email@exemplo.com" required autofocus />

                            <flux:button type="submit" variant="primary" class="w-full">Enviar Link</flux:button>
                        </div>
                    </form>

                    <div class="text-center">
                        <flux:subheading>
                            <flux:link href="{{ route('login') }}">Voltar para o login</flux:link>
                        </flux:subheading>
                    </div>
                @endif
            </div>
        </div>

        <x-auth-testimonial />
    </div>
@endsection
