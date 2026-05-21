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
                <flux:heading class="text-center" size="xl">Login</flux:heading>
                <flux:subheading class="text-center">Bem-vindo de volta ao futuro da educação.</flux:subheading>

                <x-flash />

                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    <div class="flex flex-col gap-6">
                        <flux:input label="Email" name="email" type="email" placeholder="email@exemplo.com" />

                        <flux:input label="Senha" name="password" type="password" placeholder="Sua senha" />

                        <flux:button type="submit" variant="primary" class="w-full">Entrar</flux:button>
                    </div>
                </form>

                <flux:separator text="ou" />

                <flux:button href="{{ route('auth.google') }}" class="w-full" variant="outline">
                    <svg class="w-4 h-4 mr-2" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            fill="#4285F4" />
                        <path
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            fill="#34A853" />
                        <path
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                            fill="#FBBC05" />
                        <path
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                            fill="#EA4335" />
                    </svg>
                    Entrar com o Google
                </flux:button>

                <flux:subheading>
                    Primeira vez aqui? <flux:link href="{{ route('cadastro') }}"> Crie sua conta de graça</flux:link>
                </flux:subheading>

                <flux:subheading>
                    <flux:link href="{{ route('password.request') }}" class="text-sm">Esqueceu a senha?</flux:link>
                </flux:subheading>
            </div>
        </div>

        <x-auth-testimonial />
    </div>
@endsection
