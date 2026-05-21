<?php

use App\Models\Exam;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'exams' => auth()->user()
                ->exams()
                ->latest()
                ->paginate(10),
        ];
    }

    public function deleteExam(Exam $exam)
    {
        if ($exam->user_id === auth()->id()) {
            $exam->delete();
            session()->flash('status', 'Prova excluída com sucesso.');
        }
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <flux:heading size="xl">Provas Geradas pela IA</flux:heading>
        <flux:button href="{{ route('exams.create') }}" variant="primary" icon="plus" class="w-full sm:w-auto">Solicitar Nova Prova</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <div class="overflow-x-auto w-full scrollbar-none">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Título / Tópicos</flux:table.column>
                    <flux:table.column>Data de Geração</flux:table.column>
                    <flux:table.column>Ações</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($exams as $exam)
                        <flux:table.row>
                            <flux:table.cell>
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $exam->title }}</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $exam->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button href="{{ route('exams.show', $exam) }}" size="sm" variant="ghost" icon="eye" />
                                    <flux:button wire:click="deleteExam({{ $exam->id }})" wire:confirm="Tem certeza que deseja excluir esta prova?" size="sm" variant="ghost" icon="trash" color="danger" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center py-6 text-zinc-500">
                                Nenhuma prova gerada ainda. Solicite uma nova prova para começar!
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-4">
            {{ $exams->links() }}
        </div>
    </flux:card>
</div>
