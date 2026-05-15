<?php

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Jobs\EvaluateSubmissionJob;
use App\Traits\HasOwnership;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component {
    use HasOwnership, WithFileUploads;

    public ExamEvaluation $evaluation;
    public $new_file;
    public $viewing_file_url = null;
    public $viewing_student_name = null;
    public ?ExamSubmission $viewing_submission = null;

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

    public function replaceFile($submissionId)
    {
        if (!$this->new_file) {
            $this->addError('new_file', 'Selecione um arquivo primeiro.');
            return;
        }

        $submission = ExamSubmission::findOrFail($submissionId);
        $this->authorizeOwnership($submission->evaluation);

        $this->validate(['new_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,docx,doc,txt|max:10240']);

        // Store and Convert
        $path = $this->new_file->store('evaluations/submissions', 'public');
        $converter = app(PdfConverterService::class);
        $pdfPath = $converter->convertToPdf($path);

        // Update and Requeue
        $submission->update([
            'student_file_path' => $pdfPath,
            'status' => 'pending',
            'error_message' => null,
            'status_message' => 'Arquivo substituído, aguardando nova correção...',
        ]);

        EvaluateSubmissionJob::dispatch($this->evaluation, $submission);

        $this->evaluation->update(['status' => 'processing']);
        $this->new_file = null;

        $this->modal('replace-modal-' . $submissionId)->close();

        session()->flash('status', 'Arquivo substituído e correção reiniciada.');
        $this->loadSubmissions();
    }

    protected function loadSubmissions(): void
    {
        $this->evaluation->load([
            'submissions' => function ($query) {
                $query->orderBy('student_name', 'asc');
            },
        ]);
    }

    public function previewFile($submissionId)
    {
        $submission = ExamSubmission::findOrFail($submissionId);
        $this->authorizeOwnership($submission->evaluation);
        
        $this->viewing_file_url = Storage::url($submission->student_file_path);
        $this->viewing_student_name = $submission->student_name;
        
        $this->modal('preview-file-modal')->show();
    }

    public function showFeedback($submissionId)
    {
        $this->viewing_submission = ExamSubmission::findOrFail($submissionId);
        $this->authorizeOwnership($this->viewing_submission->evaluation);
        
        $this->modal('feedback-shared-modal')->show();
    }

    protected function requeueSubmission(ExamSubmission $submission): void
    {
        $submission->update(['status' => 'pending', 'error_message' => null]);
        EvaluateSubmissionJob::dispatch($this->evaluation, $submission);
    }
};
?>

