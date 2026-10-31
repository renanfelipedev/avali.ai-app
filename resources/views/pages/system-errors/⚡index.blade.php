<?php

use App\Models\SystemErrorLog;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component {
    use WithPagination;

    public ?int $selectedErrorId = null;
    public bool $showErrorModal = false;
    public string $search = '';
    public string $levelFilter = '';
    public string $dateFilter = 'all'; // all, today, 7days, 30days
    public array $selectedIds = [];
    public bool $selectAll = false;

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedIds = $this->getCurrentPageIds();
        } else {
            $this->selectedIds = [];
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedLevelFilter(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    protected function getFilteredQuery()
    {
        $query = SystemErrorLog::with('user')->latest();

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('message', 'like', "%{$this->search}%")
                    ->orWhere('exception_class', 'like', "%{$this->search}%")
                    ->orWhere('file', 'like', "%{$this->search}%")
                    ->orWhere('url', 'like', "%{$this->search}%")
                    ->orWhere('ip_address', 'like', "%{$this->search}%")
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%");
                    });
            });
        }

        if (! empty($this->levelFilter)) {
            $query->where('level', $this->levelFilter);
        }

        if ($this->dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->dateFilter === '7days') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->dateFilter === '30days') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        return $query;
    }

    protected function getCurrentPageIds(): array
    {
        return $this->getFilteredQuery()->paginate(20)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function with(): array
    {
        $query = $this->getFilteredQuery();

        return [
            'errors' => $query->paginate(20),
            'selectedError' => $this->selectedErrorId ? SystemErrorLog::with('user')->find($this->selectedErrorId) : null,
            'totalErrors' => SystemErrorLog::count(),
            'todayErrors' => SystemErrorLog::whereDate('created_at', today())->count(),
            'criticalErrors' => SystemErrorLog::whereIn('level', ['critical', 'emergency', 'alert'])->count(),
        ];
    }

    public function viewDetails(int $errorId): void
    {
        $this->selectedErrorId = $errorId;
        $this->showErrorModal = true;
    }

    public function deleteError(int $errorId): void
    {
        SystemErrorLog::where('id', $errorId)->delete();

        $this->selectedIds = array_values(array_diff($this->selectedIds, [$errorId]));

        if ($this->selectedErrorId === $errorId) {
            $this->showErrorModal = false;
            $this->selectedErrorId = null;
        }

        Flux::toast(heading: 'Sucesso', text: 'Registro de erro removido.');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $count = count($this->selectedIds);
        SystemErrorLog::whereIn('id', $this->selectedIds)->delete();

        $this->selectedIds = [];
        $this->selectAll = false;

        Flux::toast(heading: 'Erros Removidos', text: "{$count} registros foram excluídos com sucesso.");
    }

    public function clearAllErrors(): void
    {
        SystemErrorLog::truncate();
        $this->resetPage();
        $this->selectedErrorId = null;
        $this->showErrorModal = false;
        $this->selectedIds = [];
        $this->selectAll = false;

        Flux::toast(heading: 'Logs Limpos', text: 'Todos os registros de erro foram removidos com sucesso.');
    }
};
?>

