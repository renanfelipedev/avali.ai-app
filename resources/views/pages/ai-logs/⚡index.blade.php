<?php

use App\Models\AiLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component
{
    use WithPagination;

    public $selectedLog = null;
    public $showLogModal = false;

    public function with(): array
    {
        return [
            'logs' => AiLog::latest()->paginate(15),
        ];
    }

    public function viewDetails(AiLog $log)
    {
        $this->selectedLog = $log;
        $this->showLogModal = true;
    }

    public function clearLogs()
    {
        AiLog::truncate();
        $this->resetPage();
        $this->selectedLog = null;
        $this->showLogModal = false;
        session()->flash('status', 'Todos os logs foram apagados com sucesso.');
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">Logs da Inteligência Artificial</flux:heading>
            <flux:subheading>Acompanhe e diagnostique falhas de comunicação com a API do Gemini.</flux:subheading>
        </div>
        <flux:button wire:click="clearLogs" wire:confirm="Tem certeza que deseja apagar todos os logs do banco?" variant="danger" icon="trash">Limpar Logs</flux:button>
    </div>

    @if (session('status'))
        <div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Módulo</flux:table.column>
                <flux:table.column>Mensagem de Erro</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($logs as $log)
                    <flux:table.row>
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm">{{ $log->module }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md truncate text-red-600 dark:text-red-400">
                            {{ Str::limit(explode("\n", $log->error_message)[0], 80) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" wire:click="viewDetails({{ $log->id }})">Ver Detalhes</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                            <flux:icon.check-circle class="w-8 h-8 mx-auto text-green-500 mb-2" />
                            Nenhum erro registrado! A comunicação com a IA está funcionando perfeitamente.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </flux:card>

    <flux:modal wire:model="showLogModal" class="min-w-[600px] max-w-4xl">
        <div class="space-y-6">
            @if($selectedLog)
                <div>
                    <flux:heading size="lg">Detalhes do Erro</flux:heading>
                    <flux:subheading>Módulo: {{ $selectedLog->module }} | Data: {{ $selectedLog->created_at->format('d/m/Y H:i:s') }}</flux:subheading>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <flux:heading size="sm" class="mb-2">Payload da Requisição</flux:heading>
                        <div class="bg-zinc-100 dark:bg-zinc-900 p-4 rounded-lg overflow-x-auto">
                            <pre class="text-xs text-zinc-800 dark:text-zinc-200 font-mono">{{ json_encode($selectedLog->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>

                    <div>
                        <flux:heading size="sm" class="mb-2">Stack Trace / Exceção</flux:heading>
                        <div class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 p-4 rounded-lg overflow-x-auto max-h-96 overflow-y-auto">
                            <pre class="text-xs text-red-800 dark:text-red-400 font-mono whitespace-pre-wrap">{{ $selectedLog->error_message }}</pre>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="$set('showLogModal', false)">Fechar</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
