<?php

use App\Models\ExamEvaluation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'evaluations' => auth()->user()->examEvaluations()->latest()->paginate(10),
        ];
    }

    public function deleteEvaluation(ExamEvaluation $evaluation)
    {
        if ($evaluation->user_id !== auth()->id()) {
            return;
        }

        $evaluation->delete();
    }
};
?>

<div @if ($evaluations->where('status', 'processing')->count() > 0) wire:poll.5s @endif>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Correção de Provas</flux:heading>
        <flux:button href="{{ route('evaluations.create') }}" variant="primary">Nova Correção</flux:button>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Título</flux:table.column>
                <flux:table.column>Provas</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($evaluations as $evaluation)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $evaluation->title }}</div>
                            <div class="text-sm text-zinc-500">{{ Str::limit($evaluation->grading_criteria, 50) }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $evaluation->submissions()->count() }} submissões
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($evaluation->status === 'completed')
                                <flux:badge color="success" size="sm">Concluído</flux:badge>
                            @elseif($evaluation->status === 'processing')
                                <flux:badge color="indigo" size="sm" class="animate-pulse">Processando</flux:badge>
                            @elseif($evaluation->status === 'error')
                                <flux:badge color="red" size="sm">Erro</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Pendente</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $evaluation->created_at->format('d/m/Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button href="{{ route('evaluations.show', $evaluation) }}" size="sm"
                                    variant="ghost" icon="eye" />
                                <flux:button wire:click="deleteEvaluation({{ $evaluation->id }})"
                                    wire:confirm="Tem certeza?" size="sm" variant="ghost" color="danger"
                                    icon="trash" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                            Nenhuma correção criada ainda.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $evaluations->links() }}
        </div>
    </flux:card>
</div>
