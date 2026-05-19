<?php

use App\Models\ExamEvaluation;
use App\Services\SubmissionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component {
    use WithFileUploads;

    public $title = '';
    public $grading_criteria = '';
    public $answer_key;
    public $exam_file;
    public $student_submissions = [];
    public $isProcessing = false;

    // Google Classroom properties
    public $use_google_classroom = false;
    public $google_courses = [];
    public $selected_course_id = '';
    public $google_courseworks = [];
    public $selected_coursework_id = '';
    public $import_from_classroom = false;

    public function updatedUseGoogleClassroom($value)
    {
        if ($value) {
            $classroomService = app(\App\Services\GoogleClassroomService::class);
            $this->google_courses = $classroomService->listCourses(auth()->user());
            if (empty($this->google_courses)) {
                $this->use_google_classroom = false;
                session()->flash('error', 'Nenhuma turma ativa encontrada ou sua conta Google precisa ser reconectada.');
            }
        } else {
            $this->google_courses = [];
            $this->selected_course_id = '';
            $this->google_courseworks = [];
            $this->selected_coursework_id = '';
            $this->import_from_classroom = false;
        }
    }

    public function updatedSelectedCourseId($value)
    {
        if ($value) {
            $classroomService = app(\App\Services\GoogleClassroomService::class);
            $this->google_courseworks = $classroomService->listCourseWork(auth()->user(), $value);
        } else {
            $this->google_courseworks = [];
            $this->selected_coursework_id = '';
        }
    }

    public function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'grading_criteria' => 'nullable|string',
            'answer_key' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx,doc,txt|max:10240',
            'exam_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx,doc,txt|max:10240',
        ];

        if (!$this->import_from_classroom) {
            $rules['student_submissions'] = 'required';
            $rules['student_submissions.*'] = 'file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,doc,txt|max:51200';
        }

        return $rules;
    }

    public function save(SubmissionService $submissionService)
    {
        $this->validate();
        $this->isProcessing = true;

        \Log::info('Iniciando salvamento de avaliação', [
            'total_arquivos' => count($this->student_submissions),
            'import_from_classroom' => $this->import_from_classroom,
        ]);

        // Store Reference Documents (Background normalization)
        $answerKeyPath = $this->answer_key ? $this->answer_key->store('evaluations/answer_keys', 'public') : null;
        $examFilePath = $this->exam_file ? $this->exam_file->store('evaluations/exams', 'public') : null;

        // Create Evaluation
        $evaluation = ExamEvaluation::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'grading_criteria' => $this->grading_criteria,
            'answer_key_file_path' => $answerKeyPath,
            'exam_file_path' => $examFilePath,
            'status' => $this->import_from_classroom ? 'importing' : 'processing',
            'google_course_id' => $this->use_google_classroom ? $this->selected_course_id : null,
            'google_coursework_id' => $this->use_google_classroom ? $this->selected_coursework_id : null,
        ]);

        if ($this->import_from_classroom) {
            // A importação das entregas do Google Classroom será processada de forma assíncrona (Fase 3)
            \App\Jobs\SyncClassroomSubmissionsJob::dispatch($evaluation);
            session()->flash('status', 'A importação automática das provas do Google Classroom foi agendada.');
        } else {
            // Delegate complex processing to service (DRY & KISS)
            $submissionService->processUploads($evaluation, $this->student_submissions);
            session()->flash('status', 'A correção das provas foi iniciada em segundo plano.');
        }

        return redirect()->route('tasks.index');
    }
};
?>

