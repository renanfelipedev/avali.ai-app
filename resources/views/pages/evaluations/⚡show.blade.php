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
    public $search = '';

    public $isEditingFeedback = false;
    public $editing_feedback_data = [];
    public $editing_final_grade = 0;

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

    public function updatedSearch()
    {
        $this->loadSubmissions();
    }

    public function retryErrors()
    {
        $this->authorizeOwnership($this->evaluation);

        $failedSubmissions = $this->evaluation->submissions()->where('status', 'error')->get();

        foreach ($failedSubmissions as $submission) {
            $this->requeueSubmission($submission);
        }

        $this->evaluation->update(['status' => 'processing']);
        session()->flash('status', 'Correção reiniciada para os arquivos com falha.');
        $this->loadSubmissions();
    }

    public function retrySubmission($submissionId)
    {
        $submission = ExamSubmission::findOrFail($submissionId);
        $this->authorizeOwnership($submission->evaluation);

        $this->requeueSubmission($submission);

        $this->evaluation->update(['status' => 'processing']);
        session()->flash('status', 'Correção reiniciada para o aluno ' . $submission->student_name . '.');
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
                $query->when($this->search, function ($q) {
                    $q->where('student_name', 'like', '%' . $this->search . '%');
                })->orderBy('student_name', 'asc');
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

        $this->isEditingFeedback = false;
        $this->editing_feedback_data = $this->viewing_submission->feedback_data ?? [];
        $this->editing_final_grade = $this->viewing_submission->final_grade;

        $this->modal('feedback-shared-modal')->show();
    }

    public function recalculateFinalGrade()
    {
        $sum = 0;
        foreach ($this->editing_feedback_data as $q) {
            $sum += (float) ($q['grade'] ?? 0);
        }
        $this->editing_final_grade = $sum;
    }

    public function saveManualCorrection()
    {
        $this->authorizeOwnership($this->viewing_submission->evaluation);

        $this->viewing_submission->update([
            'final_grade' => $this->editing_final_grade,
            'feedback_data' => $this->editing_feedback_data,
        ]);

        $this->isEditingFeedback = false;
        session()->flash('status', 'Correção atualizada manualmente com sucesso!');
        $this->loadSubmissions();
    }

    protected function requeueSubmission(ExamSubmission $submission): void
    {
        $submission->update(['status' => 'pending', 'error_message' => null]);
        EvaluateSubmissionJob::dispatch($this->evaluation, $submission);
    }
};
?>

