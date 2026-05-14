<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\User;
use App\Models\Role;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.main')] class extends Component {
    use WithPagination;

    public $name = '';
    public $email = '';
    public $password = '';
    public $is_active = true;
    public $selectedRoles = [];
    public ?User $editingUser = null;

    public function createUser()
    {
        $this->reset(['name', 'email', 'password', 'selectedRoles', 'editingUser']);
        $this->is_active = true;
        $this->dispatch('modal-show', name: 'user-modal');
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->is_active = (bool) $user->is_active;
        $this->selectedRoles = $user->roles->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $this->dispatch('modal-show', name: 'user-modal');
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->editingUser?->id)],
            'selectedRoles' => 'required|array|min:1',
        ];

        if (!$this->editingUser) {
            $rules['password'] = 'required|min:8';
        } else {
            $rules['password'] = 'nullable|min:8';
        }

        $validated = $this->validate($rules);

        if ($this->editingUser) {
            $this->editingUser->update([
                'name' => $this->name,
                'email' => $this->email,
                'is_active' => $this->is_active,
            ]);

            if ($this->password) {
                $this->editingUser->update(['password' => Hash::make($this->password)]);
            }

            $user = $this->editingUser;
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'is_active' => $this->is_active,
            ]);
        }

        $user->roles()->sync($this->selectedRoles);

        $this->dispatch('modal-close', name: 'user-modal');
        session()->flash('status', $this->editingUser ? 'Usuário atualizado com sucesso.' : 'Usuário criado com sucesso.');

        $this->reset(['name', 'email', 'password', 'selectedRoles', 'editingUser']);
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Você não pode excluir a si mesmo.');
            return;
        }

        $user->delete();
        session()->flash('status', 'Usuário excluído com sucesso.');
    }

    public function with()
    {
        return [
            'users' => User::with('roles')->paginate(10),
            'allRoles' => Role::all(),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Gerenciamento de Usuários</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="createUser">Adicionar Usuário</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>E-mail</flux:table.column>
                <flux:table.column>Perfil</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->roles as $role)
                                    <flux:badge size="sm" color="zinc">
                                        {{ $role->name }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->is_active)
                                <flux:badge size="sm" color="green">Ativo</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Inativo</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex space-x-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    wire:click="editUser({{ $user->id }})"></flux:button>
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Tem certeza que deseja excluir este usuário?"
                                    class="text-red-600 hover:text-red-700"></flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-6 text-zinc-500">
                            Nenhum usuário encontrado.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </flux:card>

    <flux:modal name="user-modal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingUser ? 'Editar Usuário' : 'Novo Usuário' }}</flux:heading>
                <flux:subheading>Preencha as informações do usuário abaixo.</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:input label="Nome" wire:model="name" placeholder="Nome completo" />

                <flux:input label="E-mail" type="email" wire:model="email" placeholder="email@exemplo.com" />

                <flux:input label="Senha" type="password" wire:model="password"
                    placeholder="{{ $editingUser ? 'Deixe em branco para manter a atual' : 'Mínimo 8 caracteres' }}" />

                <div class="space-y-3">
                    <flux:label>Perfis</flux:label>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach ($allRoles as $role)
                            <flux:checkbox wire:model="selectedRoles" value="{{ $role->id }}"
                                label="{{ $role->name }}" />
                        @endforeach
                    </div>
                    @error('selectedRoles')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <flux:switch wire:model="is_active" label="Usuário Ativo" />

                <div class="flex justify-end space-x-3 mt-6">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Salvar</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
