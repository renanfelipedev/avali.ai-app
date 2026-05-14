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
        }
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Provas Geradas pela IA</flux:heading>
        <flux:button href="{{ route('exams.create') }}" variant="primary" icon="plus">Solicitar Nova Prova</flux:button>
    </div>

    @if (session('status'))
        <div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <flux:card>
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
                            <div class="flex space-x-2">
                                <flux:button size="sm" variant="ghost" href="{{ route('exams.show', $exam) }}">Visualizar</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="deleteExam({{ $exam->id }})" wire:confirm="Tem certeza que deseja excluir esta prova?" class="text-red-600 hover:text-red-700">Excluir</flux:button>
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

        <div class="mt-4">
            {{ $exams->links() }}
        </div>
    </flux:card>
</div>
