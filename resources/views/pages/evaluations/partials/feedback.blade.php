@php
    $type = $viewing_submission->evaluation->type ?? 'exam';
    $isExam = $type === 'exam';
    $isText = in_array($type, ['essay', 'article']);
@endphp

@if($isEditingFeedback)
    <div class="space-y-6 pr-2 max-h-[60vh] overflow-y-auto">
        @if ($isExam && is_array($editing_feedback_data))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($editing_feedback_data as $index => $q)
                    <flux:card class="p-5 border border-zinc-200 dark:border-zinc-800 flex flex-col justify-between space-y-4 shadow-sm hover:shadow transition-shadow">
                        <div class="flex justify-between items-center pb-3 border-b border-zinc-100 dark:border-zinc-800/80">
                            <div class="font-bold text-zinc-800 dark:text-zinc-200">Questão {{ $q['question_number'] ?? ($index + 1) }}</div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-zinc-500">Nota:</span>
                                <flux:input type="number" step="0.01" min="0" wire:model="editing_feedback_data.{{ $index }}.grade" class="w-20 font-mono text-sm" />
                            </div>
                        </div>
                        <div class="flex-1 space-y-3.5">
                            @if(!empty($q['student_answer']))
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Resposta do Aluno:</div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-950 p-3 rounded-xl border border-zinc-100 dark:border-zinc-900/50 mt-1 italic leading-relaxed">
                                        "{{ $q['student_answer'] }}"
                                    </p>
                                </div>
                            @endif
                            <div>
                                <flux:textarea wire:model="editing_feedback_data.{{ $index }}.feedback" label="Feedback da Correção:" rows="3" class="text-sm" />
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @elseif ($isText)
            <div>
                <flux:heading size="md" class="mb-4">Editar Parecer Geral</flux:heading>
                <flux:textarea wire:model="editing_feedback_data" rows="8" placeholder="Escreva o parecer geral..." />
            </div>
        @endif
    </div>
@else
    <div class="space-y-8 pr-2 max-h-[60vh] overflow-y-auto">
        @if ($isExam && is_array($viewing_submission->feedback_data))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($viewing_submission->feedback_data as $q)
                    <flux:card class="p-5 border border-zinc-100 dark:border-zinc-800 flex flex-col justify-between space-y-4 shadow-sm hover:shadow transition-shadow">
                        <div class="flex justify-between items-center pb-3 border-b border-zinc-100 dark:border-zinc-800/80">
                            <div class="font-bold text-zinc-800 dark:text-zinc-200">Questão {{ $q['question_number'] ?? 'N/A' }}</div>
                            <flux:badge color="indigo" size="sm" class="font-mono px-3 py-1 text-sm font-bold">Nota: {{ $q['grade'] ?? 0 }}</flux:badge>
                        </div>
                        <div class="flex-1 space-y-3.5">
                            @if(!empty($q['student_answer']))
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Resposta do Aluno:</div>
                                    <p class="text-sm text-justify text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-950 p-3 rounded-xl border border-zinc-100 dark:border-zinc-900/50 mt-1 italic leading-relaxed">
                                        "{{ $q['student_answer'] }}"
                                    </p>
                                </div>
                            @endif
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Feedback da Correção IA:</div>
                                <p class="text-sm text-justify text-zinc-700 dark:text-zinc-300 leading-relaxed mt-1 font-medium">
                                    {{ trim($q['feedback'] ?? 'Sem feedback fornecido.') }}
                                </p>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @elseif ($isText)
            <!-- Estrutura/Competências Extraídas -->
            @if (!empty($viewing_submission->metadata) && is_array($viewing_submission->metadata))
                <div>
                    <flux:heading size="lg" class="mb-4">Análise Estrutural</flux:heading>
                    <div class="space-y-4">
                        @if (isset($viewing_submission->metadata['sections_feedback']) && is_array($viewing_submission->metadata['sections_feedback']))
                            @foreach ($viewing_submission->metadata['sections_feedback'] as $section)
                                @if (!empty($section['student_text']) || !empty($section['feedback']))
                                    <flux:card class="border-zinc-200 dark:border-zinc-800 shadow-sm">
                                        <flux:heading size="sm" class="uppercase tracking-wider mb-3 text-indigo-600 dark:text-indigo-400">
                                            {{ str_replace('_', ' ', $section['section_name'] ?? 'Seção') }}
                                        </flux:heading>
                                        
                                        @if(!empty($section['student_text']))
                                            <div class="mb-4">
                                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">Trecho do Aluno:</div>
                                                <div class="text-sm text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-950 p-3 rounded-xl border border-zinc-100 dark:border-zinc-900/50 italic text-justify leading-relaxed">
                                                    "{!! nl2br(e($section['student_text'])) !!}"
                                                </div>
                                            </div>
                                        @endif
                                        
                                        @if(!empty($section['feedback']))
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-widest text-indigo-500 mb-1">Feedback IA:</div>
                                                <div class="text-sm text-zinc-700 dark:text-zinc-300 text-justify leading-relaxed font-medium">
                                                    {!! nl2br(e($section['feedback'])) !!}
                                                </div>
                                            </div>
                                        @endif
                                    </flux:card>
                                @endif
                            @endforeach
                        @else
                            <!-- FORMATO ANTIGO: Raw Text -->
                            @foreach ($viewing_submission->metadata as $section_name => $section_content)
                                @if (!empty($section_content) && is_string($section_content))
                                    <flux:card class="border-zinc-200 dark:border-zinc-800 shadow-sm">
                                        <flux:heading size="sm" class="uppercase tracking-wider mb-2 text-indigo-600 dark:text-indigo-400">
                                            {{ str_replace('_', ' ', $section_name) }}
                                        </flux:heading>
                                        <div class="text-sm text-zinc-700 dark:text-zinc-300 text-justify leading-relaxed">
                                            {!! nl2br(e($section_content)) !!}
                                        </div>
                                    </flux:card>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
                
                <flux:separator variant="subtle" class="my-6" />
            @endif

            <!-- Parecer Geral Conclusivo -->
            @if (!empty($viewing_submission->feedback_data) && is_string($viewing_submission->feedback_data))
                <div>
                    <flux:heading size="lg" class="mb-4 text-zinc-800 dark:text-zinc-200">Parecer Geral Conclusivo</flux:heading>
                    <flux:card class="border-indigo-100 bg-indigo-50/50 dark:border-indigo-900/30 dark:bg-indigo-950/20 shadow-sm">
                        <div class="text-sm text-zinc-800 dark:text-zinc-200 text-justify leading-relaxed font-medium">
                            {!! nl2br(e($viewing_submission->feedback_data)) !!}
                        </div>
                    </flux:card>
                </div>
            @endif
        @endif
    </div>
@endif
