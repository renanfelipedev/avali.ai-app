<?php

use App\Models\ExamEvaluation;
use App\Services\SubmissionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component
{
    use WithFileUploads;

    public $title = '';
    public $grading_criteria = '';
    public $answer_key;
    public $student_submissions = [];
    public $isProcessing = false;

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'grading_criteria' => 'nullable|string',
            'answer_key' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240', // 10MB
            'student_submissions.*' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,txt|max:51200', // 50MB per file
        ];
    }

    public function save(SubmissionService $submissionService)
    {
        $this->validate();
        $this->isProcessing = true;

        // Store Answer Key only if provided
        $answerKeyPath = $this->answer_key ? $this->answer_key->store('evaluations/answer_keys', 'public') : null;

        // Create Evaluation
        $evaluation = ExamEvaluation::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'grading_criteria' => $this->grading_criteria,
            'answer_key_file_path' => $answerKeyPath,
            'status' => 'processing',
        ]);

        // Delegate complex processing to service (DRY & KISS)
        $submissionService->processUploads($evaluation, $this->student_submissions);

        session()->flash('status', 'A correção das provas foi iniciada em segundo plano.');

        return redirect()->route('tasks.index');
    }
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Nova Correção de Provas</flux:heading>
        <flux:subheading>Envie o gabarito e as provas dos alunos para iniciar a avaliação automatizada com IA.</flux:subheading>
    </div>

    <form wire:submit="save">
        <flux:card>
            <div class="space-y-6">
                <flux:input wire:model="title" label="Título da Avaliação" placeholder="Ex: Prova de Matemática - 1º Bimestre" required />

                <flux:textarea wire:model="grading_criteria" label="Critérios de Avaliação (Opcional)" placeholder="Ex: Cada questão vale 2 pontos. Considere acertos parciais caso a fórmula esteja correta mas o resultado final errado." rows="3" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:heading size="md" class="mb-2">Documento A: Gabarito (Opcional)</flux:heading>
                        <flux:subheading class="mb-4">Envie o PDF/imagem com as respostas ou deixe a IA corrigir de forma autônoma.</flux:subheading>
                        
                        <flux:input type="file" wire:model="answer_key" accept=".pdf,image/*" />
                    </div>

                    <div>
                        <flux:heading size="md" class="mb-2">Documentos B: Provas dos Alunos</flux:heading>
                        <flux:subheading class="mb-4">Selecione uma ou mais provas (PDF/Imagens/DOCX/TXT) ou envie um arquivo .ZIP contendo todas as provas.</flux:subheading>
                        
                        <flux:input type="file" wire:model="student_submissions" multiple required accept=".pdf,image/*,.zip,.docx,.txt" />
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <flux:button href="{{ route('evaluations.index') }}" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Iniciar Correção Automatizada</span>
                    <span wire:loading wire:target="save">Processando com IA...</span>
                </flux:button>
            </div>
        </flux:card>
    </form>
</div>