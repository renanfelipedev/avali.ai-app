@extends('layouts.main')

@section('title', 'Home')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Bem-vindo, {{ auth()->user()->name }}!</flux:heading>
        <flux:subheading>Visão geral do sistema avali.ai</flux:subheading>
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
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Título</flux:table.column>
                        <flux:table.column>Usuário</flux:table.column>
                        <flux:table.column>Data</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($stats['recent_exams'] ?? [] as $exam)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $exam->title }}</flux:table.cell>
                                <flux:table.cell>{{ $exam->user->name }}</flux:table.cell>
                                <flux:table.cell>{{ $exam->created_at->format('d/m H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Atalhos Rápidos</flux:heading>
                <div class="space-y-3">
                    <flux:button href="{{ route('users.index') }}" variant="ghost" class="w-full justify-start" icon="users">Gerenciar Usuários</flux:button>
                    <flux:button href="{{ route('exams.create') }}" variant="ghost" class="w-full justify-start" icon="plus">Criar Nova Prova</flux:button>
                    <flux:button href="{{ route('ai-logs.index') }}" variant="ghost" class="w-full justify-start" icon="document-text">Analisar Logs</flux:button>
                </div>
            </flux:card>
        </div>
    @else
        <flux:card>
            <flux:heading>Bem-vindo ao sistema de avaliação!</flux:heading>
            <flux:subheading>Utilize o menu lateral para acessar suas provas e correções.</flux:subheading>
        </flux:card>
    @endcan
</div>
@endsection