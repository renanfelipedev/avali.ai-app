<?php

use App\Models\ExamGenerationRequest;
use App\Services\ExamGenerationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component
{
    use WithFileUploads;

    public $questions_count = 10;
    public $topics = '';
    public $files = [];

    protected $rules = [
        'questions_count' => 'required|integer|min:1|max:50',
        'topics' => 'required|string',
        'files.*' => 'nullable|file|max:10240', // 10MB max per file
    ];

    public function save(ExamGenerationService $service)
    {
        $this->validate();

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
            'questions_count' => $this->questions_count,
            'topics' => explode(',', $this->topics),
            'supporting_materials' => $storedFiles,
            'status' => 'pending',
        ]);

        // Processar a geração da prova via IA síncronamente
        $success = $service->generateExam($generationRequest);

        if ($success) {
            session()->flash('status', 'A Prova foi gerada com sucesso pela Inteligência Artificial!');
        } else {
            session()->flash('error', 'Houve um erro na comunicação com a IA para gerar a prova. Verifique os logs.');
        }

        return redirect()->route('home');
    }
};
?>

<div class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <div>
                <flux:heading size="lg">Detalhes da Prova</flux:heading>
                <flux:subheading>Informe os parâmetros para a IA gerar sua prova.</flux:subheading>
            </div>

            <flux:input 
                wire:model="questions_count" 
                label="Quantidade de Questões" 
                type="number" 
                placeholder="Ex: 10" 
                min="1" 
                max="50"
            />

            <flux:textarea 
                wire:model="topics" 
                label="Temas / Assuntos" 
                placeholder="Ex: Cálculo I, Derivadas, Integrais (separe por vírgula)" 
                rows="3"
            />

            <div>
                <flux:heading size="md" class="mb-2">Materiais de Apoio</flux:heading>
                <flux:subheading class="mb-4">Envie livros, artigos ou provas antigas (PDF, DOCX, Imagens até 10MB cada).</flux:subheading>
                
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