<div>
    <!-- BREADCRUMBS -->
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('home') }}">Home</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Administração</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Central de Erros do Sistema</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <!-- CABEÇALHO -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" class="font-extrabold tracking-tight">🚨 Central de Erros do Sistema</flux:heading>
                <flux:badge color="rose" class="font-bold">Acesso Restrito ao Administrador</flux:badge>
            </div>
            <flux:subheading>Monitore, filtre e investigue exceções em tempo real com rastreamento completo de chamadas.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @if(count($selectedIds) > 0)
                <flux:button wire:click="deleteSelected" wire:confirm="Deseja excluir os itens selecionados?"
                    variant="danger" icon="trash">
                    Excluir Selecionados ({{ count($selectedIds) }})
                </flux:button>
            @endif

            <flux:button wire:click="clearAllErrors" wire:confirm="Tem certeza que deseja apagar TODOS os registros de erro do sistema?"
                variant="ghost" class="text-rose-600 hover:text-rose-700 dark:text-rose-400" icon="trash">
                Limpar Tudo
            </flux:button>
        </div>
    </div>

    <!-- CARDS DE MÉTRICAS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <flux:card class="flex items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm">
            <div class="p-3 bg-rose-50 dark:bg-rose-950/30 text-rose-600 rounded-xl">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
            </div>
            <div>
                <span class="text-xs text-zinc-500 font-medium">Erros Hoje</span>
                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $todayErrors }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm">
            <div class="p-3 bg-red-50 dark:bg-red-950/30 text-red-600 rounded-xl">
                <flux:icon.shield-exclamation class="w-6 h-6" />
            </div>
            <div>
                <span class="text-xs text-zinc-500 font-medium">Erros Críticos / Fatais</span>
                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $criticalErrors }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm">
            <div class="p-3 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 rounded-xl">
                <flux:icon.archive-box class="w-6 h-6" />
            </div>
            <div>
                <span class="text-xs text-zinc-500 font-medium">Total Acumulado</span>
                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $totalErrors }}</div>
            </div>
        </flux:card>
    </div>

    <!-- BARRA DE FILTROS AVANÇADOS -->
    <flux:card class="p-4 mb-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-6">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Filtrar por mensagem, exceção, rota, arquivo, IP ou usuário..." icon="magnifying-glass" />
            </div>

            <div class="sm:col-span-3">
                <flux:select wire:model.live="levelFilter" placeholder="Severidade">
                    <flux:select.option value="">Todas as Severidades</flux:select.option>
                    <flux:select.option value="error">Error</flux:select.option>
                    <flux:select.option value="critical">Critical</flux:select.option>
                    <flux:select.option value="alert">Alert</flux:select.option>
                    <flux:select.option value="emergency">Emergency</flux:select.option>
                </flux:select>
            </div>

            <div class="sm:col-span-3">
                <flux:select wire:model.live="dateFilter" placeholder="Período">
                    <flux:select.option value="all">Todo o Histórico</flux:select.option>
                    <flux:select.option value="today">Somente Hoje</flux:select.option>
                    <flux:select.option value="7days">Últimos 7 dias</flux:select.option>
                    <flux:select.option value="30days">Últimos 30 dias</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if(! empty($search) || ! empty($levelFilter) || $dateFilter !== 'all')
            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800 text-xs text-zinc-500">
                <span>Filtros ativos:</span>
                @if(! empty($search))
                    <flux:badge size="sm" color="zinc">Busca: "{{ $search }}"</flux:badge>
                @endif
                @if(! empty($levelFilter))
                    <flux:badge size="sm" color="rose">Nível: {{ strtoupper($levelFilter) }}</flux:badge>
                @endif
                @if($dateFilter !== 'all')
                    <flux:badge size="sm" color="indigo">Período: {{ $dateFilter }}</flux:badge>
                @endif
                <button wire:click="$set('search', ''); $set('levelFilter', ''); $set('dateFilter', 'all');"
                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold ml-auto">
                    Limpar Filtros
                </button>
            </div>
        @endif
    </flux:card>

    <!-- TABELA DE ERROS REFINADA -->
    <flux:card class="overflow-hidden p-0 border border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="overflow-x-auto w-full scrollbar-none">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-10">
                        <input type="checkbox" wire:model.live="selectAll"
                            class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500" />
                    </flux:table.column>
                    <flux:table.column class="w-32">Momento</flux:table.column>
                    <flux:table.column class="w-24">Nível</flux:table.column>
                    <flux:table.column>Erro & Mensagem</flux:table.column>
                    <flux:table.column class="w-64">Origem / Rota</flux:table.column>
                    <flux:table.column class="w-44">Usuário / IP</flux:table.column>
                    <flux:table.column align="end" class="w-24">Ações</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($errors as $error)
                        @php
                            $badgeColor = match(strtolower($error->level)) {
                                'critical', 'emergency', 'alert' => 'red',
                                'warning' => 'amber',
                                default => 'rose',
                            };

                            $isHttp = ! empty($error->url);
                            $urlPath = $isHttp ? parse_url($error->url, PHP_URL_PATH) : null;
                        @endphp
                        <flux:table.row class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors">
                            <flux:table.cell>
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $error->id }}"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500" />
                            </flux:table.cell>

                            <!-- Data e Hora -->
                            <flux:table.cell class="whitespace-nowrap">
                                <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 block">
                                    {{ $error->created_at->format('d/m/Y H:i:s') }}
                                </span>
                                <span class="text-[10px] text-zinc-400 font-medium">
                                    {{ $error->created_at->diffForHumans() }}
                                </span>
                            </flux:table.cell>

                            <!-- Nível -->
                            <flux:table.cell>
                                <flux:badge color="{{ $badgeColor }}" size="sm" class="uppercase font-bold text-[10px] tracking-wide">
                                    {{ $error->level }}
                                </flux:badge>
                            </flux:table.cell>

                            <!-- Mensagem e Exceção -->
                            <flux:table.cell class="max-w-md">
                                <div class="flex items-start gap-1.5">
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 font-mono text-[11px] font-bold text-zinc-700 dark:text-zinc-300 flex-shrink-0">
                                        {{ class_basename($error->exception_class ?? 'Exception') }}
                                    </span>
                                </div>
                                <div class="text-xs font-medium text-rose-700 dark:text-rose-400 line-clamp-2 mt-1 break-words font-mono" title="{{ $error->message }}">
                                    {{ $error->message }}
                                </div>
                                @if($error->file)
                                    <div class="text-[11px] text-zinc-400 font-mono truncate mt-1 flex items-center gap-1" title="{{ $error->file }}:{{ $error->line }}">
                                        <flux:icon.code-bracket class="w-3 h-3 flex-shrink-0" />
                                        <span>{{ basename($error->file) }}:<strong>{{ $error->line }}</strong></span>
                                    </div>
                                @endif
                            </flux:table.cell>

                            <!-- Origem / Rota -->
                            <flux:table.cell class="text-xs">
                                @if($isHttp)
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider
                                            {{ in_array($error->method, ['POST', 'PUT', 'DELETE']) ? 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300' : 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300' }}">
                                            {{ $error->method }}
                                        </span>
                                        <span class="font-mono text-zinc-800 dark:text-zinc-200 truncate max-w-[180px]" title="{{ $error->url }}">
                                            {{ $urlPath ?: '/' }}
                                        </span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] text-zinc-400 italic">
                                        <flux:icon.command-line class="w-3.5 h-3.5" />
                                        Console / Fila Background
                                    </span>
                                @endif
                            </flux:table.cell>

                            <!-- Usuário / IP -->
                            <flux:table.cell class="text-xs">
                                @if($error->user)
                                    <div class="font-semibold text-zinc-800 dark:text-zinc-200 truncate max-w-[160px]" title="{{ $error->user->name }}">
                                        {{ $error->user->name }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400 truncate max-w-[160px]">
                                        {{ $error->user->email }}
                                    </div>
                                @else
                                    <span class="text-zinc-400 text-xs italic">Não autenticado</span>
                                @endif

                                @if($error->ip_address)
                                    <div class="text-[10px] font-mono text-zinc-400 mt-0.5">
                                        IP: {{ $error->ip_address }}
                                    </div>
                                @endif
                            </flux:table.cell>

                            <!-- Ações -->
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="viewDetails({{ $error->id }})" title="Inspecionar Diagnóstico Completo" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteError({{ $error->id }})" class="text-rose-600 hover:text-rose-700 dark:text-rose-400" title="Excluir Registro" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-16 text-zinc-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 rounded-full mb-1">
                                        <flux:icon.check-circle class="w-8 h-8" />
                                    </div>
                                    <span class="text-base font-bold text-zinc-800 dark:text-zinc-200">Nenhum erro encontrado!</span>
                                    <span class="text-xs text-zinc-400 max-w-sm">
                                        @if(! empty($search) || ! empty($levelFilter) || $dateFilter !== 'all')
                                            Nenhum log corresponde aos critérios de filtro informados. Tente ajustar os termos de busca.
                                        @else
                                            Todas as requisições, endpoints e background jobs estão operando sem falhas registradas.
                                        @endif
                                    </span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if($errors->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                {{ $errors->links() }}
            </div>
        @endif
    </flux:card>

    <!-- MODAL DE DETALHES COMPLETOS DO ERRO -->
    <flux:modal wire:model="showErrorModal" class="md:w-[850px]">
        @if ($selectedError)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge color="{{ match(strtolower($selectedError->level)) { 'critical', 'emergency', 'alert' => 'red', default => 'rose' } }}" class="uppercase font-bold text-[10px]">
                                {{ $selectedError->level }}
                            </flux:badge>
                            <span class="text-xs text-zinc-400 font-mono">{{ $selectedError->created_at->format('d/m/Y H:i:s') }} ({{ $selectedError->created_at->diffForHumans() }})</span>
                        </div>
                        <flux:heading size="lg" class="font-black text-zinc-900 dark:text-zinc-100 font-mono">
                            {{ $selectedError->exception_class ?? 'Exception' }}
                        </flux:heading>
                    </div>
                </div>

                <!-- Mensagem Principal em Destaque -->
                <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50 shadow-inner">
                    <span class="text-xs font-bold text-rose-700 dark:text-rose-300 block mb-1 uppercase tracking-wider">Mensagem do Erro:</span>
                    <p class="text-sm font-semibold text-rose-950 dark:text-rose-100 font-mono whitespace-pre-wrap break-all">{{ $selectedError->message }}</p>
                </div>

                <!-- Informações Estruturadas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase tracking-wider">Origem no Código</span>
                        <p class="font-mono break-all text-zinc-800 dark:text-zinc-200">
                            {{ $selectedError->file ?: 'N/A' }}:<strong>{{ $selectedError->line ?: '-' }}</strong>
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase tracking-wider">Requisição HTTP</span>
                        <p class="font-mono break-all text-zinc-800 dark:text-zinc-200">
                            @if($selectedError->url)
                                <strong class="text-indigo-600 dark:text-indigo-400">{{ $selectedError->method }}</strong> {{ $selectedError->url }}
                            @else
                                <span class="italic text-zinc-400">Comando CLI ou Job em Segundo Plano</span>
                            @endif
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase tracking-wider">Usuário Impactado</span>
                        <p class="text-zinc-800 dark:text-zinc-200">
                            @if($selectedError->user)
                                <strong>{{ $selectedError->user->name }}</strong> (ID #{{ $selectedError->user->id }} - {{ $selectedError->user->email }})
                            @else
                                <span class="text-zinc-400 italic">Nenhum (Visitante ou Processamento Automático)</span>
                            @endif
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase tracking-wider">Cliente & Rede</span>
                        <p class="font-mono text-zinc-800 dark:text-zinc-200">
                            IP: <strong>{{ $selectedError->ip_address ?: '127.0.0.1' }}</strong>
                        </p>
                        @if($selectedError->user_agent)
                            <p class="text-[10px] text-zinc-400 font-mono truncate" title="{{ $selectedError->user_agent }}">
                                {{ $selectedError->user_agent }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Contexto JSON -->
                @if(! empty($selectedError->context))
                    <div>
                        <flux:heading size="sm" class="font-bold mb-2">Contexto e Parâmetros da Requisição</flux:heading>
                        <pre class="bg-zinc-950 text-emerald-400 p-4 rounded-xl text-xs overflow-x-auto max-h-48 font-mono border border-zinc-800">{{ json_encode($selectedError->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                <!-- Stack Trace -->
                @if($selectedError->stack_trace)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <flux:heading size="sm" class="font-bold">Rastreamento de Pilha (Stack Trace)</flux:heading>
                            <span class="text-[11px] text-zinc-400 font-mono">Linhas de execução</span>
                        </div>
                        <div class="bg-zinc-950 text-zinc-300 p-4 rounded-xl text-xs overflow-x-auto max-h-72 font-mono border border-zinc-800 whitespace-pre">
{{ $selectedError->stack_trace }}
                        </div>
                    </div>
                @endif

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button wire:click="deleteError({{ $selectedError->id }})" variant="danger" icon="trash">
                        Excluir Este Log
                    </flux:button>
                    <flux:button wire:click="$set('showErrorModal', false)" variant="ghost">
                        Fechar Janela
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
