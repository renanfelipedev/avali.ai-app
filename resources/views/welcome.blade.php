@extends('layouts.app')

@section('main')
<div class="relative min-h-screen selection:bg-indigo-500 selection:text-white">
    <!-- Announcement Banner -->
    <div class="bg-gradient-to-r from-indigo-600 via-indigo-700 to-emerald-600 text-white text-xs sm:text-sm font-medium py-2.5 px-4 text-center">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-white/20 text-white text-xs font-semibold uppercase tracking-wider">
                <flux:icon.sparkles class="size-3.5 inline" /> Novidade
            </span>
            <span>Chamada Online Anti-Fraude com Geofencing GPS e exportação de avaliações em PDF e Word (.docx)!</span>
            <a href="#chamada" class="underline hover:text-white/80 font-semibold ml-1 inline-flex items-center gap-1">
                Saiba mais &rarr;
            </a>
        </div>
    </div>

    <!-- Navigation Header -->
    <nav class="sticky top-0 z-50 w-full border-b border-zinc-200/80 bg-white/85 backdrop-blur-md dark:border-zinc-800/80 dark:bg-zinc-900/85">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Brand / Logo -->
                <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 transition-transform hover:scale-105">
                    <img src="{{ asset('images/logo.png') }}" alt="avali.ai logo" class="size-8 object-contain">
                    <span class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-indigo-600 via-indigo-500 to-emerald-500 bg-clip-text text-transparent">
                        avali.ai
                    </span>
                </a>

                <!-- Desktop Navigation Links -->
                <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                    <a href="#provas" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Provas com IA</a>
                    <a href="#correcao" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Correção OCR</a>
                    <a href="#chamada" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Chamada GPS</a>
                    <a href="#turmas" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Turmas & Alunos</a>
                    <a href="#como-funciona" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Como Funciona</a>
                    <a href="#faq" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">FAQ</a>
                </div>

                <!-- CTA Auth Buttons -->
                <div class="flex items-center gap-3">
                    @auth
                        <flux:button href="{{ route('home') }}" variant="primary" icon="arrow-right">
                            Painel de Controle
                        </flux:button>
                    @else
                        <flux:button href="{{ route('login') }}" variant="ghost" class="text-sm font-semibold">
                            Entrar
                        </flux:button>
                        <flux:button href="{{ route('cadastro') }}" variant="primary" class="text-sm font-semibold shadow-sm hover:shadow-indigo-500/20">
                            Criar Conta Grátis
                        </flux:button>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-20 pb-28 lg:pt-28 lg:pb-36 overflow-hidden">
        <!-- Glow Backdrops -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-[550px] -z-10 pointer-events-none opacity-40 dark:opacity-30">
            <div class="absolute top-0 left-1/4 w-[500px] h-[500px] bg-indigo-500 rounded-full blur-[140px]"></div>
            <div class="absolute bottom-0 right-1/4 w-[450px] h-[450px] bg-emerald-500 rounded-full blur-[140px]"></div>
            <div class="absolute top-1/3 right-1/3 w-[350px] h-[350px] bg-amber-400 rounded-full blur-[150px] opacity-20"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- Hero Pill Badge -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-indigo-200/80 bg-indigo-50/70 dark:border-indigo-800/80 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 text-xs sm:text-sm font-semibold mb-8 backdrop-blur-sm shadow-sm animate-pulse">
                <flux:icon.sparkles class="size-4 text-indigo-600 dark:text-indigo-400" />
                <span>Gestão Pedagógica com Gemini 3.8 Flash & Geofencing GPS</span>
            </div>

            <!-- Main Heading -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-zinc-900 dark:text-white max-w-5xl mx-auto leading-[1.12] mb-8">
                Crie Provas, Corrija com IA e Faça Chamada Presencial em <span class="bg-gradient-to-r from-indigo-600 via-indigo-500 to-emerald-500 bg-clip-text text-transparent">Minutos</span>
            </h1>

            <!-- Subtitle -->
            <p class="max-w-3xl mx-auto text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 leading-relaxed mb-10">
                A plataforma acadêmica completa para professores modernos. Elabore avaliações diagramadas em PDF e Word, automatize correções de provas manuscritas via OCR e realize chamadas online com validação geográfica por GPS e PIN dinâmico.
            </p>

            <!-- CTA Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-14">
                <flux:button href="{{ route('cadastro') }}" variant="primary" class="w-full sm:w-auto px-8 h-13 text-base font-semibold shadow-lg shadow-indigo-600/25 hover:shadow-indigo-600/40 transition-all">
                    Começar Gratuitamente
                </flux:button>
                <flux:button href="#recursos" variant="ghost" class="w-full sm:w-auto px-8 h-13 text-base font-semibold border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                    Conhecer Funcionalidades
                </flux:button>
            </div>

            <!-- Trust / Reassurance Row -->
            <div class="flex flex-wrap items-center justify-center gap-6 sm:gap-10 text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-4 text-emerald-500" />
                    <span>Sem necessidade de cartão de crédito</span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-4 text-emerald-500" />
                    <span>Exportação pronta para impressão</span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-4 text-emerald-500" />
                    <span>Anti-fraude com GPS e PIN</span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-4 text-emerald-500" />
                    <span>Suporte a Google Classroom</span>
                </div>
            </div>

            <!-- Live Product Showcase Simulation -->
            <div class="mt-16 sm:mt-20 max-w-5xl mx-auto rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 bg-zinc-900/5 dark:bg-zinc-900/40 p-3 sm:p-5 backdrop-blur-xl shadow-2xl shadow-zinc-900/10">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-inner text-left">
                    <!-- Fake Window Topbar -->
                    <div class="flex items-center justify-between px-4 py-3 bg-zinc-100/80 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700/60 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded-full bg-rose-500 inline-block"></span>
                            <span class="size-3 rounded-full bg-amber-500 inline-block"></span>
                            <span class="size-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="ml-2 font-mono text-zinc-500 dark:text-zinc-400 hidden sm:inline">avali.ai/dashboard</span>
                        </div>
                        <div class="flex items-center gap-3 text-zinc-500">
                            <span class="flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                <span class="size-2 rounded-full bg-emerald-500 animate-ping inline-block"></span>
                                Sistema Operacional (Gemini 3.8 Flash)
                            </span>
                        </div>
                    </div>

                    <!-- Showcase Cards Grid -->
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Preview Card 1: Chamada Online Anti-Fraude -->
                        <div class="p-5 rounded-xl border border-indigo-100 dark:border-indigo-950/60 bg-gradient-to-b from-indigo-50/50 to-white dark:from-indigo-950/20 dark:to-zinc-900 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                    Chamada ao Vivo
                                </span>
                                <flux:icon.clock class="size-4 text-indigo-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-zinc-900 dark:text-white text-sm">Física Teórica - 3º A</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Geofencing: Raio de 50 metros</p>
                            </div>
                            <div class="p-2.5 rounded-lg bg-zinc-100 dark:bg-zinc-800/80 flex items-center justify-between">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">PIN do Projetor:</span>
                                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 tracking-widest text-sm">7 4 1 9</span>
                            </div>
                            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1.5">
                                <flux:icon.check-circle class="size-4 shrink-0" />
                                <span>38 de 40 alunos confirmados na sala</span>
                            </div>
                        </div>

                        <!-- Preview Card 2: Prova Gerada por IA -->
                        <div class="p-5 rounded-xl border border-emerald-100 dark:border-emerald-950/60 bg-gradient-to-b from-emerald-50/50 to-white dark:from-emerald-950/20 dark:to-zinc-900 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                    Prova Diagramada
                                </span>
                                <flux:icon.document-duplicate class="size-4 text-emerald-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-zinc-900 dark:text-white text-sm">Simulado Biologia Celular</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">10 questões • Objetivas e Discursivas</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-800 text-[11px] font-mono font-medium text-zinc-700 dark:text-zinc-300 flex items-center gap-1">
                                    <flux:icon.arrow-down-tray class="size-3" /> PDF Pronto
                                </span>
                                <span class="px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-800 text-[11px] font-mono font-medium text-zinc-700 dark:text-zinc-300 flex items-center gap-1">
                                    <flux:icon.arrow-down-tray class="size-3" /> Word (.docx)
                                </span>
                            </div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 flex items-center gap-1.5">
                                <flux:icon.sparkles class="size-4 text-emerald-600 shrink-0" />
                                <span>Gabarito e critérios pedagógicos inclusos</span>
                            </div>
                        </div>

                        <!-- Preview Card 3: Correção Automatizada OCR -->
                        <div class="p-5 rounded-xl border border-amber-100 dark:border-amber-950/60 bg-gradient-to-b from-amber-50/50 to-white dark:from-amber-950/20 dark:to-zinc-900 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                    Correção OCR Concluída
                                </span>
                                <flux:icon.check-badge class="size-4 text-amber-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-zinc-900 dark:text-white text-sm">Submissão: Gabriel Alves</h3>
                                <p class="text-xs text-zinc-500 mt-0.5">Leitura de folhas manuscritas</p>
                            </div>
                            <div class="p-2.5 rounded-lg bg-zinc-100 dark:bg-zinc-800/80 flex items-center justify-between">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Nota Calculada:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">9.5 / 10.0</span>
                            </div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 flex items-center gap-1.5">
                                <flux:icon.check-circle class="size-4 text-amber-600 shrink-0" />
                                <span>Feedback pedagógico detalhado questão a questão</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Impact & Metrics Section -->
    <section class="py-16 border-y border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center divide-y lg:divide-y-0 lg:divide-x divide-zinc-200 dark:divide-zinc-800">
                <div class="pt-4 lg:pt-0">
                    <div class="text-4xl sm:text-5xl font-black bg-gradient-to-r from-indigo-600 to-indigo-500 bg-clip-text text-transparent mb-1">
                        99.4%
                    </div>
                    <div class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Precisão OCR & Correção
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">Visão multimodal com Gemini</div>
                </div>

                <div class="pt-4 lg:pt-0">
                    <div class="text-4xl sm:text-5xl font-black bg-gradient-to-r from-emerald-600 to-emerald-500 bg-clip-text text-transparent mb-1">
                        -85%
                    </div>
                    <div class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Tempo de Correção & Diagramação
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">Mais tempo para o ensino</div>
                </div>

                <div class="pt-4 lg:pt-0">
                    <div class="text-4xl sm:text-5xl font-black bg-gradient-to-r from-indigo-600 to-emerald-500 bg-clip-text text-transparent mb-1">
                        100%
                    </div>
                    <div class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Anti-Fraude na Presença
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">Geofencing GPS e PIN rotativo</div>
                </div>

                <div class="pt-4 lg:pt-0">
                    <div class="text-4xl sm:text-5xl font-black bg-gradient-to-r from-amber-500 to-amber-600 bg-clip-text text-transparent mb-1">
                        24/7
                    </div>
                    <div class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Alta Disponibilidade
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">Multi-Model Fallback contínuo</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Comprehensive Modules Section -->
    <section id="recursos" class="py-24 lg:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <flux:badge variant="neutral" class="mb-4 py-1 px-3">Ecossistema Acadêmico Unificado</flux:badge>
                <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white mb-6">
                    Tudo o que você precisa para uma rotina escolar eficiente
                </h2>
                <p class="text-lg text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Do primeiro dia de aula até a entrega das notas finais: o avali.ai centraliza elaboração, frequência e avaliação em uma experiência fluida e poderosa.
                </p>
            </div>

            <!-- Feature 1: Provas com IA -->
            <div id="provas" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-28">
                <div class="space-y-6">
                    <div class="size-12 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <flux:icon.document-duplicate class="size-6" />
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-zinc-900 dark:text-white">
                        Geração de Provas Personalizadas com Suporte Multimodal
                    </h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Esqueça noites em claro montando questões. O avali.ai analisa seus PDFs, apostilas, ementas ou temas de aula e elabora provas completas com questões objetivas e discursivas rigorosamente alinhadas à BNCC e diretrizes do ensino superior.
                    </p>
                    <ul class="space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-indigo-500 shrink-0" />
                            <span>Exportação instantânea em <strong>PDF diagramado</strong> profissional pronto para impressão.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-indigo-500 shrink-0" />
                            <span>Download em <strong>Microsoft Word (.docx)</strong> 100% editável com seu cabeçalho escolar.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-indigo-500 shrink-0" />
                            <span>Gabarito comentado e critérios pedagógicos automáticos para cada questão.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-indigo-500 shrink-0" />
                            <span>Configuração de perfis de impressão e número de colunas.</span>
                        </li>
                    </ul>
                    <div class="pt-2">
                        <flux:button href="{{ route('cadastro') }}" variant="primary" icon="sparkles">
                            Experimentar Gerador de Provas
                        </flux:button>
                    </div>
                </div>

                <div class="relative">
                    <flux:card class="p-6 sm:p-8 space-y-4 shadow-xl border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div>
                                <span class="text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400 font-bold">Prévia de Exportação</span>
                                <h4 class="font-bold text-zinc-900 dark:text-white text-base">Avaliação Bimestral de História</h4>
                            </div>
                            <flux:badge color="emerald" size="sm">Pronta para Impressão</flux:badge>
                        </div>
                        <div class="space-y-3 text-xs text-zinc-600 dark:text-zinc-400">
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800">
                                <span class="font-semibold text-zinc-900 dark:text-white">Questão 1 (Objetiva):</span>
                                <p class="mt-1">Sobre as transformações políticas do Período Joanino no Brasil, analise as proposições...</p>
                                <div class="mt-2 space-y-1 text-zinc-500">
                                    <div>[A] Abertura dos portos às nações amigas (1808)</div>
                                    <div>[B] Tratado de Aliança e Amizade</div>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800">
                                <span class="font-semibold text-zinc-900 dark:text-white">Questão 2 (Discursiva):</span>
                                <p class="mt-1">Explique o impacto do bloqueio continental nas rotas comerciais marítimas...</p>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <flux:button variant="ghost" size="sm" class="w-1/2" icon="arrow-down-tray">Baixar PDF</flux:button>
                            <flux:button variant="ghost" size="sm" class="w-1/2" icon="arrow-down-tray">Baixar Word</flux:button>
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Feature 2: Chamada Online com Geolocalização & Anti-Fraude -->
            <div id="chamada" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-28">
                <div class="order-2 lg:order-1 relative">
                    <flux:card class="p-6 sm:p-8 space-y-5 shadow-xl border-emerald-200/60 dark:border-emerald-900/60 bg-gradient-to-b from-emerald-50/20 to-white dark:from-emerald-950/10 dark:to-zinc-900">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div class="flex items-center gap-2">
                                <span class="size-3 rounded-full bg-emerald-500 animate-ping"></span>
                                <h4 class="font-bold text-zinc-900 dark:text-white text-base">Sessão de Presença Ativa</h4>
                            </div>
                            <flux:badge color="amber" size="sm">Expira em 42 min</flux:badge>
                        </div>

                        <!-- Simulated Projector Screen Box -->
                        <div class="p-5 rounded-xl bg-zinc-900 text-white text-center space-y-3">
                            <div class="text-xs uppercase tracking-wider text-zinc-400 font-semibold">Exibição no Projetor da Sala</div>
                            <div class="text-3xl sm:text-4xl font-mono font-black tracking-widest text-emerald-400">
                                PIN: 8 3 5 1
                            </div>
                            <p class="text-[11px] text-zinc-400">
                                Escaneie o QR Code na tela ou acesse <span class="text-white underline">avali.ai/c/aula-mat</span>
                            </p>
                        </div>

                        <!-- Security Features checklist -->
                        <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400">
                            <div class="flex items-center justify-between p-2 rounded bg-zinc-50 dark:bg-zinc-800/50">
                                <span class="flex items-center gap-2">
                                    <flux:icon.map-pin class="size-4 text-emerald-500" />
                                    <span>Validação por GPS (Geofencing)</span>
                                </span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">Raio de 75m Ativo</span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded bg-zinc-50 dark:bg-zinc-800/50">
                                <span class="flex items-center gap-2">
                                    <flux:icon.lock-closed class="size-4 text-emerald-500" />
                                    <span>Filtro de Matrícula</span>
                                </span>
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Apenas Alunos da Turma</span>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                            <flux:icon.envelope class="size-4 shrink-0 text-emerald-600" />
                            <span>Ao encerrar a aula, a lista completa de presença é enviada por e-mail em PDF.</span>
                        </div>
                    </flux:card>
                </div>

                <div class="order-1 lg:order-2 space-y-6">
                    <div class="size-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <flux:icon.clock class="size-6" />
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-zinc-900 dark:text-white">
                        Chamada Online Inteligente com Geolocalização & Anti-Fraude
                    </h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Acabe definitivamente com a perda de tempo passando folhas de chamada e impeça que estudantes assinem pelo colega ausente ou enviem o link pelo WhatsApp.
                    </p>
                    <ul class="space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-emerald-500 shrink-0" />
                            <span><strong>Geofencing por GPS:</strong> A presença só é computada se as coordenadas do smartphone do aluno estiverem dentro do raio delimitado da sala.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-emerald-500 shrink-0" />
                            <span><strong>Código PIN Dinâmico:</strong> Código visível apenas no projetor, garantindo a presença visual do estudante no ambiente.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-emerald-500 shrink-0" />
                            <span><strong>Modo Apenas Matriculados:</strong> Impede check-in de alunos que não façam parte da lista oficial da turma.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-emerald-500 shrink-0" />
                            <span><strong>Fechamento Automatizado:</strong> Chamadas expiram no tempo determinado e geram relatórios consolidados enviados por e-mail.</span>
                        </li>
                    </ul>
                    <div class="pt-2">
                        <flux:button href="{{ route('cadastro') }}" variant="primary" icon="play-circle">
                            Começar a Usar Chamada Online
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Feature 3: Correção Automatizada OCR -->
            <div id="correcao" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-28">
                <div class="space-y-6">
                    <div class="size-12 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <flux:icon.check-badge class="size-6" />
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-zinc-900 dark:text-white">
                        Correção Automatizada de Avaliações com Visão Computacional
                    </h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Digitalize pilhas de provas em segundos. Nosso motor alimentado por Gemini Multimodal processa tanto respostas digitais quanto caligrafia manuscrita em fotos ou PDFs, comparando cada raciocínio com o gabarito.
                    </p>
                    <ul class="space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-amber-500 shrink-0" />
                            <span><strong>OCR Multimodal Avançado:</strong> Reconhecimento de escrita cursiva e impressa com alta fidelidade.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-amber-500 shrink-0" />
                            <span><strong>Feedback Pedagógico Individual:</strong> A IA aponta exatamente onde o aluno acertou e onde errou, oferecendo justificativa clara.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-amber-500 shrink-0" />
                            <span><strong>Cálculo Automático de Notas:</strong> Pontuação ponderada por critérios pré-estabelecidos sem viés subjetivo.</span>
                        </li>
                    </ul>
                    <div class="pt-2">
                        <flux:button href="{{ route('cadastro') }}" variant="primary" icon="clipboard-document-check">
                            Automatizar Minhas Correções
                        </flux:button>
                    </div>
                </div>

                <div class="relative">
                    <flux:card class="p-6 sm:p-8 space-y-4 shadow-xl border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <div>
                                <span class="text-xs uppercase tracking-wider text-amber-600 dark:text-amber-400 font-bold">Relatório de Correção</span>
                                <h4 class="font-bold text-zinc-900 dark:text-white text-base">Aluno: Mariana Costa</h4>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Nota: 8.5</div>
                                <div class="text-xs text-zinc-400">Pontuação máxima: 10.0</div>
                            </div>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="p-3 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/50">
                                <div class="flex items-center justify-between font-semibold text-emerald-800 dark:text-emerald-300">
                                    <span>Questão 1: Conceito de Leis de Newton</span>
                                    <span>+3.0 / 3.0</span>
                                </div>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Resposta correta. O aluno demonstrou domínio claro sobre a inércia e a segunda lei.</p>
                            </div>

                            <div class="p-3 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-800/50">
                                <div class="flex items-center justify-between font-semibold text-amber-800 dark:text-amber-300">
                                    <span>Questão 2: Cálculo de Aceleração</span>
                                    <span>+1.5 / 2.0</span>
                                </div>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Fórmula aplicada corretamente, porém com pequeno desvio no arredondamento decimal final.</p>
                            </div>
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Feature 4: Gestão Ágil de Turmas & Estudantes -->
            <div id="turmas" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="order-2 lg:order-1 relative">
                    <flux:card class="p-6 sm:p-8 space-y-4 shadow-xl border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                            <flux:heading size="md">Turmas Cadastradas</flux:heading>
                            <flux:badge color="zinc">3 Turmas Ativas</flux:badge>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between hover:border-indigo-500 transition-colors">
                                <div>
                                    <div class="font-bold text-zinc-900 dark:text-white text-sm">3º Ano A - Ensino Médio</div>
                                    <div class="text-zinc-500">Matemática Aplicada • Colégio Objetivo</div>
                                </div>
                                <flux:badge color="indigo">38 alunos</flux:badge>
                            </div>

                            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between hover:border-indigo-500 transition-colors">
                                <div>
                                    <div class="font-bold text-zinc-900 dark:text-white text-sm">Engenharia de Software II</div>
                                    <div class="text-zinc-500">Arquitetura de Sistemas • Campus Central</div>
                                </div>
                                <flux:badge color="indigo">44 alunos</flux:badge>
                            </div>

                            <div class="p-3.5 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 text-center py-4 text-zinc-500">
                                <flux:icon.arrow-up-tray class="size-5 mx-auto mb-1 text-zinc-400" />
                                <span>Importe listas de alunos em lote via arquivo (.txt ou .csv)</span>
                            </div>
                        </div>
                    </flux:card>
                </div>

                <div class="order-1 lg:order-2 space-y-6">
                    <div class="size-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <flux:icon.academic-cap class="size-6" />
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-zinc-900 dark:text-white">
                        Gestão Ágil de Turmas e Alunos com Importação em Lote
                    </h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Mantenha suas turmas e disciplinas sob controle. Organize múltiplos cursos, importe centenas de alunos de uma vez a partir de planilhas e deixe nossa inteligência padronizar nomes e acentos automaticamente.
                    </p>
                    <ul class="space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-blue-500 shrink-0" />
                            <span><strong>Importação Rápida por Arquivo:</strong> Cole ou faça upload de arquivos .txt e .csv exportados do SIGAA, Moodle ou Excel.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-blue-500 shrink-0" />
                            <span><strong>Sanitização Inteligente de Nomes:</strong> Correção automática de acentuação portuguesa e caixa alta/baixa.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <flux:icon.check-circle class="size-5 text-blue-500 shrink-0" />
                            <span>Histórico individual de notas, presenças em chamadas e evolução pedagógica.</span>
                        </li>
                    </ul>
                    <div class="pt-2">
                        <flux:button href="{{ route('cadastro') }}" variant="primary" icon="plus-circle">
                            Cadastrar Minhas Turmas
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="como-funciona" class="py-24 bg-zinc-50 dark:bg-zinc-900/50 border-y border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <flux:badge variant="neutral" class="mb-3 py-1 px-3">Passo a Passo Simplificado</flux:badge>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-zinc-900 dark:text-white">
                    Como funciona o avali.ai na sua rotina
                </h2>
                <p class="text-zinc-600 dark:text-zinc-400 mt-3 text-base">
                    Projetado para professores que buscam resultados rápidos sem curvas de aprendizado complicadas.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <flux:card class="p-6 space-y-4 hover:border-indigo-500 transition-colors">
                    <div class="size-10 rounded-xl bg-indigo-600 text-white font-black text-lg flex items-center justify-center">
                        1
                    </div>
                    <flux:heading size="md">Crie ou Importe Turmas</flux:heading>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Cadastre suas disciplinas e importe a lista de alunos em instantes a partir de arquivos de texto ou planilhas.
                    </p>
                </flux:card>

                <!-- Step 2 -->
                <flux:card class="p-6 space-y-4 hover:border-indigo-500 transition-colors">
                    <div class="size-10 rounded-xl bg-indigo-600 text-white font-black text-lg flex items-center justify-center">
                        2
                    </div>
                    <flux:heading size="md">Inicie a Chamada com QR Code</flux:heading>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Abra a chamada no projetor. Os alunos registram presença no celular com validação por GPS e PIN em poucos segundos.
                    </p>
                </flux:card>

                <!-- Step 3 -->
                <flux:card class="p-6 space-y-4 hover:border-indigo-500 transition-colors">
                    <div class="size-10 rounded-xl bg-indigo-600 text-white font-black text-lg flex items-center justify-center">
                        3
                    </div>
                    <flux:heading size="md">Gere Provas em PDF & Word</flux:heading>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Informe o tema ou suba o material didático. A IA elabora questões de alto nível prontas para impressão diagramada.
                    </p>
                </flux:card>

                <!-- Step 4 -->
                <flux:card class="p-6 space-y-4 hover:border-indigo-500 transition-colors">
                    <div class="size-10 rounded-xl bg-indigo-600 text-white font-black text-lg flex items-center justify-center">
                        4
                    </div>
                    <flux:heading size="md">Corrija com Visão Computacional</flux:heading>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Faça o upload das fotos das provas. O sistema calcula as notas e gera relatórios pedagógicos automáticos.
                    </p>
                </flux:card>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <flux:badge variant="neutral" class="mb-3 py-1 px-3">Dúvidas Frequentes</flux:badge>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-zinc-900 dark:text-white">
                    Perguntas Frequentes
                </h2>
                <p class="text-zinc-600 dark:text-zinc-400 mt-3 text-base">
                    Tire suas dúvidas sobre o funcionamento do avali.ai
                </p>
            </div>

            <div class="space-y-4">
                <flux:card class="p-6 space-y-2">
                    <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                        Como funciona o sistema de geolocalização na chamada online?
                    </h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Quando você inicia a chamada, o professor pode definir um raio de distância em metros (por exemplo, 100m). Ao escanear o QR Code ou acessar o link, o navegador do estudante valida sua posição via GPS. Se ele estiver fora do raio ou tentando marcar presença de casa, o check-in é bloqueado automaticamente.
                    </p>
                </flux:card>

                <flux:card class="p-6 space-y-2">
                    <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                        Para que serve o PIN temporário de 4 dígitos na chamada?
                    </h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        O PIN é uma camada extra de segurança exibida na tela do projetor ou compartilhada verbalmente pelo professor. Isso impede que alunos dentro da sala repassem o link do check-in por WhatsApp ou redes sociais para amigos que faltaram à aula.
                    </p>
                </flux:card>

                <flux:card class="p-6 space-y-2">
                    <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                        Posso baixar as provas geradas no Microsoft Word para editar?
                    </h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Sim! O avali.ai oferece exportação direta tanto em PDF profissional (diagramado em colunas, com cabeçalho de instituição e gabarito) quanto em arquivo Word (.docx), permitindo que você altere textos, imagens e formatações livremente.
                    </p>
                </flux:card>

                <flux:card class="p-6 space-y-2">
                    <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                        A IA consegue ler provas escritas à mão pelos alunos?
                    </h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Sim. Graças à tecnologia multimodal do Gemini 3.8 Flash, o sistema realiza reconhecimento óptico de caracteres (OCR) avançado, interpretando respostas discursivas manuscritas e comparando a resposta do estudante com os critérios do gabarito.
                    </p>
                </flux:card>

                <flux:card class="p-6 space-y-2">
                    <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                        Como faço para importar meus alunos sem ter que digitar um a um?
                    </h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Basta acessar a turma desejada e clicar em "Importar Lista". Você pode fazer upload de um arquivo de texto (.txt) ou planilha (.csv) contendo os nomes. O sistema higieniza a lista, formata acentos e cadastra todos os alunos em poucos segundos.
                    </p>
                </flux:card>
            </div>
        </div>
    </section>

    <!-- Final CTA Banner -->
    <section class="py-20 lg:py-28 bg-gradient-to-br from-indigo-900 via-zinc-900 to-black text-white relative overflow-hidden">
        <div class="absolute inset-0 -z-10 opacity-30">
            <div class="absolute top-1/2 left-1/3 w-96 h-96 bg-indigo-500 rounded-full blur-[140px]"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-emerald-500 rounded-full blur-[140px]"></div>
        </div>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white max-w-3xl mx-auto">
                Pronto para transformar sua rotina docente e economizar horas de trabalho?
            </h2>
            <p class="text-lg text-zinc-300 max-w-2xl mx-auto">
                Crie sua conta gratuita em menos de 1 minuto e descubra a praticidade de ter geração de provas, correção e presença inteligente em um único lugar.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                <flux:button href="{{ route('cadastro') }}" variant="primary" class="w-full sm:w-auto px-10 h-14 text-lg font-bold shadow-xl shadow-indigo-500/30">
                    Criar Minha Conta Gratuita
                </flux:button>
                <flux:button href="{{ route('login') }}" variant="filled" class="w-full sm:w-auto px-10 h-14 text-lg font-bold !text-white !bg-white/15 hover:!bg-white/25 !border !border-white/30 backdrop-blur-md shadow-lg transition-all">
                    Já possuo uma conta &rarr;
                </flux:button>
            </div>
        </div>
    </section>

    <!-- Modern Footer -->
    <footer class="py-12 bg-white dark:bg-zinc-950 border-t border-zinc-200 dark:border-zinc-800 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo.png') }}" alt="avali.ai logo" class="size-7 object-contain">
                    <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">avali.ai</span>
                </div>

                <div class="flex flex-wrap justify-center gap-6 text-zinc-500 dark:text-zinc-400">
                    <a href="#provas" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Provas com IA</a>
                    <a href="#correcao" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Correção OCR</a>
                    <a href="#chamada" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Chamada GPS</a>
                    <a href="#turmas" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Turmas</a>
                    <a href="{{ route('login') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Entrar</a>
                    <a href="{{ route('cadastro') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Cadastrar</a>
                </div>

                <p class="text-zinc-500 text-xs">
                    &copy; {{ date('Y') }} avali.ai - Todos os direitos reservados.
                </p>
            </div>
        </div>
    </footer>
</div>
@endsection
