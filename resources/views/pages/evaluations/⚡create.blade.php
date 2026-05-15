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

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'grading_criteria' => 'nullable|string',
            'answer_key' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx,doc,txt|max:10240',
            'exam_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx,doc,txt|max:10240',
            'student_submissions.*' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,doc,txt|max:51200',
        ];
    }

    public function save(SubmissionService $submissionService, App\Services\PdfConverterService $pdfConverter)
    {
        $this->validate();
        $this->isProcessing = true;

        // Store Answer Key and Convert to PDF
        $answerKeyPath = null;
        if ($this->answer_key) {
            $path = $this->answer_key->store('evaluations/answer_keys', 'public');
            $answerKeyPath = $pdfConverter->convertToPdf($path);
        }

        // Store Blank Exam and Convert to PDF
        $examFilePath = null;
        if ($this->exam_file) {
            $path = $this->exam_file->store('evaluations/exams', 'public');
            $examFilePath = $pdfConverter->convertToPdf($path);
        }

        // Create Evaluation
        $evaluation = ExamEvaluation::create([
            'user_id' => auth()->id(),
            'title' => $this->title,
            'grading_criteria' => $this->grading_criteria,
            'answer_key_file_path' => $answerKeyPath,
            'exam_file_path' => $examFilePath,
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
    <div class="mb-8">
        <flux:heading size="xl" class="mb-1">🚀 Nova Correção Automatizada</flux:heading>
        <flux:subheading>Siga as etapas abaixo para configurar a inteligência artificial para sua avaliação.
        </flux:subheading>
    </div>

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

            <flux:card
                class="border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-900/5">
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="mb-4 p-4 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600">
                        <flux:icon.cloud-arrow-up class="w-10 h-10" />
                    </div>
                    <flux:heading size="lg">Upload de Provas em Massa</flux:heading>
                    <flux:subheading class="max-w-md mx-auto mb-6">
                        Você pode selecionar várias imagens/PDFs de uma vez ou enviar um único arquivo **.ZIP** contendo
                        todas as provas.
                    </flux:subheading>

                    <div class="w-full max-w-sm">
                        <flux:input type="file" wire:model="student_submissions" multiple required
                            accept=".pdf,image/*,.zip,.docx,.doc,.txt" />
                    </div>
                </div>
            </flux:card>
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
