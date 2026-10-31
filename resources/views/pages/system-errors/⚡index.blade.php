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

    public function with(): array
    {
        $query = SystemErrorLog::with('user')->latest();

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('message', 'like', "%{$this->search}%")
                    ->orWhere('exception_class', 'like', "%{$this->search}%")
                    ->orWhere('file', 'like', "%{$this->search}%")
                    ->orWhere('url', 'like', "%{$this->search}%");
            });
        }

        if (! empty($this->levelFilter)) {
            $query->where('level', $this->levelFilter);
        }

        return [
            'errors' => $query->paginate(20),
            'selectedError' => $this->selectedErrorId ? SystemErrorLog::with('user')->find($this->selectedErrorId) : null,
            'totalErrors' => SystemErrorLog::count(),
            'todayErrors' => SystemErrorLog::whereDate('created_at', today())->count(),
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

        if ($this->selectedErrorId === $errorId) {
            $this->showErrorModal = false;
            $this->selectedErrorId = null;
        }

        Flux::toast(heading: 'Sucesso', text: 'Registro de erro removido.');
    }

    public function clearAllErrors(): void
    {
        SystemErrorLog::truncate();
        $this->resetPage();
        $this->selectedErrorId = null;
        $this->showErrorModal = false;

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
            <flux:subheading>Monitore, depure e investigue exceções não tratadas, falhas de APIs e logs de erro em tempo real.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="clearAllErrors" wire:confirm="Tem certeza que deseja apagar TODOS os registros de erro?"
                variant="danger" icon="trash">
                Limpar Todos os Erros
            </flux:button>
        </div>
    </div>

    <!-- CARDS DE MÉTRICAS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <flux:card class="flex items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="p-3 bg-rose-50 dark:bg-rose-950/30 text-rose-600 rounded-xl">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
            </div>
            <div>
                <span class="text-xs text-zinc-500 font-medium">Erros Registrados Hoje</span>
                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $todayErrors }}</div>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="p-3 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 rounded-xl">
                <flux:icon.archive-box class="w-6 h-6" />
            </div>
            <div>
                <span class="text-xs text-zinc-500 font-medium">Total de Erros Armazenados</span>
                <div class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $totalErrors }}</div>
            </div>
        </flux:card>
    </div>

    <!-- FILTROS -->
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por mensagem, classe de exceção, arquivo ou URL..." icon="magnifying-glass" />
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="levelFilter" placeholder="Todos os Níveis">
                <flux:select.option value="">Todos os Níveis</flux:select.option>
                <flux:select.option value="error">Error</flux:select.option>
                <flux:select.option value="critical">Critical</flux:select.option>
                <flux:select.option value="alert">Alert</flux:select.option>
                <flux:select.option value="emergency">Emergency</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- TABELA DE ERROS -->
    <flux:card class="overflow-hidden p-0 border border-zinc-200 dark:border-zinc-800">
        <div class="overflow-x-auto w-full scrollbar-none">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Data / Hora</flux:table.column>
                    <flux:table.column>Nível</flux:table.column>
                    <flux:table.column>Exceção / Mensagem</flux:table.column>
                    <flux:table.column>Origem / Rota</flux:table.column>
                    <flux:table.column>Usuário</flux:table.column>
                    <flux:table.column align="end">Ações</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($errors as $error)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-nowrap text-xs text-zinc-500 font-mono">
                                {{ $error->created_at->format('d/m/Y H:i:s') }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $badgeColor = match(strtolower($error->level)) {
                                        'critical', 'emergency', 'alert' => 'red',
                                        'warning' => 'amber',
                                        default => 'rose',
                                    };
                                @endphp
                                <flux:badge color="{{ $badgeColor }}" size="sm" class="uppercase font-bold text-[10px]">
                                    {{ $error->level }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="max-w-md">
                                <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ class_basename($error->exception_class ?? 'Erro') }}: {{ $error->message }}
                                </div>
                                @if($error->file)
                                    <div class="text-[11px] text-zinc-400 font-mono truncate mt-0.5">
                                        {{ basename($error->file) }}:{{ $error->line }}
                                    </div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                @if($error->url)
                                    <span class="font-mono text-[11px]">{{ $error->method }} {{ parse_url($error->url, PHP_URL_PATH) }}</span>
                                @else
                                    <span class="text-zinc-400 text-xs">Background / CLI</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-xs">
                                @if($error->user)
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $error->user->name }}</span>
                                    <span class="block text-[10px] text-zinc-400 font-mono">{{ $error->user->email }}</span>
                                @else
                                    <span class="text-zinc-400 text-xs italic">Visitante / Sistema</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="viewDetails({{ $error->id }})" title="Ver Detalhes" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteError({{ $error->id }})" class="text-rose-600 hover:text-rose-700" title="Excluir" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-12 text-zinc-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon.check-circle class="w-10 h-10 text-emerald-500 opacity-60" />
                                    <span class="text-sm font-semibold">Nenhum erro registrado no sistema!</span>
                                    <span class="text-xs text-zinc-400">Todos os módulos e requisições estão operando com sucesso.</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if($errors->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $errors->links() }}
            </div>
        @endif
    </flux:card>

    <!-- MODAL DE DETALHES DO ERRO -->
    <flux:modal wire:model="showErrorModal" class="md:w-[750px]">
        @if ($selectedError)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge color="rose" class="uppercase font-bold text-[10px]">{{ $selectedError->level }}</flux:badge>
                            <span class="text-xs text-zinc-400 font-mono">{{ $selectedError->created_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                        <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-zinc-100">
                            {{ $selectedError->exception_class ?? 'Erro do Sistema' }}
                        </flux:heading>
                    </div>
                </div>

                <!-- Mensagem Principal -->
                <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/50">
                    <span class="text-xs font-bold text-rose-700 dark:text-rose-300 block mb-1">MENSAGEM:</span>
                    <p class="text-sm font-semibold text-rose-950 dark:text-rose-100 font-mono whitespace-pre-wrap break-all">{{ $selectedError->message }}</p>
                </div>

                <!-- Detalhes do Ambiente / Requisição -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase">Arquivo & Linha</span>
                        <p class="font-mono break-all text-zinc-800 dark:text-zinc-200">{{ $selectedError->file }}:{{ $selectedError->line }}</p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase">Rota / URL</span>
                        <p class="font-mono break-all text-zinc-800 dark:text-zinc-200">
                            {{ $selectedError->method }} {{ $selectedError->url ?: 'Execução em Console / Fila' }}
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase">Usuário Autenticado</span>
                        <p class="text-zinc-800 dark:text-zinc-200">
                            {{ $selectedError->user ? $selectedError->user->name . ' (' . $selectedError->user->email . ')' : 'Nenhum (Visitante ou Job)' }}
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-1">
                        <span class="text-zinc-400 font-bold block text-[10px] uppercase">IP / User-Agent</span>
                        <p class="font-mono text-zinc-800 dark:text-zinc-200">
                            {{ $selectedError->ip_address ?: '-' }}
                        </p>
                    </div>
                </div>

                <!-- Contexto Opcional -->
                @if(! empty($selectedError->context))
                    <div>
                        <flux:heading size="sm" class="font-bold mb-2">Contexto Adicional</flux:heading>
                        <pre class="bg-zinc-950 text-emerald-400 p-4 rounded-xl text-xs overflow-x-auto max-h-48 font-mono border border-zinc-800">{{ json_encode($selectedError->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                <!-- Stack Trace -->
                @if($selectedError->stack_trace)
                    <div>
                        <flux:heading size="sm" class="font-bold mb-2">Pilha de Chamadas (Stack Trace)</flux:heading>
                        <div class="bg-zinc-950 text-zinc-300 p-4 rounded-xl text-xs overflow-x-auto max-h-64 font-mono border border-zinc-800 whitespace-pre">
{{ $selectedError->stack_trace }}
                        </div>
                    </div>
                @endif

                <!-- Footer -->
                <div class="flex justify-between items-center pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button wire:click="deleteError({{ $selectedError->id }})" variant="danger" icon="trash">
                        Excluir Este Erro
                    </flux:button>
                    <flux:button wire:click="$set('showErrorModal', false)" variant="ghost">
                        Fechar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
