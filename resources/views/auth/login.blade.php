@extends('layouts.app')

@section('main')
<div class="flex min-h-screen">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <flux:heading class="text-center" size="xl">Login</flux:heading>
            <flux:subheading class="text-center">Bem-vindo de volta ao futuro da educação.</flux:subheading>

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
                O avali.ai transformou minha rotina, permitindo que eu foque no que realmente importa: o aprendizado dos meus alunos.
            </div>

            <div class="flex gap-4">
                <flux:avatar src="https://ui-avatars.com/api/?name=Maria+Silva&background=random" size="xl" />

                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">Maria Silva</div>
                    <div class="text-zinc-300">Professora de Biologia</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection