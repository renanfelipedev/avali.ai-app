<?php

use App\Models\Student;
use App\Services\StudentNameService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $institution = '';

    public array $selectedClassrooms = [];
    public bool $showCreateModal = false;
    public string $search = '';

    public function mount()
    {
        if (request()->boolean('criar') || request()->boolean('create') || request()->has('criar') || request()->has('create')) {
            $this->showCreateModal = true;
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $query = $user->students()->with('classrooms')->latest();

        if (!empty($this->search)) {
            $search = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('institution', 'like', $search)
                  ->orWhereHas('classrooms', function ($q2) use ($search) {
                      $q2->where('name', 'like', $search);
                  });
            });
        }

        return [
            'students' => $query->get(),
            'classrooms' => $user->classrooms()->orderBy('name')->get(),
        ];
    }

    public function save()
    {
        $this->validate();

        $name = app(StudentNameService::class)->fixAccentsAndCasing($this->name);

        $student = Auth::user()->students()->create([
            'name' => $name,
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'institution' => $this->institution ? trim($this->institution) : null,
        ]);

        if (!empty($this->selectedClassrooms)) {
            $student->classrooms()->sync($this->selectedClassrooms);
        }

        $this->reset(['name', 'email', 'institution', 'selectedClassrooms', 'showCreateModal']);
        session()->flash('status', 'Aluno cadastrado com sucesso!');
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <flux:heading size="xl">Todos os Alunos</flux:heading>
            <flux:subheading>Gerencie e visualize todos os alunos cadastrados nas suas turmas</flux:subheading>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <flux:button wire:click="$set('showCreateModal', true)" variant="primary" icon="plus" class="w-full sm:w-auto">
                Novo Aluno
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center gap-2">
            <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-6">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar aluno por nome, e-mail, instituição ou turma..." icon="magnifying-glass" clearable />
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
                                    <span class="text-zinc-400 text-xs italic">Sem turma vinculada</span>
                                @endforelse
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                            <flux:icon.users class="size-12 mx-auto mb-4 text-zinc-400" />
                            @if(!empty($search))
                                <p>Nenhum aluno encontrado para o termo pesquisado.</p>
                            @else
                                <p>Você ainda não tem alunos cadastrados.</p>
                                <p class="text-xs mt-1 mb-4">Adicione alunos individualmente ou faça importação em lote dentro de uma turma.</p>
                                <flux:button wire:click="$set('showCreateModal', true)" variant="ghost" icon="plus">Cadastrar Primeiro Aluno</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Create Student Modal -->
    <flux:modal wire:model="showCreateModal" class="max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Cadastrar Novo Aluno</flux:heading>
                <flux:subheading>Adicione as informações do aluno e vincule-o às turmas desejadas.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Nome Completo do Aluno" placeholder="Ex: Lucas Gabriel da Silva" icon="user" required />

                <flux:input wire:model="email" type="email" label="E-mail (Opcional)" placeholder="aluno@instituicao.edu.br" icon="envelope" />

                <flux:input wire:model="institution" label="Instituição (Opcional)" placeholder="Ex: Colégio Anglo ou UFRJ" icon="building-office" />

                <div class="space-y-3">
                    <flux:label>Vincular a Turmas (Opcional)</flux:label>
                    @if(count($classrooms) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-48 overflow-y-auto p-2 border border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-900/50">
                            @foreach ($classrooms as $classroom)
                                <flux:checkbox wire:model="selectedClassrooms" value="{{ $classroom->id }}"
                                    label="{{ $classroom->name }}" />
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-zinc-500">Você ainda não tem turmas cadastradas. O aluno será salvo sem turma e poderá ser vinculado depois.</p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" wire:click="$set('showCreateModal', false)" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Cadastrar Aluno</flux:button>
            </div>
        </form>
    </flux:modal>
</div>