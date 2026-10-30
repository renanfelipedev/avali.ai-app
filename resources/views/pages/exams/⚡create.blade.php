<?php

use App\Models\Exam;
use App\Models\ExamGenerationRequest;
use App\Models\SystemSetting;
use App\Services\ExamGenerationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component {
    use WithFileUploads;

    public $title = '';
    public $classroom_id = null;
    public $objective_count = 5;
    public $discursive_count = 2;
    public $topics = '';
    public $additional_criteria = '';
    public $question_images = [];
    public $files = [];
    public $isSubmitting = false;

    protected $rules = [
        'title' => 'nullable|string|max:255',
        'classroom_id' => 'nullable|exists:classrooms,id',
        'objective_count' => 'required|integer|min:0|max:50',
        'discursive_count' => 'required|integer|min:0|max:50',
        'topics' => 'required|string',
        'additional_criteria' => 'nullable|string',
        'question_images.*' => 'nullable|image|max:10240', // 10MB max per image
        'files.*' => 'nullable|file|max:10240', // 10MB max per file
    ];

    public function removeQuestionImage(int $index): void
    {
        if (isset($this->question_images[$index])) {
            unset($this->question_images[$index]);
            $this->question_images = array_values($this->question_images);
        }
    }

    public function clearQuestionImages(): void
    {
        $this->question_images = [];
    }

    public function save(ExamGenerationService $service)
    {
        if ($this->isSubmitting) {
            return null;
        }

        $this->isSubmitting = true;

        try {
            $this->validate();

            // Validação de cota mensal do Plano
            if (! auth()->user()->canGenerateExam()) {
                $planLimit = auth()->user()->currentPlan()->getFeature('monthly_exams_limit', 5);
                $this->addError('title', "Você atingiu a cota mensal de {$planLimit} provas do seu plano atual (".auth()->user()->currentPlan()->name.'). Faça upgrade para continuar gerando.');
                $this->isSubmitting = false;

                return null;
            }

            // Validação de limite diário de segurança
            $dailyLimit = (int) SystemSetting::get('daily_exam_limit_per_teacher', 20);
            if (! auth()->user()->isAdmin()) {
                $todayCount = Exam::where('user_id', auth()->id())
                    ->whereDate('created_at', today())
                    ->count();

                if ($todayCount >= $dailyLimit) {
                    $this->addError('title', "Você atingiu seu limite diário de {$dailyLimit} provas geradas por professor. Tente novamente amanhã ou fale com um administrador.");
                    $this->isSubmitting = false;

                    return null;
                }
            }

            // Validação de quantidade máxima de questões permitidas pelo plano
            $maxQuestions = (int) auth()->user()->currentPlan()->getFeature('max_questions_per_exam', 10);
            $totalQuestionsRequested = $this->objective_count + $this->discursive_count;
            if ($maxQuestions > 0 && $totalQuestionsRequested > $maxQuestions && ! auth()->user()->isAdmin()) {
                $this->addError('objective_count', "O seu plano atual permite até {$maxQuestions} questões por prova. Você solicitou {$totalQuestionsRequested}. Reduza a quantidade ou faça upgrade de plano.");
                $this->isSubmitting = false;

                return null;
            }

            // Validação de imagens / tirinhas permitidas pelo plano
            if (! empty($this->question_images) && ! auth()->user()->canUseFeature('allow_question_images')) {
                $this->addError('question_images', 'O envio de imagens e tirinhas para formulação de questões é um recurso exclusivo dos planos Pro e Institucional. Remova as imagens ou faça upgrade de plano.');
                $this->isSubmitting = false;

                return null;
            }

            if ($this->objective_count == 0 && $this->discursive_count == 0) {
                $this->addError('objective_count', 'Pelo menos uma questão deve ser solicitada.');
                $this->isSubmitting = false;

                return null;
            }

            $storedFiles = [];

            // Armazena imagens específicas para questões (tirinhas, charges, fotos, gráficos)
            foreach ($this->question_images as $image) {
                $path = $image->store('exam_images', 'public');

                $storedFiles[] = [
                    'path' => $path,
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'is_question_image' => true,
                ];
            }

            // Armazena documentos de apoio gerais (PDFs, textos)
            foreach ($this->files as $file) {
                $mimeType = $file->getMimeType();
                $isImage = str_starts_with($mimeType, 'image/');
                $path = $file->store($isImage ? 'exam_images' : 'supporting_materials', 'public');

                $storedFiles[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'is_question_image' => $isImage,
                ];
            }

            $generationRequest = ExamGenerationRequest::create([
                'user_id' => auth()->id(),
                'classroom_id' => $this->classroom_id,
                'title' => $this->title ?: null,
                'questions_count' => $this->objective_count + $this->discursive_count,
                'objective_count' => $this->objective_count,
                'discursive_count' => $this->discursive_count,
                'topics' => explode(',', $this->topics),
                'additional_criteria' => $this->additional_criteria ?: null,
                'supporting_materials' => $storedFiles,
                'status' => 'pending',
            ]);

            // Dispatch background job com prioridade baseada no plano
            \App\Jobs\GenerateExamJob::dispatch($generationRequest)->onQueue(auth()->user()->getQueuePriority());

            session()->flash('status', 'A solicitação de geração de prova foi enviada para processamento em segundo plano.');

            return redirect()->route('tasks.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSubmitting = false;
            throw $e;
        } catch (\Throwable $e) {
            $this->isSubmitting = false;
            throw $e;
        }
    }

    public function with(): array
    {
        $plan = auth()->user()->currentPlan();
        $monthlyLimit = $plan->getFeature('monthly_exams_limit', 5);

        return [
            'classrooms' => auth()->user()->classrooms()->orderBy('name', 'asc')->get(),
            'currentPlan' => $plan,
            'canUseImages' => auth()->user()->canUseFeature('allow_question_images'),
            'monthlyExamsUsed' => auth()->user()->getCurrentMonthExamsCount(),
            'monthlyExamsLimit' => $monthlyLimit === -1 ? 'Ilimitado' : $monthlyLimit,
        ];
    }
};
?>

<div>
    <!-- PAGE TITLE -->
    <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl" class="font-extrabold tracking-tight">✨ Gerar Nova Prova com IA</flux:heading>
            <flux:subheading>Defina os temas, quantidade de questões e anexe materiais de apoio para criar uma prova personalizada em minutos.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:border-indigo-300 transition-colors">
                <flux:icon.credit-card class="w-4 h-4 text-indigo-600" />
                <span>{{ $currentPlan->name }} ({{ $monthlyExamsUsed }}/{{ $monthlyExamsLimit }} provas)</span>
            </a>
        </div>
    </div>

    <!-- SESSION ERROR MESSAGE -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 text-sm text-rose-600 dark:text-rose-400 flex items-center gap-3 animate-fade-in shadow-sm">
            <flux:icon.exclamation-triangle class="w-5 h-5 text-rose-600 dark:text-rose-400" />
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    @if (! auth()->user()->canGenerateExam())
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-300 flex items-center justify-between gap-3 animate-fade-in shadow-sm">
            <div class="flex items-center gap-3">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
                <span>
                    <strong>Limite mensal do seu plano atingido:</strong> Você já utilizou todas as {{ $monthlyExamsLimit }} provas incluídas no plano {{ $currentPlan->name }} neste mês.
                </span>
            </div>
            <flux:button href="{{ route('plans.index') }}" size="sm" variant="primary">
                Fazer Upgrade
            </flux:button>
        </div>
    @endif

    <form wire:submit="save" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return false; } submitting = true" class="space-y-8">
        <!-- ETAPA 1: PARÂMETROS DA PROVA -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">1</flux:badge>
                <flux:heading size="lg" class="font-bold">Configurações da Prova</flux:heading>
            </div>

            <flux:card class="space-y-6 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="title" label="Título da Prova (Opcional)"
                        placeholder="Ex: Prova de Matemática - 1º Bimestre" />
                    
                    <flux:select wire:model="classroom_id" label="Vincular a uma Turma (Opcional)">
                        <flux:select.option value="">Sem vínculo</flux:select.option>
                        @foreach($classrooms as $classroom)
                            <flux:select.option value="{{ $classroom->id }}">{{ $classroom->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="objective_count" label="Questões Objetivas" type="number" placeholder="Ex: 5"
                        min="0" max="50" />
                    <flux:input wire:model="discursive_count" label="Questões Discursivas" type="number"
                        placeholder="Ex: 2" min="0" max="50" />
                </div>
            </flux:card>
        </section>

        <!-- ETAPA 2: CONTEÚDO E DIRETRIZES -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">2</flux:badge>
                <flux:heading size="lg" class="font-bold">Conteúdo & Diretrizes da IA</flux:heading>
            </div>

            <flux:card class="space-y-6 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <flux:textarea wire:model="topics" label="Temas / Assuntos"
                    placeholder="Ex: Cálculo I, Derivadas, Integrais (separe por vírgula)" rows="3" required />

                <flux:textarea wire:model="additional_criteria" label="Critérios Adicionais / Instruções Especiais (Opcional)"
                    placeholder="Ex: Questões com foco em aplicações práticas do cotidiano, nível de dificuldade intermediário, incluir alternativas bem elaboradas..." rows="3" />
            </flux:card>
        </section>

        <!-- ETAPA 3: IMAGENS E TIRINHAS PARA QUESTÕES -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">3</flux:badge>
                <div class="flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <flux:heading size="lg" class="font-bold">Imagens e Tirinhas para as Questões (Opcional)</flux:heading>
                        <flux:subheading>Envie uma ou mais imagens (tirinhas, charges, ilustrações ou gráficos). O Gemini analisará visualmente as imagens e formulará questões contextualizadas sobre elas.</flux:subheading>
                    </div>
                    @if(!$canUseImages)
                        <flux:badge color="amber" size="sm" class="self-start sm:self-auto font-bold">Recurso Pro</flux:badge>
                    @endif
                </div>
            </div>

            @if(!$canUseImages)
                <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                    <div class="flex items-center gap-2.5">
                        <flux:icon.sparkles class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                        <span>O envio de imagens e tirinhas para formulação de questões está liberado a partir do <strong>Plano Professor Pro</strong>.</span>
                    </div>
                    <flux:button href="{{ route('plans.index') }}" size="xs" variant="primary" class="self-start sm:self-auto font-bold">Conhecer Planos</flux:button>
                </div>
            @endif

            <flux:card class="border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/10 dark:bg-indigo-950/5 shadow-sm space-y-6">
                <div class="flex flex-col items-center justify-center py-4 text-center space-y-4">
                    <div class="p-4 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 border border-indigo-200 dark:border-indigo-800 shadow-sm">
                        <flux:icon.photo class="w-10 h-10" />
                    </div>

                    <div class="w-full max-w-xl mx-auto space-y-3 px-4">
                        <flux:input type="file" wire:model="question_images" multiple accept="image/png,image/jpeg,image/webp,image/gif"
                            label="Selecionar Imagens / Tirinhas" help="Selecione imagens em PNG, JPG ou WEBP (até 10MB cada)." />

                        <div wire:loading wire:target="question_images" class="text-xs text-indigo-600 font-medium">
                            Enviando imagens selecionadas...
                        </div>
                    </div>
                </div>

                <!-- Preview Grid das Imagens Selecionadas -->
                @if ($question_images)
                    <div class="border-t border-indigo-100 dark:border-indigo-900/40 pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                {{ count($question_images) }} imagem(ns) selecionada(s) para compor a prova:
                            </span>
                            <flux:button wire:click="clearQuestionImages" size="xs" variant="ghost" color="indigo" class="font-extrabold">
                                Remover Todas
                            </flux:button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach ($question_images as $index => $img)
                                <div class="relative group bg-white dark:bg-zinc-950/60 p-3 rounded-2xl border border-indigo-100 dark:border-indigo-900/50 shadow-sm space-y-2">
                                    <div class="relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-900 aspect-video flex items-center justify-center border border-zinc-200 dark:border-zinc-800">
                                        @if(method_exists($img, 'temporaryUrl'))
                                            <img src="{{ $img->temporaryUrl() }}" alt="Preview da Imagem {{ $index + 1 }}" class="h-full w-full object-contain" />
                                        @else
                                            <flux:icon.photo class="w-8 h-8 text-zinc-400" />
                                        @endif
                                        <flux:badge size="xs" color="indigo" class="absolute top-2 left-2 shadow">
                                            Imagem {{ $index + 1 }}
                                        </flux:badge>
                                    </div>
                                    <div class="flex items-center justify-between text-xs pt-1">
                                        <span class="truncate font-medium text-zinc-700 dark:text-zinc-300 max-w-[140px]" title="{{ $img->getClientOriginalName() }}">
                                            {{ $img->getClientOriginalName() }}
                                        </span>
                                        <button type="button" wire:click="removeQuestionImage({{ $index }})" class="text-rose-500 hover:text-rose-700 p-1 rounded-md hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors" title="Remover imagem">
                                            <flux:icon.trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        </section>

        <!-- ETAPA 4: MATERIAIS DE APOIO GERAIS -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">4</flux:badge>
                <div>
                    <flux:heading size="lg" class="font-bold">Documentos e Textos de Apoio (Opcional)</flux:heading>
                    <flux:subheading>Envie livros, artigos, capítulos ou provas antigas em PDF para contextualizar a IA na geração geral.</flux:subheading>
                </div>
            </div>

            <flux:card class="border-2 border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50/30 dark:bg-zinc-900/10 shadow-sm">
                <div class="flex flex-col items-center justify-center py-6 text-center space-y-6">
                    <div class="p-4 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 border border-zinc-200 dark:border-zinc-700 shadow-sm">
                        <flux:icon.cloud-arrow-up class="w-10 h-10" />
                    </div>

                    <div class="w-full max-w-xl mx-auto space-y-4 px-4">
                        <flux:input type="file" wire:model="files" multiple accept=".pdf,.docx,.txt"
                            label="Selecionar Documentos" help="Selecione arquivos PDF ou DOCX de até 10MB cada." />

                        <!-- Queue File list -->
                        @if ($files)
                            <div class="mt-4 p-4 rounded-2xl bg-zinc-100/60 dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 text-xs text-zinc-700 dark:text-zinc-300 space-y-2.5 max-h-48 overflow-y-auto shadow-inner animate-fade-in text-left">
                                <div class="font-bold flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-2">
                                    <span>{{ count($files) }} arquivo(s) anexado(s):</span>
                                    <flux:button wire:click="$set('files', [])" size="xs" variant="ghost" class="font-extrabold">Limpar Fila</flux:button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($files as $index => $file)
                                        <div class="flex items-center justify-between bg-white/70 dark:bg-zinc-950/40 p-2 rounded-xl border border-zinc-200 dark:border-zinc-800 truncate shadow-sm">
                                            <div class="flex items-center gap-2 truncate">
                                                <flux:icon.document-text class="w-3.5 h-3.5 text-zinc-500 flex-shrink-0" />
                                                <span class="truncate font-medium text-zinc-700 dark:text-zinc-300">{{ $file->getClientOriginalName() }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        </section>

        <!-- ACTION BUTTONS -->
        <div class="flex items-center justify-between pt-6 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button href="{{ route('home') }}" variant="ghost">Cancelar e Voltar</flux:button>

            <div class="flex items-center gap-4">
                <x-loading target="files,question_images" variant="badge">
                    Enviando arquivos...
                </x-loading>

                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="save" x-bind:disabled="submitting" class="font-bold">
                    <span wire:loading.remove wire:target="save">Solicitar Geração</span>
                    <span wire:loading wire:target="save">Processando...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
