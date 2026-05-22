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

    public function downloadSource()
    {
        if (!empty($this->exam->content_json)) {
            return response()->streamDownload(
                fn () => print(json_encode($this->exam->content_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                Str::slug($this->exam->title) . '.json'
            );
        }

        if (!empty($this->exam->file_path) && Storage::disk('public')->exists($this->exam->file_path)) {
            return Storage::disk('public')->download($this->exam->file_path, $this->exam->original_name);
        }

        session()->flash('error', 'Conteúdo da prova não encontrado.');
    }



    public function with(): array
    {
        $content = '';
        $jsonData = null;
        $isJson = $this->exam->mime_type === 'application/json';

        if (!empty($this->exam->content_json) || $this->exam->mime_type === 'application/json') {
            $isJson = true;
            $jsonData = $this->exam->parsed_json_data;
        } elseif (!empty($this->exam->file_path) && Storage::disk('public')->exists($this->exam->file_path)) {
            $rawContent = Storage::disk('public')->get($this->exam->file_path);
            $content = Str::markdown($rawContent);
        } else {
            $content = '<p class="text-red-500">O conteúdo desta prova não foi encontrado.</p>';
        }

        return [
            'htmlContent' => $content,
            'jsonData' => $jsonData,
            'isJson' => $isJson,
            'printProfiles' => auth()->user()->printProfiles()->latest()->get()
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <flux:heading size="xl">{{ $exam->title }}</flux:heading>
            <flux:subheading>Gerada em {{ $exam->created_at->format('d/m/Y H:i') }}</flux:subheading>
        </div>
        <div class="flex flex-col sm:flex-row w-full sm:w-auto gap-3">
            <flux:button href="{{ route('exams.index') }}" variant="ghost" icon="arrow-left" class="w-full sm:w-auto">Voltar</flux:button>
            <flux:button wire:click="downloadSource" variant="filled" color="zinc" icon="arrow-down-tray" class="w-full sm:w-auto">
                {{ (!empty($exam->content_json) || $exam->mime_type === 'application/json') ? 'Baixar JSON' : 'Baixar Markdown' }}
            </flux:button>
            <flux:dropdown>
                <flux:button variant="primary" icon="document" class="w-full sm:w-auto">
                    Baixar PDF
                </flux:button>
                <flux:menu>
                    <flux:menu.item href="{{ route('exams.pdf', $exam->id) }}" icon="document-text">
                        Padrão (Avali.AI)
                    </flux:menu.item>
                    @foreach($printProfiles as $profile)
                        <flux:menu.item href="{{ route('exams.pdf', ['exam' => $exam->id, 'profile' => $profile->id]) }}" icon="building-office-2">
                            {{ $profile->institution_name }}
                        </flux:menu.item>
                    @endforeach
                    <flux:menu.separator />
                    <flux:menu.item href="{{ route('exams.settings') }}" icon="cog-6-tooth">
                        Gerenciar Perfis
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <flux:card>
        @if($isJson && $jsonData)
            <div class="space-y-12">
                @if(isset($jsonData['objective_questions']) && count($jsonData['objective_questions']) > 0)
                    <section class="space-y-8">
                        <flux:heading size="lg" class="border-b pb-2">Questões Objetivas</flux:heading>
                        
                        @foreach($jsonData['objective_questions'] as $q)
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ $q['number'] ?? $loop->iteration }}.</span>
                                    <div class="text-lg font-medium text-zinc-800 dark:text-zinc-200 leading-relaxed">
                                        {{ $q['text'] ?? '' }}
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 gap-2 pl-8">
                                    @foreach($q['options'] ?? [] as $key => $option)
                                        <div class="flex items-start gap-3 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                                            <span class="font-bold text-zinc-500 uppercase">{{ $key }})</span>
                                            <span class="text-zinc-700 dark:text-zinc-300">{{ $option }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if(isset($jsonData['discursive_questions']) && count($jsonData['discursive_questions']) > 0)
                    <section class="space-y-8">
                        <flux:heading size="lg" class="border-b pb-2">Questões Discursivas</flux:heading>
                        
                        @foreach($jsonData['discursive_questions'] as $q)
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ $q['number'] ?? $loop->iteration }}.</span>
                                    <div class="text-lg font-medium text-zinc-800 dark:text-zinc-200 leading-relaxed">
                                        {{ $q['text'] ?? '' }}
                                    </div>
                                </div>
                                <div class="pl-8">
                                    <div class="h-32 w-full border-b-2 border-dotted border-zinc-300 dark:border-zinc-700"></div>
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif

                <section class="mt-16 pt-8 border-t border-dashed border-zinc-300 dark:border-zinc-700">
                    <flux:heading size="lg" class="mb-6">Gabarito Sugerido</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($jsonData['objective_questions'] ?? [] as $q)
                            <div class="flex gap-2 text-sm">
                                <span class="font-bold">Questão {{ $q['number'] ?? $loop->iteration }}:</span>
                                <span class="uppercase text-emerald-600 dark:text-emerald-400 font-bold">Alternativa {{ $q['answer'] ?? 'N/A' }}</span>
                            </div>
                        @endforeach
                    </div>
                    @foreach($jsonData['discursive_questions'] ?? [] as $q)
                        <div class="mt-4 text-sm">
                            <div class="font-bold mb-1">Questão {{ $q['number'] ?? $loop->iteration }} (Critérios):</div>
                            <p class="text-zinc-600 dark:text-zinc-400 italic">{{ $q['answer_key'] ?? 'N/A' }}</p>
                        </div>
                    @endforeach
                </section>
            </div>
        @else
            <!-- Conteúdo Renderizado da Prova (Legado Markdown) -->
            <div class="prose prose-zinc dark:prose-invert max-w-none 
                prose-h3:mt-12 prose-h3:mb-6 prose-h3:border-b prose-h3:pb-2 prose-h3:border-zinc-200 dark:prose-h3:border-zinc-800
                prose-p:text-zinc-700 dark:prose-p:text-zinc-300
                prose-li:my-2 prose-ul:list-none prose-ul:pl-0
                [&_ul_li]:flex [&_ul_li]:gap-2">
                {!! $htmlContent !!}
            </div>
        @endif
    </flux:card>
</div>
