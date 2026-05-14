<?php

use App\Models\ExamGenerationRequest;
use App\Services\ExamGenerationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component {
    use WithFileUploads;

    public $objective_count = 5;
    public $discursive_count = 2;
    public $topics = '';
    public $files = [];

    protected $rules = [
        'objective_count' => 'required|integer|min:0|max:50',
        'discursive_count' => 'required|integer|min:0|max:50',
        'topics' => 'required|string',
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
            'questions_count' => $this->objective_count + $this->discursive_count,
            'objective_count' => $this->objective_count,
            'discursive_count' => $this->discursive_count,
            'topics' => explode(',', $this->topics),
            'supporting_materials' => $storedFiles,
            'status' => 'pending',
        ]);

        // Dispatch background job
        \App\Jobs\GenerateExamJob::dispatch($generationRequest);

        session()->flash('status', 'A solicitação de geração de prova foi enviada para processamento em segundo plano.');

        return redirect()->route('tasks.index');
    }
};
?>

<div class="">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <div>
                <flux:heading size="lg">Detalhes da Prova</flux:heading>
                <flux:subheading>Informe os parâmetros para a IA gerar sua prova.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="objective_count" label="Questões Objetivas" type="number" placeholder="Ex: 5"
                    min="0" max="50" />
                <flux:input wire:model="discursive_count" label="Questões Discursivas" type="number"
                    placeholder="Ex: 2" min="0" max="50" />
            </div>

            <flux:textarea wire:model="topics" label="Temas / Assuntos"
                placeholder="Ex: Cálculo I, Derivadas, Integrais (separe por vírgula)" rows="3" />

            <div>
                <flux:heading size="md" class="mb-2">Materiais de Apoio</flux:heading>
                <flux:subheading class="mb-4">Envie livros, artigos ou provas antigas (PDF, DOCX, Imagens até 10MB
                    cada).</flux:subheading>

                <flux:input type="file" wire:model="files" multiple accept=".pdf,.docx,image/*" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" href="{{ route('home') }}">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Solicitar Geração</span>
                    <span wire:loading>Processando...</span>
                </flux:button>
            </div>
        </flux:card>
    </form>
</div>
