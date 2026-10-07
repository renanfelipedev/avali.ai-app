@extends('layouts.main')

@section('title', 'Home')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Bem-vindo, {{ auth()->user()->name }}!</flux:heading>
            <flux:subheading>Visão geral e atalhos rápidos do avali.ai</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <flux:button href="{{ route('exams.create') }}" variant="primary" icon="sparkles">
                Nova Prova
            </flux:button>
            <flux:button href="{{ route('attendance.index', ['nova' => 1]) }}" variant="filled" icon="play-circle">
                Iniciar Chamada
            </flux:button>
        </div>
    </div>

    @can('admin')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <flux:card class="flex flex-col items-center justify-center p-6 text-center">
                <flux:icon.users class="size-8 text-indigo-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_users'] ?? 0 }}</flux:heading>
                <flux:subheading>Usuários Totais</flux:subheading>
                <div class="mt-2 text-xs text-green-600 font-medium">{{ $stats['active_users'] ?? 0 }} ativos</div>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center p-6 text-center">
                <flux:icon.document-duplicate class="size-8 text-blue-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_exams'] ?? 0 }}</flux:heading>
                <flux:subheading>Provas Geradas</flux:subheading>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center p-6 text-center">
                <flux:icon.check-badge class="size-8 text-emerald-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_evaluations'] ?? 0 }}</flux:heading>
                <flux:subheading>Correções Feitas</flux:subheading>
            </flux:card>

            <livewire:admin.ai-status-card />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
            <flux:card class="lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Provas Recentes</flux:heading>
                    <flux:button href="{{ route('exams.index') }}" variant="ghost" size="sm">Ver todas</flux:button>
                </div>
                <div class="overflow-x-auto w-full scrollbar-none">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Título</flux:table.column>
                            <flux:table.column>Usuário</flux:table.column>
                            <flux:table.column>Data</flux:table.column>
                            <flux:table.column>Ações</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($stats['recent_exams'] ?? [] as $exam)
                                <flux:table.row>
                                    <flux:table.cell class="font-medium truncate max-w-xs" tooltip="{{ $exam->title }}">
                                        {{ Str::limit($exam->title, 45) }}
                                    </flux:table.cell>
                                    <flux:table.cell class="truncate max-w-[150px]">{{ Str::limit($exam->user?->name ?? 'N/A', 25) }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">{{ $exam->created_at->format('d/m H:i') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button href="{{ route('exams.show', $exam) }}" variant="ghost" size="sm" icon="eye">Ver</flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-6 text-zinc-500">
                                        Nenhuma prova gerada recentemente.
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Atalhos Rápidos</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('exams.create') }}" variant="ghost" class="w-full justify-start" icon="sparkles">Criar Nova Prova</flux:button>
                    <flux:button href="{{ route('classrooms.index', ['criar' => 1]) }}" variant="ghost" class="w-full justify-start" icon="plus-circle">Cadastrar Turma</flux:button>
                    <flux:button href="{{ route('students.index', ['criar' => 1]) }}" variant="ghost" class="w-full justify-start" icon="user-plus">Cadastrar Aluno</flux:button>
                    <flux:button href="{{ route('attendance.index', ['nova' => 1]) }}" variant="ghost" class="w-full justify-start" icon="play-circle">Iniciar Chamada</flux:button>
                    <flux:button href="{{ route('users.index') }}" variant="ghost" class="w-full justify-start" icon="users">Gerenciar Usuários</flux:button>
                    <flux:button href="{{ route('ai-logs.index') }}" variant="ghost" class="w-full justify-start" icon="document-text">Analisar Logs</flux:button>
                </div>
            </flux:card>
        </div>
    @else
        <!-- Professor Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <flux:card class="flex flex-col items-center justify-center p-6 text-center hover:border-indigo-500/50 transition-colors">
                <flux:icon.academic-cap class="size-8 text-indigo-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_classrooms'] ?? 0 }}</flux:heading>
                <flux:subheading>Suas Turmas</flux:subheading>
                <flux:button href="{{ route('classrooms.index') }}" variant="ghost" size="xs" class="mt-2">Ver Turmas</flux:button>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center p-6 text-center hover:border-blue-500/50 transition-colors">
                <flux:icon.users class="size-8 text-blue-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_students'] ?? 0 }}</flux:heading>
                <flux:subheading>Alunos Cadastrados</flux:subheading>
                <flux:button href="{{ route('students.index') }}" variant="ghost" size="xs" class="mt-2">Ver Alunos</flux:button>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center p-6 text-center hover:border-emerald-500/50 transition-colors">
                <flux:icon.document-duplicate class="size-8 text-emerald-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_exams'] ?? 0 }}</flux:heading>
                <flux:subheading>Provas Geradas</flux:subheading>
                <flux:button href="{{ route('exams.index') }}" variant="ghost" size="xs" class="mt-2">Ver Provas</flux:button>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center p-6 text-center hover:border-amber-500/50 transition-colors">
                <flux:icon.clock class="size-8 text-amber-500 mb-2" />
                <flux:heading size="lg">{{ $stats['total_attendance'] ?? 0 }}</flux:heading>
                <flux:subheading>Sessões de Chamada</flux:subheading>
                <flux:button href="{{ route('attendance.index') }}" variant="ghost" size="xs" class="mt-2">Ver Chamadas</flux:button>
            </flux:card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
            <flux:card class="lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Suas Provas Recentes</flux:heading>
                    <flux:button href="{{ route('exams.index') }}" variant="ghost" size="sm">Ver todas</flux:button>
                </div>

                @if(!empty($stats['recent_exams']) && count($stats['recent_exams']) > 0)
                    <div class="overflow-x-auto w-full scrollbar-none">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Título</flux:table.column>
                                <flux:table.column>Data</flux:table.column>
                                <flux:table.column>Ações</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($stats['recent_exams'] as $exam)
                                    <flux:table.row>
                                        <flux:table.cell class="font-medium truncate max-w-xs" tooltip="{{ $exam->title }}">
                                            {{ Str::limit($exam->title, 45) }}
                                        </flux:table.cell>
                                        <flux:table.cell class="whitespace-nowrap">{{ $exam->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button href="{{ route('exams.show', $exam) }}" variant="ghost" size="sm" icon="eye">Abrir</flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @else
                    <div class="py-10 text-center text-zinc-500">
                        <flux:icon.document-duplicate class="size-10 mx-auto mb-3 text-zinc-400" />
                        <p class="font-medium text-zinc-700 dark:text-zinc-300">Você ainda não gerou nenhuma avaliação.</p>
                        <p class="text-xs text-zinc-400 mt-1 mb-4">Utilize o gerador com Gemini para criar sua primeira prova em segundos.</p>
                        <flux:button href="{{ route('exams.create') }}" variant="primary" icon="sparkles" size="sm">Criar Prova Agora</flux:button>
                    </div>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Ações Rápidas de Cadastro</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('classrooms.index', ['criar' => 1]) }}" variant="filled" class="w-full justify-start" icon="plus-circle">
                        Cadastrar Nova Turma
                    </flux:button>
                    <flux:button href="{{ route('students.index', ['criar' => 1]) }}" variant="filled" class="w-full justify-start" icon="user-plus">
                        Cadastrar Novo Aluno
                    </flux:button>
                    <flux:button href="{{ route('exams.create') }}" variant="filled" class="w-full justify-start" icon="sparkles">
                        Gerar Nova Prova (IA)
                    </flux:button>
                    <flux:button href="{{ route('evaluations.create') }}" variant="filled" class="w-full justify-start" icon="clipboard-document-check">
                        Corrigir Provas (OCR)
                    </flux:button>
                    <flux:button href="{{ route('attendance.index', ['nova' => 1]) }}" variant="filled" class="w-full justify-start" icon="play-circle">
                        Iniciar Chamada Online
                    </flux:button>
                </div>
            </flux:card>
        </div>
    @endcan
</div>
@endsection