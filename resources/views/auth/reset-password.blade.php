@extends('layouts.app')

@section('main')
    <div class="flex min-h-screen">
        <div class="flex-1 flex justify-center items-center">
            <div class="w-80 max-w-80 space-y-6">
                <flux:heading class="text-center" size="xl">Escolher Nova Senha</flux:heading>
                <flux:subheading class="text-center">Preencha os campos abaixo para definir sua nova senha de acesso.</flux:subheading>

                <x-flash />
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="flex flex-col gap-6">
                        <flux:input label="Confirme seu E-mail" name="email" type="email" value="{{ old('email', $email) }}" required autofocus />

                        <flux:input label="Nova Senha" name="password" type="password" placeholder="Mínimo 6 caracteres" required />

                        <flux:input label="Confirmar Nova Senha" name="password_confirmation" type="password" placeholder="Digite a senha novamente" required />

                        <flux:button type="submit" variant="primary" class="w-full">Atualizar Senha</flux:button>
                    </div>
                </form>

                <div class="text-center">
                    <flux:subheading>
                        <flux:link href="{{ route('login') }}">Voltar para o login</flux:link>
                    </flux:subheading>
                </div>
            </div>
        </div>

        <x-auth-testimonial />
    </div>
@endsection
