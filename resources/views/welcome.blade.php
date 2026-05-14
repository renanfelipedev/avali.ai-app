@extends('layouts.app')

@section('main')
<div class="relative min-h-screen">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 w-full border-b border-zinc-200 bg-white/80 dark:bg-zinc-900/80 dark:border-zinc-800 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <flux:icon.sparkles class="size-8 text-indigo-600 dark:text-indigo-400" />
                    <span class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">avali.ai</span>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <flux:button href="{{ route('home') }}" variant="ghost">Painel</flux:button>
                    @else
                        <flux:button href="{{ route('login') }}" variant="ghost">Entrar</flux:button>
                        <flux:button href="{{ route('cadastro') }}" variant="primary">Criar Conta</flux:button>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative py-20 lg:py-32 overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-indigo-500 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-emerald-500 rounded-full blur-[120px]"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <flux:badge variant="neutral" class="mb-6 py-1 px-3">Modernizando a educação com IA</flux:badge>
            <h1 class="text-5xl lg:text-7xl font-extrabold tracking-tight text-zinc-900 dark:text-white mb-8">
                Crie e Corrija Provas em <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-emerald-500">Segundos</span>
            </h1>
            <p class="max-w-2xl mx-auto text-xl text-zinc-600 dark:text-zinc-400 mb-10">
                A plataforma definitiva para professores. Gere avaliações personalizadas e corrija submissões automaticamente usando o poder do Gemini 2.5 Flash.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <flux:button href="{{ route('cadastro') }}" variant="primary" class="w-full sm:w-auto px-10 h-14 text-lg">Começar Agora</flux:button>
                <flux:button href="#features" variant="ghost" class="w-full sm:w-auto px-10 h-14 text-lg">Ver Funcionalidades</flux:button>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-zinc-50 dark:bg-zinc-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <flux:heading size="xl" class="mb-4">Tudo o que você precisa</flux:heading>
                <flux:subheading>Ferramentas poderosas para otimizar sua rotina acadêmica.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <flux:card class="p-8 space-y-4 hover:border-indigo-500 transition-colors cursor-default">
                    <div class="size-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <flux:icon.document-duplicate />
                    </div>
                    <flux:heading size="lg">Gerador de Provas</flux:heading>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Crie questões objetivas e discursivas baseadas em seus próprios PDFs, livros ou temas específicos.
                    </p>
                </flux:card>

                <flux:card class="p-8 space-y-4 hover:border-emerald-500 transition-colors cursor-default">
                    <div class="size-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <flux:icon.check-badge />
                    </div>
                    <flux:heading size="lg">Corretor Inteligente</flux:heading>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Corrija provas manuscritas ou digitais instantaneamente. Receba feedbacks detalhados e notas automáticas.
                    </p>
                </flux:card>

                <flux:card class="p-8 space-y-4 hover:border-amber-500 transition-colors cursor-default">
                    <div class="size-12 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <flux:icon.sparkles />
                    </div>
                    <flux:heading size="lg">Multi-Model AI</flux:heading>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Sistema resiliente com fallback automático entre modelos Gemini para garantir alta disponibilidade.
                    </p>
                </flux:card>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 border-y border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">99%</div>
                    <div class="text-sm text-zinc-500 uppercase tracking-widest">Precisão da IA</div>
                </div>
                <div>
                    <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">+10k</div>
                    <div class="text-sm text-zinc-500 uppercase tracking-widest">Provas Geradas</div>
                </div>
                <div>
                    <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">24/7</div>
                    <div class="text-sm text-zinc-500 uppercase tracking-widest">Disponibilidade</div>
                </div>
                <div>
                    <div class="text-4xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">10x</div>
                    <div class="text-sm text-zinc-500 uppercase tracking-widest">Mais Rápido</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2">
                <flux:icon.sparkles class="size-6 text-indigo-600 dark:text-indigo-400" />
                <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">avali.ai</span>
            </div>
            <p class="text-zinc-500 text-sm">
                &copy; {{ date('Y') }} avali.ai - Todos os direitos reservados.
            </p>
            <div class="flex gap-6">
                <a href="#" class="text-zinc-400 hover:text-indigo-600 transition-colors">Termos</a>
                <a href="#" class="text-zinc-400 hover:text-indigo-600 transition-colors">Privacidade</a>
            </div>
        </div>
    </footer>
</div>
@endsection
