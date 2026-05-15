<?php

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Jobs\EvaluateSubmissionJob;
use App\Traits\HasOwnership;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    use HasOwnership;

    public ExamEvaluation $evaluation;

    public function mount(ExamEvaluation $evaluation)
    {
        $this->authorizeOwnership($evaluation);

        $this->loadSubmissions();
    }

    public function refreshSubmissions()
    {
        $this->loadSubmissions();

        $total = $this->evaluation->submissions->count();
        $completed = $this->evaluation->submissions->whereIn('status', ['completed', 'error'])->count();

        if ($total > 0 && $total === $completed && $this->evaluation->status === 'processing') {
            $this->evaluation->update(['status' => 'completed']);
        }
    }

    public function retryErrors()
    {
        $this->authorizeOwnership($this->evaluation);

        $failedSubmissions = $this->evaluation->submissions()->where('status', 'error')->get();

        foreach ($failedSubmissions as $submission) {
            $this->requeueSubmission($submission);
        }

        $this->evaluation->update(['status' => 'processing']);
        $this->loadSubmissions();
    }

    public function retrySubmission($submissionId)
    {
        $submission = ExamSubmission::findOrFail($submissionId);
        $this->authorizeOwnership($submission->evaluation);

        $this->requeueSubmission($submission);
        
        $this->evaluation->update(['status' => 'processing']);
        $this->loadSubmissions();
    }

    protected function loadSubmissions(): void
    {
        $this->evaluation->load(['submissions' => function ($query) {
            $query->orderBy('student_name', 'asc')->orderBy('id', 'asc');
        }]);
    }

    protected function requeueSubmission(ExamSubmission $submission): void
    {
        $submission->update(['status' => 'pending', 'error_message' => null]);
        EvaluateSubmissionJob::dispatch($this->evaluation, $submission);
    }
};
?>

