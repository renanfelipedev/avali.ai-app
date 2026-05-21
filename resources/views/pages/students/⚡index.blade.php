<?php

use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    public function with(): array
    {
        return [
            'students' => Auth::user()->students()->with('classrooms')->latest()->get(),
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <flux:heading size="xl">Todos os Alunos</flux:heading>
            <flux:subheading>Visão geral de todos os seus alunos cadastrados</flux:subheading>
        </div>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>Instituição</flux:table.column>
                <flux:table.column>Turmas</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse($students as $student)
                    <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $student->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $student->email ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $student->institution ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse($student->classrooms as $classroom)
                                    <flux:badge size="sm" color="zinc">{{ $classroom->name }}</flux:badge>
                                @empty
                                    <span class="text-zinc-400">-</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                            <flux:icon.users class="size-12 mx-auto mb-4 text-zinc-400" />
                            <p>Você ainda não tem alunos cadastrados.</p>
                            <p class="text-xs mt-1">Adicione alunos diretamente dentro de uma <a href="{{ route('classrooms.index') }}" class="text-indigo-600 hover:underline">Turma</a>.</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>