<div>
    <!-- PAGE TITLE -->
    <div class="mb-8">
        <flux:heading size="xl" class="font-extrabold tracking-tight">🚀 Nova Correção Automatizada</flux:heading>
        <flux:subheading>Configure a Inteligência Artificial e anexe os arquivos das provas para correção instantânea.
        </flux:subheading>
    </div>

    <!-- SESSION ERROR MESSAGE -->
    @if (session()->has('error'))
        <div
            class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 text-sm text-rose-600 dark:text-rose-400 flex items-center gap-3 animate-fade-in shadow-sm">
            <flux:icon.exclamation-triangle class="w-5 h-5 text-rose-600 dark:text-rose-400" />
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        <!-- ETAPA 1: CONFIGURAÇÃO BÁSICA -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">1</flux:badge>
                <flux:heading size="lg" class="font-bold">Configurações da Avaliação</flux:heading>
            </div>

            <flux:card class="space-y-6 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <flux:input wire:model="title" label="Título da Avaliação"
                    placeholder="Ex: Prova de História - 2º Trimestre" required />

                <flux:textarea wire:model="grading_criteria" label="Critérios Adicionais (Opcional)"
                    placeholder="Ex: Valorize a interpretação histórica. Se citar a data correta, considere 0.5 pontos extras."
                    rows="3" />

                <flux:separator />

                <!-- GOOGLE CLASSROOM INTEGRATION MODULE -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model.live="use_google_classroom"
                            label="Vincular com o Google Classroom"
                            description="Associe esta correção a uma de suas turmas para sincronizar notas e atividades."
                            class="font-bold" />
                    </div>

                    @if ($use_google_classroom)
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in pt-3 border-t border-zinc-200 dark:border-zinc-800/60">
                            <!-- Course Select -->
                            <div class="relative space-y-1">
                                <flux:select wire:model.live="selected_course_id" label="Selecionar Turma"
                                    placeholder="Escolha uma turma...">
                                    @foreach ($google_courses as $course)
                                        <flux:select.option value="{{ $course['id'] }}">{{ $course['name'] }}
                                            @if ($course['section'])
                                                ({{ $course['section'] }})
                                            @endif
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <div wire:loading wire:target="use_google_classroom" class="absolute right-2 top-0">
                                    <flux:badge color="indigo" size="sm" class="animate-pulse" icon="arrow-path">
                                        Buscando...</flux:badge>
                                </div>
                            </div>

                            <!-- CourseWork Select -->
                            @if ($selected_course_id)
                                <div class="relative space-y-1 animate-fade-in">
                                    <flux:select wire:model.live="selected_coursework_id" label="Selecionar Atividade"
                                        placeholder="Escolha uma atividade...">
                                        @foreach ($google_courseworks as $cw)
                                            <flux:select.option value="{{ $cw['id'] }}">{{ $cw['title'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <div wire:loading wire:target="selected_course_id" class="absolute right-2 top-0">
                                        <flux:badge color="indigo" size="sm" class="animate-pulse"
                                            icon="arrow-path">Buscando...</flux:badge>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Sync Checkbox -->
                        @if ($selected_coursework_id)
                            <div
                                class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/10 border border-emerald-100 dark:border-emerald-900/40 space-y-3 animate-fade-in shadow-inner">
                                <flux:checkbox wire:model.live="import_from_classroom"
                                    label="Importar entregas diretamente do Google Classroom"
                                    description="O avali.ai baixará automaticamente todos os anexos de entregas dos alunos nesta atividade."
                                    class="font-bold text-emerald-800 dark:text-emerald-300" />
                            </div>
                        @endif
                    @endif
                </div>
            </flux:card>
        </section>

        <!-- ETAPA 2: DOCUMENTOS DE REFERÊNCIA -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">2</flux:badge>
                <div>
                    <flux:heading size="lg" class="font-bold">Documentos de Referência</flux:heading>
                    <flux:subheading>Forneça gabaritos ou modelos para que o Gemini AI tenha 99% de precisão.
                    </flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Documento A: Gabarito -->
                <flux:card
                    class="relative overflow-hidden group border-zinc-200 dark:border-zinc-800 hover:border-indigo-500 transition-colors shadow-sm">
                    <div class="flex items-start gap-4">
                        <div
                            class="p-3 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/30">
                            <flux:icon.check-badge class="w-6 h-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="md" class="font-bold">Documento A: Gabarito Oficial</flux:heading>
                            <flux:subheading class="mb-4 text-xs">O arquivo de referência com as respostas corretas.
                            </flux:subheading>

                            <flux:input type="file" wire:model="answer_key" accept=".pdf,image/*,.docx,.doc,.txt" />

                            @if ($answer_key)
                                <div
                                    class="mt-3.5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 text-xs text-emerald-700 dark:text-emerald-300 flex items-center justify-between shadow-sm animate-fade-in">
                                    <div class="flex items-center gap-2 truncate">
                                        <flux:icon.check-circle class="w-4 h-4 text-emerald-600" />
                                        <span
                                            class="truncate font-bold">{{ $answer_key->getClientOriginalName() }}</span>
                                    </div>
                                    <flux:button wire:click="$set('answer_key', null)" size="xs" variant="ghost"
                                        icon="x-mark" color="emerald" tooltip="Remover arquivo" />
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>

                <!-- Documento B: Prova Original -->
                <flux:card
                    class="relative overflow-hidden group border-zinc-200 dark:border-zinc-800 hover:border-indigo-500 transition-colors shadow-sm">
                    <div class="flex items-start gap-4">
                        <div
                            class="p-3 rounded-xl bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/30">
                            <flux:icon.document-text class="w-6 h-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="md" class="font-bold">Documento B: Prova Original em Branco
                            </flux:heading>
                            <flux:subheading class="mb-4 text-xs">Ajuda o Gemini a decifrar a estrutura de questões.
                            </flux:subheading>

                            <flux:input type="file" wire:model="exam_file" accept=".pdf,image/*,.docx,.doc,.txt" />

                            @if ($exam_file)
                                <div
                                    class="mt-3.5 p-3 rounded-xl bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/50 text-xs text-blue-700 dark:text-blue-300 flex items-center justify-between shadow-sm animate-fade-in">
                                    <div class="flex items-center gap-2 truncate">
                                        <flux:icon.check-circle class="w-4 h-4 text-blue-600" />
                                        <span
                                            class="truncate font-bold">{{ $exam_file->getClientOriginalName() }}</span>
                                    </div>
                                    <flux:button wire:click="$set('exam_file', null)" size="xs" variant="ghost"
                                        icon="x-mark" color="blue" tooltip="Remover arquivo" />
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>
        </section>

        <!-- ETAPA 3: SUBMISSÃO DOS ALUNOS -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">3</flux:badge>
                <div>
                    <flux:heading size="lg" class="font-bold">Provas dos Alunos</flux:heading>
                    <flux:subheading>Selecione as provas entregues pelos alunos para iniciar o pipeline de correção.
                    </flux:subheading>
                </div>
            </div>

            <!-- Classroom Sync Mode -->
            @if ($import_from_classroom)
                <flux:card
                    class="border-2 border-dashed border-emerald-300 dark:border-emerald-800 bg-emerald-50/10 dark:bg-emerald-950/10 shadow-sm animate-fade-in">
                    <div class="flex flex-col items-center justify-center py-10 text-center space-y-4">
                        <div
                            class="p-4 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 border border-emerald-200 dark:border-emerald-800 shadow-sm">
                            <flux:icon.check-circle class="w-12 h-12" />
                        </div>
                        <div class="max-w-md mx-auto space-y-2">
                            <flux:heading size="lg"
                                class="font-extrabold text-emerald-900 dark:text-emerald-100">Pronto para Importação
                                Direta</flux:heading>
                            <flux:subheading class="text-emerald-700 dark:text-emerald-400">O servidor fará o download
                                automático das entregas e anexos dos alunos diretamente de sua atividade vinculada do
                                Classroom assim que você iniciar!</flux:subheading>
                        </div>
                    </div>
                </flux:card>
                <!-- Manual Upload Mode -->
            @else
                <flux:card
                    class="border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/10 dark:bg-indigo-950/5 shadow-sm">
                    <div class="flex flex-col items-center justify-center py-6 text-center space-y-6">
                        <div
                            class="p-4 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 border border-indigo-200 dark:border-indigo-800 shadow-sm">
                            <flux:icon.cloud-arrow-up class="w-10 h-10" />
                        </div>

                        <div class="w-full max-w-xl mx-auto space-y-4 px-4">
                            <flux:input wire:model="student_submissions" type="file"
                                label="Selecione Provas de Alunos"
                                placeholder="Selecione um ZIP ou múltiplos arquivos..." multiple required
                                help="Selecione múltiplos arquivos (PDF, Imagens, Word, TXT) ou um arquivo .ZIP unificado." />

                            <!-- Queue File list -->
                            @if ($student_submissions)
                                <div
                                    class="mt-4 p-4 rounded-2xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 text-xs text-indigo-700 dark:text-indigo-300 space-y-2.5 max-h-48 overflow-y-auto shadow-inner animate-fade-in">
                                    <div
                                        class="font-bold flex items-center justify-between border-b border-indigo-100 dark:border-indigo-900/40 pb-2">
                                        <span>{{ count($student_submissions) }} arquivos na fila de envio:</span>
                                        <flux:button wire:click="$set('student_submissions', [])" size="xs"
                                            variant="ghost" color="indigo" class="font-extrabold">Limpar Fila
                                        </flux:button>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach ($student_submissions as $index => $file)
                                            <div
                                                class="flex items-center gap-2 bg-white/70 dark:bg-zinc-950/40 p-2 rounded-xl border border-indigo-50 dark:border-indigo-900/50 truncate shadow-sm">
                                                <flux:icon.document-text
                                                    class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                                                <span
                                                    class="truncate font-medium text-zinc-700 dark:text-zinc-300">{{ $file->getClientOriginalName() }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Tip Alert box -->
                            <div
                                class="p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/50 rounded-2xl flex items-start gap-3 text-left">
                                <flux:icon.light-bulb class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                                <div class="text-xs text-amber-800 dark:text-amber-300">
                                    <span class="font-bold block mb-1">Dica para grandes turmas:</span>
                                    Se você possui mais de 10 alunos, é altamente recomendado empacotar as fotos ou PDFs
                                    de todas as provas em um único arquivo **.ZIP** e enviá-lo. Isso acelera o
                                    processamento em mais de 3x.
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif
        </section>

        <!-- ACTION BUTTONS -->
        <div class="flex items-center justify-between pt-6 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button href="{{ route('evaluations.index') }}" variant="ghost">Cancelar e Voltar</flux:button>

            <div class="flex items-center gap-4">
                <div wire:loading wire:target="student_submissions">
                    <flux:badge color="zinc" size="sm" class="animate-pulse" icon="arrow-path">Enviando
                        arquivos...</flux:badge>
                </div>

                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="save" class="font-bold">
                    <span wire:loading.remove wire:target="save">Iniciar Inteligência Artificial</span>
                    <span wire:loading wire:target="save">Preparando Correção...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