<div @if ($evaluation->status === 'processing') wire:poll.3s="refreshSubmissions" @endif>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $evaluation->title }}</flux:heading>
            <flux:subheading>Criado em {{ $evaluation->created_at->format('d/m/Y H:i') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            @if ($evaluation->submissions->where('status', 'error')->count() > 0)
                <flux:button wire:click="retryErrors" variant="primary" icon="arrow-path">Recorrigir Erros</flux:button>
            @endif
            <flux:button href="{{ route('evaluations.index') }}" variant="ghost" icon="arrow-left">Voltar</flux:button>
        </div>
    </div>

    @if ($evaluation->status === 'processing')
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
                    <div class="text-sm font-bold text-indigo-700 dark:text-indigo-300">{{ $done }} /
                        {{ $total }}</div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Concluídos</div>
                </div>
            </div>

            <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                <div class="bg-indigo-600 h-2 transition-all duration-500 ease-out" style="width: {{ $progress }}%">
                </div>
            </div>

            <div class="flex justify-between text-[10px] font-bold uppercase tracking-widest">
                <div class="text-green-600">Sucesso: {{ $completed }}</div>
                <div class="text-indigo-500 animate-pulse">Em andamento: {{ $processing }}</div>
                <div class="text-red-600">Erros: {{ $failed }}</div>
                <div class="text-zinc-500">Pendentes: {{ $total - ($done + $processing) }}</div>
            </div>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <flux:card class="md:col-span-2">
            <flux:heading size="lg" class="mb-4">Documentos de Referência</flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Gabarito -->
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <div class="flex items-center gap-3 mb-3">
                        <div
                            class="p-2 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
                            <flux:icon.check-badge class="w-5 h-5" />
                        </div>
                        <flux:heading size="sm">Gabarito</flux:heading>
                    </div>

                    @if ($evaluation->answer_key_file_path)
                        <flux:button href="{{ Storage::url($evaluation->answer_key_file_path) }}" target="_blank"
                            size="sm" variant="filled" icon="eye" class="w-full">Visualizar Gabarito
                        </flux:button>
                    @else
                        <div class="text-xs text-zinc-500 italic px-2">Nenhum gabarito enviado.</div>
                    @endif
                </div>

                <!-- Prova Original -->
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            <flux:icon.document-text class="w-5 h-5" />
                        </div>
                        <flux:heading size="sm">Prova Original</flux:heading>
                    </div>

                    @if ($evaluation->exam_file_path)
                        <flux:button href="{{ Storage::url($evaluation->exam_file_path) }}" target="_blank"
                            size="sm" variant="filled" icon="eye" class="w-full">Visualizar Prova</flux:button>
                    @else
                        <div class="text-xs text-zinc-500 italic px-2">Prova original não enviada.</div>
                    @endif
                </div>
            </div>

            @if ($evaluation->grading_criteria)
                <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:heading size="sm" class="mb-2">Critérios Adicionais:</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 italic">"{{ $evaluation->grading_criteria }}"
                    </p>
                </div>
            @endif
        </flux:card>

        <flux:card class="flex flex-col justify-between">
            <div>
                <flux:heading size="lg" class="mb-4">Resumo da Turma</flux:heading>
                <div class="space-y-4">
                    <div class="flex justify-between items-end">
                        <span class="text-sm text-zinc-500">Total de Alunos:</span>
                        <span class="text-xl font-bold">{{ $evaluation->submissions->count() }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-sm text-zinc-500">Média Geral:</span>
                        <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                            {{ number_format($evaluation->submissions->where('status', 'completed')->avg('final_grade') ?? 0, 1) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-800 flex justify-center">
                <flux:badge color="zinc" size="sm">ID: #{{ $evaluation->id }}</flux:badge>
            </div>
        </flux:card>
    </div>

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
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $submission->student_name ?: 'Aguardando processamento...' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($submission->status === 'completed')
                                <flux:badge color="green" size="sm">Concluído</flux:badge>
                            @elseif($submission->status === 'error')
                                <flux:badge color="red" size="sm">Erro</flux:badge>
                            @elseif($submission->status === 'processing')
                                <div class="flex flex-col gap-1">
                                    <flux:badge color="indigo" size="sm" class="animate-pulse">Corrigindo
                                    </flux:badge>
                                    <span
                                        class="text-[10px] text-zinc-500 italic">{{ $submission->status_message }}</span>
                                </div>
                            @else
                                <flux:badge color="zinc" size="sm">Pendente</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div
                                class="font-semibold {{ $submission->final_grade >= 6 ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500' }}">
                                {{ $submission->final_grade !== null ? number_format($submission->final_grade, 2, ',', '.') : '-' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if ($submission->status !== 'completed')
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="sm"
                                        variant="ghost" icon="arrow-path" tooltip="Tentar novamente" />
                                @endif

                                @if ($submission->status === 'error')
                                    <flux:modal.trigger name="replace-modal-{{ $submission->id }}">
                                        <flux:button size="sm" variant="ghost" icon="cloud-arrow-up"
                                            color="warning" tooltip="Substituir arquivo corrompido" />
                                    </flux:modal.trigger>

                                    <flux:modal name="replace-modal-{{ $submission->id }}" class="md:w-1/3">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">Substituir Arquivo</flux:heading>
                                                <flux:subheading>Envie uma nova versão da prova do aluno para reiniciar
                                                    a correção.</flux:subheading>
                                            </div>

                                            <flux:input type="file" wire:model="new_file"
                                                label="Nova Prova (PDF/Imagem/Word)" />

                                            <div class="flex justify-end gap-2">
                                                <flux:modal.close>
                                                    <flux:button variant="ghost">Cancelar</flux:button>
                                                </flux:modal.close>

                                                <flux:button wire:click="replaceFile({{ $submission->id }})"
                                                    variant="primary" wire:loading.attr="disabled" icon="arrow-path">
                                                    Substituir e Corrigir
                                                </flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @endif

                                @if ($submission->status === 'completed')
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="sm"
                                        variant="ghost" icon="arrow-path" tooltip="Refazer correção" />
                                @endif

                                <flux:button wire:click="showFeedback({{ $submission->id }})" size="sm" variant="ghost" icon="document-text">Ver Feedback
                                </flux:button>
                                <flux:button wire:click="previewFile({{ $submission->id }})" size="sm" variant="ghost" icon="eye" tooltip="Visualizar Prova Original" />

                                <flux:button href="{{ Storage::url($submission->student_file_path) }}"
                                    target="_blank" size="sm" variant="ghost" icon="arrow-down-tray"
                                    tooltip="Baixar Arquivo" />
                            </div>

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

    <!-- Modal Compartilhado: Feedback Detalhado -->
    <flux:modal name="feedback-shared-modal" class="md:w-3/4 max-w-4xl">
        <div class="space-y-6">
            @if($viewing_submission)
                <div class="flex justify-between items-start">
                    <div>
                        <flux:heading size="lg">Feedback da Correção</flux:heading>
                        <flux:subheading>Aluno: {{ $viewing_submission->student_name }} - Nota Final: {{ $viewing_submission->final_grade }}</flux:subheading>
                    </div>
                    @if ($viewing_submission->status === 'error')
                        <flux:button wire:click="retrySubmission({{ $viewing_submission->id }})" variant="primary" size="sm" icon="arrow-path">Tentar Novamente</flux:button>
                    @endif
                </div>

                @if ($viewing_submission->status === 'error')
                    <div class="space-y-4">
                        <flux:card class="border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/10 text-red-600 dark:text-red-500">
                            <div class="flex items-center gap-2 font-bold mb-2 text-lg">
                                <flux:icon.exclamation-triangle class="w-5 h-5" />
                                Erro no Processamento
                            </div>
                            <div class="text-sm font-mono bg-white/50 dark:bg-black/20 p-4 rounded-lg border border-red-100 dark:border-red-800">
                                {{ $viewing_submission->error_message }}
                            </div>
                        </flux:card>
                    </div>
                @endif

                @if ($viewing_submission->feedback_data && is_array($viewing_submission->feedback_data))
                    <div class="space-y-4">
                        @foreach ($viewing_submission->feedback_data as $q)
                            <flux:card class="space-y-4">
                                <div class="flex justify-between items-start">
                                    <flux:heading size="md">Questão {{ $q['question_number'] ?? 'N/A' }}</flux:heading>
                                    <flux:badge color="zinc" size="sm">Nota: {{ $q['grade'] ?? 0 }}</flux:badge>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-zinc-500 mb-1">Feedback da IA:</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ trim($q['feedback'] ?? 'Sem feedback fornecido.') }}</p>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex items-center justify-center py-12">
                    <flux:badge color="zinc" class="animate-pulse">Carregando feedback...</flux:badge>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Modal Compartilhado: Visualização da Prova -->
    <flux:modal name="preview-file-modal" class="w-[95vw] h-[95vh] max-w-none">
        <div class="h-full flex flex-col space-y-4">
            <div class="flex justify-between items-center px-2">
                <flux:heading size="lg">Visualizando Prova: {{ $viewing_student_name }}</flux:heading>
                <div class="flex gap-2">
                    @if($viewing_file_url)
                        <flux:button href="{{ $viewing_file_url }}" download variant="filled" size="sm" icon="arrow-down-tray">Baixar PDF</flux:button>
                    @endif
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm" icon="x-mark">Fechar</flux:button>
                    </flux:modal.close>
                </div>
            </div>
            
            <div class="flex-1 bg-zinc-100 dark:bg-zinc-900 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-inner">
                @if($viewing_file_url)
                    <iframe src="{{ $viewing_file_url }}" class="w-full h-full" frameborder="0"></iframe>
                @else
                    <div class="flex items-center justify-center h-full">
                        <flux:badge color="zinc" class="animate-pulse">Carregando arquivo...</flux:badge>
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
