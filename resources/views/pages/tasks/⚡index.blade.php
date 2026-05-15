<?php

use App\Models\ExamEvaluation;
use App\Models\ExamGenerationRequest;
use App\Models\ExamSubmission;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    public function mount()
    {
        $this->cleanupStuckTasks();
    }

    protected function cleanupStuckTasks()
    {
        $timeout = now()->subMinutes(15);

        // Cleanup Generation Requests
        ExamGenerationRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', $timeout)
            ->update([
                'status' => 'error',
                'error_message' => 'Tempo limite excedido (15 min). A solicitação foi cancelada automaticamente.'
            ]);

        // Cleanup Submissions
        ExamSubmission::whereHas('evaluation', function ($query) {
            $query->where('user_id', auth()->id());
        })
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', $timeout)
            ->update([
                'status' => 'error',
                'error_message' => 'Tempo limite excedido (15 min). A correção foi cancelada automaticamente.'
            ]);
            
        // Sync Evaluation status if all submissions are processed
        $evaluations = ExamEvaluation::where('user_id', auth()->id())
            ->where('status', 'processing')
            ->get();

        foreach ($evaluations as $evaluation) {
            $total = $evaluation->submissions()->count();
            $processed = $evaluation->submissions()->whereIn('status', ['completed', 'error'])->count();
            if ($total > 0 && $total === $processed) {
                $evaluation->update(['status' => 'completed']);
            }
        }
    }
    public function getGenerationTasks()
    {
        return ExamGenerationRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->get();
    }

    public function getGradingTasks()
    {
        return ExamEvaluation::where('user_id', auth()->id())
            ->where('status', 'processing')
            ->withCount('submissions')
            ->latest()
            ->get();
    }

    public function getSubmissionProgress($evaluationId)
    {
        $total = ExamSubmission::where('exam_evaluation_id', $evaluationId)->count();
        $completed = ExamSubmission::where('exam_evaluation_id', $evaluationId)
            ->whereIn('status', ['completed', 'error'])
            ->count();
        
        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0
        ];
    }
};
?>

<div wire:poll.3s>
    <div class="mb-6">
        <flux:heading size="xl">Processamento em Segundo Plano</flux:heading>
        <flux:subheading>Acompanhe o andamento das suas gerações e correções via IA.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-8">
        <!-- Geração de Provas -->
        <flux:card>
            <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                <flux:icon.document-duplicate class="size-5" />
                Geração de Provas
            </flux:heading>

            @php $genTasks = $this->getGenerationTasks(); @endphp

            @if($genTasks->isEmpty())
                <div class="py-8 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-800">
                    Nenhuma prova sendo gerada no momento.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($genTasks as $task)
                        <div class="p-4 border border-zinc-200 dark:border-zinc-800 rounded-lg flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="bg-indigo-100 dark:bg-indigo-900/30 p-2 rounded-lg">
                                    <flux:icon.arrow-path class="animate-spin size-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div>
                                    <div class="font-medium">Gerando prova: {{ Str::limit(implode(', ', (array)$task->topics), 40) }}</div>
                                    <div class="text-xs text-zinc-500 uppercase tracking-widest">{{ $task->questions_count }} questões solicitações</div>
                                </div>
                            </div>
                            <div>
                                <flux:badge color="zinc" size="sm" class="animate-pulse">IA Processando...</flux:badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <!-- Correção de Provas -->
        <flux:card>
            <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                <flux:icon.check-badge class="size-5" />
                Correção de Provas (Grading)
            </flux:heading>

            @php $gradingTasks = $this->getGradingTasks(); @endphp

            @if($gradingTasks->isEmpty())
                <div class="py-8 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-800">
                    Nenhuma correção sendo efetuada no momento.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($gradingTasks as $task)
                        @php $progress = $this->getSubmissionProgress($task->id); @endphp
                        <div class="p-4 border border-zinc-200 dark:border-zinc-800 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <div class="bg-emerald-100 dark:bg-emerald-900/30 p-2 rounded-lg">
                                        <flux:icon.arrow-path class="animate-spin size-5 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $task->title }}</div>
                                        <div class="text-xs text-zinc-500 uppercase tracking-widest">{{ $progress['completed'] }} de {{ $progress['total'] }} alunos corrigidos</div>
                                    </div>
                                </div>
                                <flux:button href="{{ route('evaluations.show', $task) }}" size="sm" variant="ghost">Ver Detalhes</flux:button>
                            </div>
                            
                            <!-- Barra de Progresso Simples -->
                            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                                <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $progress['percentage'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    <div class="mt-8 text-center">
        <flux:subheading>Você pode sair desta página. O processamento continuará em segundo plano.</flux:subheading>
    </div>
</div>