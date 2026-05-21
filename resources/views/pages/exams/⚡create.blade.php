<?php

use App\Models\ExamGenerationRequest;
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
    public $files = [];

    protected $rules = [
        'title' => 'nullable|string|max:255',
        'classroom_id' => 'nullable|exists:classrooms,id',
        'objective_count' => 'required|integer|min:0|max:50',
        'discursive_count' => 'required|integer|min:0|max:50',
        'topics' => 'required|string',
        'additional_criteria' => 'nullable|string',
        'files.*' => 'nullable|file|max:10240', // 10MB max per file
    ];

    public function save(ExamGenerationService $service)
    {
        $this->validate();

        if ($this->objective_count == 0 && $this->discursive_count == 0) {
            $this->addError('objective_count', 'Pelo menos uma questão deve ser solicitada.');
            return;
        }

        $storedFiles = [];
        foreach ($this->files as $file) {
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $path = $file->store('supporting_materials', 'public');

            $storedFiles[] = [
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $size,
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

        // Dispatch background job
        \App\Jobs\GenerateExamJob::dispatch($generationRequest);

        session()->flash('status', 'A solicitação de geração de prova foi enviada para processamento em segundo plano.');

        return redirect()->route('tasks.index');
    }
    public function with(): array
    {
        return [
            'classrooms' => auth()->user()->classrooms()->latest()->get(),
        ];
    }
};
?>

<div>
    <!-- PAGE TITLE -->
    <div class="mb-8">
        <flux:heading size="xl" class="font-extrabold tracking-tight">✨ Gerar Nova Prova com IA</flux:heading>
        <flux:subheading>Defina os temas, quantidade de questões e anexe materiais de apoio para criar uma prova personalizada em minutos.</flux:subheading>
    </div>

    <!-- SESSION ERROR MESSAGE -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 text-sm text-rose-600 dark:text-rose-400 flex items-center gap-3 animate-fade-in shadow-sm">
            <flux:icon.exclamation-triangle class="w-5 h-5 text-rose-600 dark:text-rose-400" />
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
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

        <!-- ETAPA 3: MATERIAIS DE APOIO -->
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-2">
                <flux:badge color="indigo" class="w-8 h-8 flex items-center justify-center rounded-full font-black text-sm shadow-sm">3</flux:badge>
                <div>
                    <flux:heading size="lg" class="font-bold">Materiais de Apoio (Opcional)</flux:heading>
                    <flux:subheading>Envie livros, artigos, capítulos ou provas antigas em PDF para guiar a IA na geração das questões.</flux:subheading>
                </div>
            </div>

            <flux:card class="border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/10 dark:bg-indigo-950/5 shadow-sm">
                <div class="flex flex-col items-center justify-center py-6 text-center space-y-6">
                    <div class="p-4 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 border border-indigo-200 dark:border-indigo-800 shadow-sm">
                        <flux:icon.cloud-arrow-up class="w-10 h-10" />
                    </div>

                    <div class="w-full max-w-xl mx-auto space-y-4 px-4">
                        <flux:input type="file" wire:model="files" multiple accept=".pdf,.docx,image/*"
                            label="Selecionar Arquivos" help="Selecione múltiplos arquivos até 10MB cada." />

                        <!-- Queue File list -->
                        @if ($files)
                            <div class="mt-4 p-4 rounded-2xl bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 text-xs text-indigo-700 dark:text-indigo-300 space-y-2.5 max-h-48 overflow-y-auto shadow-inner animate-fade-in text-left">
                                <div class="font-bold flex items-center justify-between border-b border-indigo-100 dark:border-indigo-900/40 pb-2">
                                    <span>{{ count($files) }} arquivos anexados:</span>
                                    <flux:button wire:click="$set('files', [])" size="xs" variant="ghost" color="indigo" class="font-extrabold">Limpar Fila</flux:button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($files as $index => $file)
                                        <div class="flex items-center justify-between bg-white/70 dark:bg-zinc-950/40 p-2 rounded-xl border border-indigo-50 dark:border-indigo-900/50 truncate shadow-sm">
                                            <div class="flex items-center gap-2 truncate">
                                                <flux:icon.document-text class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
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
                <x-loading target="files" variant="badge">
                    Enviando arquivos...
                </x-loading>

                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="save" class="font-bold">
                    <span wire:loading.remove wire:target="save">Solicitar Geração</span>
                    <span wire:loading wire:target="save">Processando...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