<div @if($evaluation->status === 'processing') wire:poll.3s="refreshSubmissions" @endif>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $evaluation->title }}</flux:heading>
            <flux:subheading>Criado em {{ $evaluation->created_at->format('d/m/Y H:i') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            @if($evaluation->submissions->where('status', 'error')->count() > 0)
                <flux:button wire:click="retryErrors" variant="primary" icon="arrow-path">Recorrigir Erros</flux:button>
            @endif
            <flux:button href="{{ route('evaluations.index') }}" variant="ghost" icon="arrow-left">Voltar</flux:button>
        </div>
    </div>

    @if($evaluation->status === 'processing')
        @php
            $total = $evaluation->submissions->count();
            $completed = $evaluation->submissions->where('status', 'completed')->count();
            $failed = $evaluation->submissions->where('status', 'error')->count();
            $processing = $evaluation->submissions->where('status', 'processing')->count();
            $done = $completed + $failed;
            $progress = $total > 0 ? ($done / $total) * 100 : 0;
        @endphp

        <flux:card class="mb-6 space-y-4 border-indigo-200 bg-indigo-50 dark:border-indigo-900/50 dark:bg-indigo-900/10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 text-indigo-600 dark:text-indigo-400">
                    <flux:icon.arrow-path class="animate-spin h-5 w-5" />
                    <div>
                        <div class="font-bold">Corrigindo provas com Inteligência Artificial...</div>
                        <div class="text-xs">Os resultados aparecerão na lista abaixo conforme forem finalizados.</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-indigo-700 dark:text-indigo-300">{{ $done }} / {{ $total }}</div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Concluídos</div>
                </div>
            </div>

            <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                <div class="bg-indigo-600 h-2 transition-all duration-500 ease-out" style="width: {{ $progress }}%"></div>
            </div>

            <div class="flex justify-between text-[10px] font-bold uppercase tracking-widest">
                <div class="text-green-600">Sucesso: {{ $completed }}</div>
                <div class="text-indigo-500 animate-pulse">Em andamento: {{ $processing }}</div>
                <div class="text-red-600">Erros: {{ $failed }}</div>
                <div class="text-zinc-500">Pendentes: {{ $total - ($done + $processing) }}</div>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">Resultados dos Alunos</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Aluno</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Nota Final</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($evaluation->submissions as $submission)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $submission->student_name ?: 'Aguardando processamento...' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($submission->status === 'completed')
                                <flux:badge color="success" size="sm">Concluído</flux:badge>
                            @elseif($submission->status === 'error')
                                <flux:badge color="danger" size="sm">Erro</flux:badge>
                            @elseif($submission->status === 'processing')
                                <div class="flex flex-col gap-1">
                                    <flux:badge color="indigo" size="sm" class="animate-pulse">Corrigindo</flux:badge>
                                    <span class="text-[10px] text-zinc-500 italic">{{ $submission->status_message }}</span>
                                </div>
                            @else
                                <flux:badge color="zinc" size="sm">Pendente</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-semibold {{ $submission->final_grade >= 6 ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500' }}">
                                {{ $submission->final_grade !== null ? number_format($submission->final_grade, 2, ',', '.') : '-' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if($submission->status !== 'completed')
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="sm" variant="ghost" icon="arrow-path" tooltip="Forçar correção desta prova" />
                                @endif
                                
                                @if($submission->status === 'completed')
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="sm" variant="ghost" icon="arrow-path" tooltip="Refazer correção" />
                                @endif

                                <flux:modal.trigger name="feedback-modal-{{ $submission->id }}">
                                    <flux:button size="sm" variant="ghost" icon="document-text">Ver Feedback</flux:button>
                                </flux:modal.trigger>
                                <flux:button href="{{ Storage::url($submission->student_file_path) }}" target="_blank" size="sm" variant="ghost" icon="arrow-down-tray" />
                            </div>

                            <flux:modal name="feedback-modal-{{ $submission->id }}" class="md:w-3/4 max-w-4xl">
                                <div class="space-y-6">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <flux:heading size="lg">Feedback da Correção</flux:heading>
                                            <flux:subheading>Aluno: {{ $submission->student_name }} - Nota Final: {{ $submission->final_grade }}</flux:subheading>
                                        </div>
                                        @if($submission->status === 'error')
                                            <flux:button wire:click="retrySubmission({{ $submission->id }})" variant="primary" size="sm" icon="arrow-path">Tentar Novamente</flux:button>
                                        @endif
                                    </div>

                                    @if($submission->status === 'error')
                                        <flux:card class="border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/10 text-red-600 dark:text-red-500">
                                            <div class="font-bold mb-1">Erro no processamento:</div>
                                            {{ $submission->error_message }}
                                        </flux:card>
                                    @endif

                                    @if($submission->feedback_data && is_array($submission->feedback_data))
                                        <div class="space-y-4">
                                            @foreach($submission->feedback_data as $q)
                                                <flux:card class="space-y-4">
                                                    <div class="flex justify-between items-start">
                                                        <flux:heading size="md">Questão {{ $q['question_number'] ?? 'N/A' }}</flux:heading>
                                                        <flux:badge color="zinc" size="sm">Nota: {{ $q['grade'] ?? 0 }}</flux:badge>
                                                    </div>
                                                    
                                                    @if(isset($q['student_answer']))
                                                        <div>
                                                            <div class="text-xs font-bold uppercase text-zinc-500 mb-1">Resposta do Aluno:</div>
                                                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm italic text-zinc-700 dark:text-zinc-300">
                                                                "{{ $q['student_answer'] }}"
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <div>
                                                        <div class="text-xs font-bold uppercase text-zinc-500 mb-1">Feedback da IA:</div>
                                                        <p class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{{ $q['feedback'] ?? 'Sem feedback fornecido.' }}</p>
                                                    </div>
                                                </flux:card>
                                            @endforeach
                                        </div>
                                    @else
                                        @if($submission->status !== 'error')
                                            <div class="text-sm text-zinc-500">Nenhum feedback disponível.</div>
                                        @endif
                                    @endif
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                            Nenhuma submissão encontrada.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>