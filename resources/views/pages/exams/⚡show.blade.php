<?php

use App\Models\Exam;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    public Exam $exam;

    public function mount(Exam $exam)
    {
        if ($exam->user_id != auth()->id()) {
            abort(403);
        }
        $this->exam = $exam;
    }

    public function downloadMarkdown()
    {
        if (Storage::disk('public')->exists($this->exam->file_path)) {
            return Storage::disk('public')->download($this->exam->file_path, $this->exam->original_name);
        }

        session()->flash('error', 'Arquivo não encontrado.');
    }

    public function with(): array
    {
        $content = '';
        if (Storage::disk('public')->exists($this->exam->file_path)) {
            $markdown = Storage::disk('public')->get($this->exam->file_path);
            $content = Str::markdown($markdown);
        } else {
            $content = '<p class="text-red-500">O arquivo Markdown desta prova não foi encontrado no servidor.</p>';
        }

        return [
            'htmlContent' => $content,
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ $exam->title }}</flux:heading>
            <flux:subheading>Gerada em {{ $exam->created_at->format('d/m/Y H:i') }}</flux:subheading>
        </div>
        <div class="flex space-x-3">
            <flux:button href="{{ route('exams.index') }}" variant="ghost" icon="arrow-left">Voltar</flux:button>
            <flux:button wire:click="downloadMarkdown" variant="primary" icon="arrow-down-tray">Baixar Markdown</flux:button>
        </div>
    </div>

    @if (session('error'))
        <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <flux:card>
        <!-- Conteúdo Renderizado da Prova -->
        <div class="prose prose-zinc dark:prose-invert max-w-none">
            {!! $htmlContent !!}
        </div>
    </flux:card>
</div>
