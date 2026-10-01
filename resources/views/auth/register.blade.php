@extends('layouts.app')

@section('main')
<div class="flex min-h-screen">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <form action="{{ route('cadastro') }}" method="POST" id="register-form">
                @csrf

                {{-- Honeypot anti-bot invisível --}}
                <div class="hidden" style="display: none;" aria-hidden="true">
                    <input type="text" name="website_hp" tabindex="-1" autocomplete="off" value="">
                </div>

                {{-- Token reCAPTCHA v3 --}}
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                <div class="flex justify-center mb-6">
                    <a href="/" class="flex items-center gap-2">
                        <img src="{{ asset('images/logo.png') }}" alt="avali.ai logo" class="h-10 w-auto">
                        <span class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">avali.ai</span>
                    </a>
                </div>
                <flux:heading class="text-center mb-4" size="xl">Cadastre-se</flux:heading>

                <x-flash />

                @error('g-recaptcha-response')
                    <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                        <flux:callout.text>
                            {{ $message }}
                        </flux:callout.text>
                    </flux:callout>
                @enderror

                <div class="flex flex-col gap-2">
                    <flux:input label="Nome completo" value="{{ old('name') }}" name="name" placeholder="email@exemplo.com" />

                    <flux:input label="E-mail" value="{{ old('email') }}" name="email" type="email" placeholder="email@exemplo.com" />

                    <flux:separator />

                    <flux:input label="Senha" name="password" type="password" placeholder="Sua senha" />

                    <flux:input label="Confirmação de Senha" name="password_confirmation" type="password" placeholder="Confirme sua senha" />

                    <flux:button type="submit" variant="primary" class="w-full">Cadastrar</flux:button>
                </div>

                <flux:subheading class="text-center mt-4">
                    Já possui acesso? <flux:link href="{{ route('login') }}">Entre no sistema</flux:link>
                </flux:subheading>
            </form>

            @if (\App\Models\SystemSetting::getBool('google_login_enabled', true))
                <flux:separator text="ou" />

                <flux:button href="{{ route('auth.google') }}" class="w-full" variant="outline">
                    <svg class="w-4 h-4 mr-2" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                    </svg>
                    Cadastrar com o Google
                </flux:button>
            @endif

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

@php
    $recaptchaEnabled = \App\Models\SystemSetting::getBool('recaptcha_enabled', true);
    $recaptchaSiteKey = \App\Models\SystemSetting::get('recaptcha_site_key', config('services.recaptcha.site_key'));
@endphp

@if ($recaptchaEnabled && $recaptchaSiteKey)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('register-form');
            if (!form) return;

            form.addEventListener('submit', function (event) {
                const tokenInput = document.getElementById('g-recaptcha-response');
                const siteKey = "{{ $recaptchaSiteKey }}";

                // Se já possui token preenchido (ou sem chave), envia diretamente
                if (!siteKey || (tokenInput && tokenInput.value)) {
                    return;
                }

                event.preventDefault();

                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function () {
                        grecaptcha.execute(siteKey, { action: 'register' })
                            .then(function (token) {
                                tokenInput.value = token;
                                form.submit();
                            })
                            .catch(function (error) {
                                console.error('reCAPTCHA error:', error);
                                form.submit();
                            });
                    });
                } else {
                    form.submit();
                }
            });
        });
    </script>
@endif
@endsection