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
            'import_from_classroom' => $this->import_from_classroom
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
    <div class="mb-8">
        <flux:heading size="xl" class="mb-1">🚀 Nova Correção Automatizada</flux:heading>
        <flux:subheading>Siga as etapas abaixo para configurar a inteligência artificial para sua avaliação.
        </flux:subheading>
    </div>

    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 text-sm text-red-600 dark:text-red-400 flex items-center gap-3">
            <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        <!-- ETAPA 1: CONFIGURAÇÃO BÁSICA -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-sm">
                    1</div>
                <flux:heading size="lg">Configurações Básicas</flux:heading>
            </div>

            <flux:card class="space-y-6">
                <flux:input wire:model="title" label="Título da Avaliação"
                    placeholder="Ex: Prova de História - 2º Trimestre" required />
                <flux:textarea wire:model="grading_criteria" label="Critérios Adicionais (Opcional)"
                    placeholder="Ex: Valorize a interpretação histórica. Se citar a data correta, considere 0.5 pontos extras."
                    rows="3" />

                <flux:separator />

                <div class="space-y-4">
                    <flux:checkbox wire:model.live="use_google_classroom" label="Vincular com o Google Classroom" 
                        description="Associe esta correção a uma de suas turmas para sincronizar notas e atividades." />

                    @if ($use_google_classroom)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in">
                            <div class="relative space-y-1">
                                <flux:select wire:model.live="selected_course_id" label="Selecionar Turma" placeholder="Escolha uma turma...">
                                    @foreach ($google_courses as $course)
                                        <flux:select.option value="{{ $course['id'] }}">{{ $course['name'] }} @if($course['section']) ({{ $course['section'] }}) @endif</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <div wire:loading wire:target="use_google_classroom" class="absolute right-2 top-0">
                                    <flux:badge color="indigo" size="sm" class="animate-pulse" icon="arrow-path">Buscando...</flux:badge>
                                </div>
                            </div>

                            @if ($selected_course_id)
                                <div class="relative space-y-1">
                                    <flux:select wire:model.live="selected_coursework_id" label="Selecionar Atividade" placeholder="Escolha uma atividade...">
                                        @foreach ($google_courseworks as $cw)
                                            <flux:select.option value="{{ $cw['id'] }}">{{ $cw['title'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <div wire:loading wire:target="selected_course_id" class="absolute right-2 top-0">
                                        <flux:badge color="indigo" size="sm" class="animate-pulse" icon="arrow-path">Buscando...</flux:badge>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($selected_coursework_id)
                            <div class="p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 space-y-3">
                                <flux:checkbox wire:model.live="import_from_classroom" label="Importar entregas diretamente do Google Classroom"
                                    description="Buscar automaticamente os arquivos enviados pelos alunos nesta atividade." />
                            </div>
                        @endif
                    @endif
                </div>
            </flux:card>
        </section>

        <!-- ETAPA 2: DOCUMENTOS DE REFERÊNCIA -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-sm">
                    2</div>
                <div>
                    <flux:heading size="lg">Documentos de Referência</flux:heading>
                    <flux:subheading>Estes arquivos ajudam a IA a ser 99% mais precisa.</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:card
                    class="relative overflow-hidden group border-zinc-200 dark:border-zinc-800 hover:border-indigo-500 transition-colors">
                    <div class="flex items-start gap-4">
                        <div
                            class="p-3 rounded-xl bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
                            <flux:icon.check-badge class="w-6 h-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="md">Documento A: Gabarito</flux:heading>
                            <flux:subheading class="mb-4">Arquivo com as respostas corretas esperadas.
                            </flux:subheading>
                            <flux:input type="file" wire:model="answer_key" accept=".pdf,image/*,.docx,.doc,.txt" />
                        </div>
                    </div>
                </flux:card>

                <flux:card
                    class="relative overflow-hidden group border-zinc-200 dark:border-zinc-800 hover:border-indigo-500 transition-colors">
                    <div class="flex items-start gap-4">
                        <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            <flux:icon.document-text class="w-6 h-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="md">Documento B: Prova Original</flux:heading>
                            <flux:subheading class="mb-4">A prova em branco ajuda a IA a ler as perguntas.
                            </flux:subheading>
                            <flux:input type="file" wire:model="exam_file" accept=".pdf,image/*,.docx,.doc,.txt" />
                        </div>
                    </div>
                </flux:card>
            </div>
        </section>

        <!-- ETAPA 3: SUBMISSÃO DOS ALUNOS -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-sm">
                    3</div>
                <div>
                    <flux:heading size="lg">Provas dos Alunos</flux:heading>
                    <flux:subheading>Envie os arquivos preenchidos que devem ser corrigidos agora.</flux:subheading>
                </div>
            </div>

            @if ($import_from_classroom)
                <flux:card class="border-2 border-dashed border-green-200 dark:border-green-900/50 bg-green-50/30 dark:bg-green-900/5">
                    <div class="flex flex-col items-center justify-center py-8 text-center space-y-4">
                        <div class="p-4 rounded-full bg-green-100 dark:bg-green-900/50 text-green-600">
                            <flux:icon.check-circle class="w-10 h-10" />
                        </div>
                        <div class="max-w-md mx-auto space-y-2">
                            <flux:heading size="md">Tudo Pronto para Importação Automática</flux:heading>
                            <flux:subheading>As provas serão baixadas diretamente da atividade selecionada do Google Classroom assim que você iniciar.</flux:subheading>
                        </div>
                    </div>
                </flux:card>
            @else
                <flux:card class="border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-900/5">
                    <div class="flex flex-col items-center justify-center py-4 text-center space-y-6">
                        <div class="p-4 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600">
                            <flux:icon.cloud-arrow-up class="w-10 h-10" />
                        </div>
                        
                        <div class="w-full max-w-md mx-auto space-y-4">
                            <flux:input wire:model="student_submissions" type="file" label="Provas dos Alunos"
                                placeholder="Selecione os arquivos..." multiple required
                                help="Selecione múltiplos arquivos (PDF, Imagem, Word) ou um arquivo .ZIP" />

                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex items-start gap-3 text-left">
                                <flux:icon.light-bulb class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                                <div class="text-xs text-amber-800 dark:text-amber-300">
                                    <span class="font-bold block mb-1">Dica para grandes lotes:</span>
                                    Se você tem mais de 15 alunos, recomendamos enviar todas as provas em um único arquivo **.ZIP**. Isso é mais rápido e evita limitações do navegador.
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif
        </section>

        <!-- BOTÕES DE AÇÃO -->
        <div class="flex items-center justify-between pt-6 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button href="{{ route('evaluations.index') }}" variant="ghost">Cancelar e Voltar</flux:button>

            <div class="flex items-center gap-4">
                <div wire:loading wire:target="student_submissions">
                    <flux:badge color="zinc" size="sm" class="animate-pulse" icon="arrow-path">Enviando arquivos...</flux:badge>
                </div>

                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="save">
                    <span wire:loading.remove wire:target="save">Iniciar Inteligência Artificial</span>
                    <span wire:loading wire:target="save">Preparando Correção...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
