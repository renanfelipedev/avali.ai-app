<?php

use App\Models\ExamEvaluation;
use App\Models\ExamGenerationRequest;
use Livewire\Component;

new class extends Component
{
    public $activeGenerationIds = [];
    public $activeGradingIds = [];

    public function mount()
    {
        $this->activeGenerationIds = ExamGenerationRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->pluck('id')
            ->toArray();

        $this->activeGradingIds = ExamEvaluation::where('user_id', auth()->id())
            ->where('status', 'processing')
            ->pluck('id')
            ->toArray();
    }

    public function checkTasks()
    {
        $currentGenerations = ExamGenerationRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->get();
        $currentGenIds = $currentGenerations->pluck('id')->toArray();

        $currentGrading = ExamEvaluation::where('user_id', auth()->id())
            ->where('status', 'processing')
            ->get();
        $currentGradingIds = $currentGrading->pluck('id')->toArray();

        // Check finished generations
        $finishedGenIds = array_diff($this->activeGenerationIds, $currentGenIds);
        foreach ($finishedGenIds as $id) {
            $req = ExamGenerationRequest::find($id);
            if ($req) {
                if ($req->status === 'completed') {
                    Flux::toast(
                        text: "A prova '" . ($req->title ?: 'Sem título') . "' foi gerada com sucesso!",
                        heading: 'Geração Concluída',
                        variant: 'success'
                    );
                } elseif ($req->status === 'error') {
                    Flux::toast(
                        text: "Erro ao gerar prova: " . ($req->error_message ?: 'Desconhecido'),
                        heading: 'Geração Falhou',
                        variant: 'danger'
                    );
                }
            }
        }

        // Check finished grading
        $finishedGradingIds = array_diff($this->activeGradingIds, $currentGradingIds);
        foreach ($finishedGradingIds as $id) {
            $eval = ExamEvaluation::find($id);
            if ($eval) {
                if ($eval->status === 'completed') {
                    Flux::toast(
                        text: "A correção da prova '" . $eval->title . "' foi concluída!",
                        heading: 'Correção Finalizada',
                        variant: 'success'
                    );
                } elseif ($eval->status === 'error') {
                    Flux::toast(
                        text: "Erro na correção da prova '" . $eval->title . "'",
                        heading: 'Correção Falhou',
                        variant: 'danger'
                    );
                }
            }
        }

        $this->activeGenerationIds = $currentGenIds;
        $this->activeGradingIds = $currentGradingIds;

        return [
            'generations' => $currentGenerations,
            'grading' => $currentGrading,
            'total' => $currentGenerations->count() + $currentGrading->count()
        ];
    }

    public function getFinishedRecently()
    {
        $generations = ExamGenerationRequest::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->get();

        $grading = ExamEvaluation::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->get();

        return [
            'generations' => $generations,
            'grading' => $grading,
            'total' => $generations->count() + $grading->count()
        ];
    }
};
?>

<div wire:poll.5s class="flex items-center gap-4">
    @php 
        $active = $this->checkTasks(); 
        $finished = $this->getFinishedRecently();
    @endphp

    <flux:dropdown align="end" class="relative">
        <flux:button variant="ghost" icon="bell" class="relative">
            @if($active['total'] > 0)
                <span class="absolute top-2 right-2 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
            @elseif($finished['total'] > 0)
                <span class="absolute top-2 right-2 flex h-2 w-2">
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 p-0 overflow-hidden">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50">
                <flux:heading size="sm">Central de Notificações</flux:heading>
            </div>

            <div class="max-h-96 overflow-y-auto">
                @if($active['total'] === 0 && $finished['total'] === 0)
                    <div class="p-8 text-center text-zinc-500 text-sm">
                        Nenhuma atividade recente.
                    </div>
                @endif

                <!-- Tarefas Ativas -->
                @if($active['total'] > 0)
                    <div class="p-2 bg-amber-50/50 dark:bg-amber-900/10">
                        <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-600">Em Processamento</div>
                        @foreach($active['generations'] as $task)
                            <flux:menu.item href="{{ route('tasks.index') }}" class="gap-3">
                                <flux:icon.arrow-path class="animate-spin size-4 text-amber-500" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">Gerando Prova</div>
                                    <div class="text-xs text-zinc-500 truncate">{{ implode(', ', (array)$task->topics) }}</div>
                                </div>
                            </flux:menu.item>
                        @endforeach
                        @foreach($active['grading'] as $task)
                            <flux:menu.item href="{{ route('tasks.index') }}" class="gap-3">
                                <flux:icon.arrow-path class="animate-spin size-4 text-amber-500" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">Corrigindo Provas</div>
                                    <div class="text-xs text-zinc-500 truncate">{{ $task->title }}</div>
                                </div>
                            </flux:menu.item>
                        @endforeach
                    </div>
                @endif

                <!-- Tarefas Finalizadas Recentemente -->
                @if($finished['total'] > 0)
                    <div class="p-2">
                        <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-600">Concluído Recentemente</div>
                        @foreach($finished['generations'] as $task)
                            <flux:menu.item href="{{ route('exams.index') }}" class="gap-3">
                                <flux:icon.check-circle class="size-4 text-emerald-500" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">Prova Concluída</div>
                                    <div class="text-xs text-zinc-500 truncate">Clique para visualizar</div>
                                </div>
                            </flux:menu.item>
                        @endforeach
                        @foreach($finished['grading'] as $task)
                            <flux:menu.item href="{{ route('evaluations.show', $task) }}" class="gap-3">
                                <flux:icon.check-circle class="size-4 text-emerald-500" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">Correção Finalizada</div>
                                    <div class="text-xs text-zinc-500 truncate">{{ $task->title }}</div>
                                </div>
                            </flux:menu.item>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-2 border-t border-zinc-200 dark:border-zinc-800 text-center">
                <flux:button variant="ghost" size="sm" href="{{ route('tasks.index') }}" class="w-full text-xs">Ver Todas as Tarefas</flux:button>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>