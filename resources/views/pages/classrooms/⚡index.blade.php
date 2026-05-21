<?php

use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $subject = '';

    #[Validate('nullable|string|max:255')]
    public string $institution = '';

    #[Validate('nullable|string')]
    public string $description = '';

    public bool $showCreateModal = false;

    public function with(): array
    {
        return [
            'classrooms' => Auth::user()->classrooms()->withCount('students')->latest()->get(),
        ];
    }

    public function save()
    {
        $this->validate();

        Auth::user()->classrooms()->create([
            'name' => $this->name,
            'subject' => $this->subject,
            'institution' => $this->institution,
            'description' => $this->description,
        ]);

        $this->reset(['name', 'subject', 'institution', 'description', 'showCreateModal']);
        
        // This closes the modal using Flux macro
        $this->modal('create-classroom')->close();
        session()->flash('status', 'Turma criada com sucesso!');
    }

    public function goto($id)
    {
        return $this->redirect(route('classrooms.show', $id), navigate: true);
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <flux:heading size="xl">Turmas</flux:heading>
            <flux:subheading>Gerencie suas turmas e disciplinas</flux:subheading>
        </div>
        
        <flux:modal.trigger name="create-classroom">
            <flux:button variant="primary" icon="plus">Nova Turma</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($classrooms as $classroom)
            <flux:card class="flex flex-col h-full hover:border-indigo-500/50 transition-colors cursor-pointer" wire:click="goto({{ $classroom->id }})">
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <flux:heading size="lg">{{ $classroom->name }}</flux:heading>
                        <flux:badge color="zinc">{{ $classroom->students_count }} alunos</flux:badge>
                    </div>
                    
                    @if($classroom->subject)
                        <div class="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                            {{ $classroom->subject }}
                        </div>
                    @endif
                    
                    @if($classroom->institution)
                        <div class="mt-1 text-sm text-zinc-500 flex items-center gap-1">
                            <flux:icon.building-office class="size-4" />
                            {{ $classroom->institution }}
                        </div>
                    @endif
                </div>
                
                <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-end">
                    <flux:button href="{{ route('classrooms.show', $classroom) }}" variant="ghost" size="sm">Ver Turma</flux:button>
                </div>
            </flux:card>
        @empty
            <div class="col-span-full py-12 text-center text-zinc-500">
                <flux:icon.academic-cap class="size-12 mx-auto mb-4 text-zinc-400" />
                <p>Você ainda não cadastrou nenhuma turma.</p>
                <flux:modal.trigger name="create-classroom">
                    <flux:button variant="ghost" class="mt-4">Criar primeira turma</flux:button>
                </flux:modal.trigger>
            </div>
        @endforelse
    </div>

    <!-- Create Modal -->
    <flux:modal name="create-classroom" class="max-w-xl">
        <form wire:submit="save">
            <flux:heading size="lg" class="mb-6">Nova Turma</flux:heading>
            
            <div class="space-y-4">
                <flux:input wire:model="name" label="Nome da Turma" placeholder="Ex: 1º Ano A" required />
                
                <flux:input wire:model="subject" label="Disciplina (Opcional)" placeholder="Ex: Matemática" />
                
                <flux:input wire:model="institution" label="Instituição (Opcional)" placeholder="Ex: Colégio XYZ" />
                
                <flux:textarea wire:model="description" label="Descrição (Opcional)" placeholder="Detalhes adicionais sobre a turma..." />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Criar Turma</flux:button>
            </div>
        </form>
    </flux:modal>
</div>