<div @if ($evaluation->status === 'processing') wire:poll.3s="refreshSubmissions" @endif>
    <!-- TOP HEADER -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" class="font-extrabold tracking-tight">{{ $evaluation->title }}</flux:heading>
                @if($evaluation->status === 'completed')
                    <flux:badge color="green" size="sm">Concluído</flux:badge>
                @elseif($evaluation->status === 'processing' || $evaluation->status === 'importing')
                    <flux:badge color="indigo" size="sm" class="animate-pulse">Em Andamento</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Pendente</flux:badge>
                @endif
            </div>
            <flux:subheading>Criado em {{ $evaluation->created_at->format('d/m/Y \à\s H:i') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @if ($evaluation->submissions->where('status', 'error')->count() > 0)
                <flux:button wire:click="retryErrors" variant="primary" icon="arrow-path">Recorrigir Erros</flux:button>
            @endif
            <flux:button href="{{ route('evaluations.index') }}" variant="ghost" icon="arrow-left">Voltar para a Lista</flux:button>
        </div>
    </div>

    <!-- GOOGLE CLASSROOM SYNC HEADER -->
    @if ($evaluation->google_course_id)
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm animate-fade-in">
            <div class="flex items-center gap-3.5">
                <div class="p-2.5 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                        Sincronizado com o Google Classroom
                        <flux:badge color="emerald" size="sm" class="animate-pulse">Ativo</flux:badge>
                    </div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400/90 font-medium">As notas finais e rascunhos são atualizados no Classroom no momento da correção.</div>
                </div>
            </div>
            <div class="text-xs font-mono text-emerald-700 dark:text-emerald-400 bg-white/60 dark:bg-black/30 px-3.5 py-1.5 rounded-xl border border-emerald-100 dark:border-emerald-900/50">
                Tarefa ID: {{ substr($evaluation->google_coursework_id, 0, 8) }}...
            </div>
        </div>
    @endif

    <!-- PROGRESS CARD (IF PROCESSING) -->
    @if ($evaluation->status === 'processing')
        @php
            $total = $evaluation->submissions->count();
            $completed = $evaluation->submissions->where('status', 'completed')->count();
            $failed = $evaluation->submissions->where('status', 'error')->count();
            $processing = $evaluation->submissions->where('status', 'processing')->count();
            $done = $completed + $failed;
            $progress = $total > 0 ? ($done / $total) * 100 : 0;
        @endphp

        <flux:card class="mb-6 space-y-4 shadow-inner">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:icon.arrow-path class="animate-spin h-5 w-5" />
                    <div>
                        <div class="font-bold">Corrigindo provas com Inteligência Artificial...</div>
                        <div class="text-xs">Os resultados aparecerão na lista abaixo conforme forem finalizados.</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold">{{ $done }} / {{ $total }}</div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Concluídos</div>
                </div>
            </div>

            <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                <div class="bg-indigo-600 h-2.5 transition-all duration-500 ease-out" style="width: {{ $progress }}%">
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

    <!-- PREMIUM METRIC CARDS -->
    @if ($evaluation->type === 'exam')
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
            <!-- Metric 1: Total Alunos -->
            <flux:card class="p-6 flex items-center gap-4 bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div class="p-3 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                    <flux:icon.users class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">{{ $evaluation->submissions->count() }}</div>
                    <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Total de Alunos</div>
                </div>
            </flux:card>

            <!-- Metric 2: Média Geral -->
            @php
                $completedSubmissions = $evaluation->submissions->where('status', 'completed');
                $avgGrade = $completedSubmissions->avg('final_grade') ?? 0;
                $avgColor = $avgGrade >= 7.0 ? 'text-zinc-900 dark:text-zinc-50' : 'text-zinc-900 dark:text-zinc-50';
            @endphp
            <flux:card class="p-6 flex items-center gap-4 bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div class="p-3 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                    <flux:icon.academic-cap class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-2xl font-black {{ $avgColor }} tracking-tight">{{ number_format($avgGrade, 1, ',', '.') }}</div>
                    <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Média Geral</div>
                </div>
            </flux:card>

            <!-- Metric 3: Taxa de Sucesso -->
            @php
                $totalSubmissions = $evaluation->submissions->count();
                $successSubmissions = $completedSubmissions->count();
                $successRate = $totalSubmissions > 0 ? round(($successSubmissions / $totalSubmissions) * 100) : 0;
            @endphp
            <flux:card class="p-6 flex items-center gap-4 bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div class="p-3 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                    <flux:icon.check-circle class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">{{ $successRate }}%</div>
                    <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Taxa de Sucesso</div>
                </div>
            </flux:card>
        </div>
    @endif

    <!-- REFERENCIAS & CRITERIOS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        @if ($evaluation->type === 'exam')
            <!-- Gabarito Card -->
            <flux:card class="p-5 flex items-center justify-between border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <flux:icon.check-badge class="w-6 h-6" />
                    </div>
                    <div>
                        <flux:heading size="sm">Gabarito Oficial</flux:heading>
                        <flux:subheading class="text-xs">Respostas esperadas</flux:subheading>
                    </div>
                </div>
                <div>
                    @if ($evaluation->answer_key_file_path)
                        <flux:button href="{{ Storage::url($evaluation->answer_key_file_path) }}" target="_blank" size="xs" variant="filled" color="zinc" icon="eye">Ver</flux:button>
                    @else
                        <flux:badge color="zinc" size="sm" class="italic">Não enviado</flux:badge>
                    @endif
                </div>
            </flux:card>

            <!-- Prova Original Card -->
            <flux:card class="p-5 flex items-center justify-between border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                        <flux:icon.document-text class="w-6 h-6" />
                    </div>
                    <div>
                        <flux:heading size="sm">Prova Original</flux:heading>
                        <flux:subheading class="text-xs">Prova em branco</flux:subheading>
                    </div>
                </div>
                <div>
                    @if ($evaluation->exam_file_path)
                        <flux:button href="{{ Storage::url($evaluation->exam_file_path) }}" target="_blank" size="xs" variant="filled" color="zinc" icon="eye">Ver</flux:button>
                    @else
                        <flux:badge color="zinc" size="sm" class="italic">Não enviado</flux:badge>
                    @endif
                </div>
            </flux:card>
        @endif

        <!-- Critérios Card -->
        <flux:card class="p-5 flex flex-col justify-center border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm {{ $evaluation->type !== 'exam' ? 'md:col-span-3' : '' }}">
            <flux:heading size="sm" class="mb-1">Critérios do Professor</flux:heading>
            <p class="text-xs text-zinc-500 italic truncate" title="{{ $evaluation->grading_criteria ?? 'Nenhum critério adicional definido.' }}">
                "{{ $evaluation->grading_criteria ?? 'Nenhum critério adicional definido.' }}"
            </p>
        </flux:card>
    </div>

    <!-- MAIN SUBMISSIONS LIST -->
    <flux:card class="relative overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <flux:heading size="lg" class="font-extrabold tracking-tight">Resultados dos Alunos</flux:heading>
                <flux:subheading>Gerencie as correções e visualize os feedbacks gerados pelo Gemini AI.</flux:subheading>
            </div>
            
            <div class="w-full sm:w-72">
                <flux:input wire:model.live="search" placeholder="Buscar aluno..." icon="magnifying-glass" size="sm" class="w-full" />
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Aluno / Arquivo</flux:table.column>
                <flux:table.column>Status da Correção</flux:table.column>
                <flux:table.column>Nota Final</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($evaluation->submissions as $submission)
                    <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                        <!-- Aluno & Arquivo -->
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if($submission->google_submission_id)
                                    <div class="flex-shrink-0 p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50" title="Importado do Google Classroom">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                    </div>
                                @endif
                                <div>
                                    <span class="font-bold text-zinc-900 dark:text-zinc-50 block leading-tight" title="{{ $submission->student_name }}">
                                        {{ $submission->student_name ? \Illuminate\Support\Str::limit($submission->student_name, 35) : 'Aguardando sincronização...' }}
                                    </span>
                                    <span class="text-xs text-zinc-400 font-mono block max-w-xs truncate mt-0.5" title="{{ basename($submission->student_file_path) }}">{{ basename($submission->student_file_path) }}</span>
                                </div>
                            </div>
                        </flux:table.cell>

                        <!-- Status -->
                        <flux:table.cell>
                            @if ($submission->status === 'completed')
                                <flux:badge color="green" size="sm">Concluído</flux:badge>
                            @elseif($submission->status === 'error')
                                <flux:badge color="red" size="sm">Erro</flux:badge>
                            @elseif($submission->status === 'processing')
                                <div class="flex flex-col gap-1">
                                    <flux:badge color="indigo" size="sm" class="animate-pulse">Corrigindo</flux:badge>
                                    <span class="text-[10px] text-zinc-500 italic">{{ $submission->status_message }}</span>
                                </div>
                            @else
                                <flux:badge color="zinc" size="sm">Pendente</flux:badge>
                            @endif
                        </flux:table.cell>

                        <!-- Nota Final -->
                        <flux:table.cell>
                            <div class="font-bold text-lg {{ $submission->final_grade >= 6 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $submission->final_grade !== null ? number_format($submission->final_grade, 2, ',', '.') : '-' }}
                            </div>
                        </flux:table.cell>

                        <!-- Ações -->
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                @if ($submission->status === 'completed')
                                    <flux:button wire:click="showFeedback({{ $submission->id }})" size="xs" variant="filled" color="indigo" icon="document-text">Feedback</flux:button>
                                    <flux:button wire:click="previewFile({{ $submission->id }})" size="xs" variant="ghost" icon="eye" tooltip="Visualizar Prova" />
                                    <flux:button href="{{ Storage::url($submission->student_file_path) }}" target="_blank" size="xs" variant="ghost" icon="arrow-down-tray" tooltip="Baixar Arquivo Original" />
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="xs" variant="ghost" icon="arrow-path" tooltip="Refazer Correção" />
                                @elseif($submission->status === 'error')
                                    <flux:modal.trigger name="replace-modal-{{ $submission->id }}">
                                        <flux:button size="xs" variant="filled" color="amber" icon="cloud-arrow-up">Substituir</flux:button>
                                    </flux:modal.trigger>
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="xs" variant="ghost" icon="arrow-path" tooltip="Tentar Novamente" />

                                    <!-- Modal de substituição -->
                                    <flux:modal name="replace-modal-{{ $submission->id }}" class="md:w-1/3">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">Substituir Arquivo Corrompido</flux:heading>
                                                <flux:subheading>Envie uma nova versão da prova do aluno para reiniciar a correção.</flux:subheading>
                                            </div>

                                            <flux:input type="file" wire:model="new_file" label="Nova Prova (PDF/Imagem/Word)" />

                                            <div class="flex justify-end gap-2">
                                                <flux:modal.close>
                                                    <flux:button variant="ghost">Cancelar</flux:button>
                                                </flux:modal.close>

                                                <flux:button wire:click="replaceFile({{ $submission->id }})" variant="primary" wire:loading.attr="disabled" icon="arrow-path">
                                                    Substituir e Corrigir
                                                </flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @else
                                    <flux:button wire:click="retrySubmission({{ $submission->id }})" size="xs" variant="ghost" icon="arrow-path" tooltip="Reenfileirar" />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500 py-12 italic">
                            Nenhuma submissão encontrada.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Modal Compartilhado: Feedback Detalhado -->
    <flux:modal name="feedback-shared-modal" class="md:w-3/4 max-w-4xl max-h-[85vh] overflow-y-auto">
        <div class="space-y-6">
            @if($viewing_submission)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <flux:heading size="lg" class="flex items-center gap-2">
                            Relatório de Correção IA
                            <flux:badge color="{{ $viewing_submission->final_grade >= 6 ? 'emerald' : 'rose' }}" size="sm" class="font-mono text-sm font-bold">
                                Nota Final: {{ $viewing_submission->final_grade }}
                            </flux:badge>
                        </flux:heading>
                        <flux:subheading title="{{ $viewing_submission->student_name }}">
                            {{ \Illuminate\Support\Str::limit($viewing_submission->student_name, 45) }}
                        </flux:subheading>
                        
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if ($viewing_submission->status === 'error')
                            <flux:button wire:click="retrySubmission({{ $viewing_submission->id }})" variant="primary" size="sm" icon="arrow-path">
                                Tentar Novamente
                            </flux:button>
                        @endif
                    </div>
                </div>

                @if ($viewing_submission->status === 'error')
                    <flux:card class="border-red-200 bg-red-50 dark:border-red-900/40 dark:bg-red-950/20 text-red-600 dark:text-red-400 p-5">
                        <div class="flex items-center gap-2.5 font-extrabold mb-2.5 text-lg">
                            <flux:icon.exclamation-triangle class="w-6 h-6 text-red-600" />
                            Erro de Processamento do Arquivo
                        </div>
                        <div class="text-xs font-mono bg-white/70 dark:bg-black/40 p-4 rounded-xl border border-red-100 dark:border-red-900/50 leading-relaxed shadow-sm">
                            {{ $viewing_submission->error_message }}
                        </div>
                    </flux:card>
                @endif

                @include('pages.evaluations.partials.feedback')
            @else
                <div class="flex flex-col items-center justify-center py-20 space-y-3">
                    <flux:icon.arrow-path class="w-8 h-8 text-zinc-400 animate-spin" />
                    <flux:badge color="zinc" class="animate-pulse">Carregando relatório de feedback...</flux:badge>
                </div>
            @endif
        </div>

        @if($viewing_submission)
            <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                <flux:modal.close>
                    <flux:button variant="filled">Fechar Relatório</flux:button>
                </flux:modal.close>
            </div>
        @endif
    </flux:modal>

    <!-- Modal Compartilhado: Visualização da Prova -->
    <flux:modal name="preview-file-modal" class="w-[95vw] h-[95vh] max-w-none">
        <div class="h-full flex flex-col space-y-4">
            <div class="flex justify-between items-center px-2">
                <flux:heading size="lg" class="font-extrabold truncate max-w-full" title="Visualizando Prova: {{ $viewing_student_name }}">Visualizando Prova: {{ \Illuminate\Support\Str::limit($viewing_student_name, 40) }}</flux:heading>
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
