<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\User;
use App\Models\Role;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Enums\UserRole;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Flux\Flux;

new #[Layout('layouts.main')] class extends Component {
    use WithPagination;

    public $name = '';
    public $email = '';
    public $password = '';
    public $is_active = true;
    public $selectedRoles = [];
    public ?int $selectedPlanId = null;
    public ?User $editingUser = null;
    public $showUserModal = false;

    // Modal de alteração rápida de plano
    public ?User $managingPlanUser = null;
    public ?int $newPlanId = null;
    public $showPlanModal = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin'), 403);
    }

    public function createUser()
    {
        $this->reset(['name', 'email', 'password', 'selectedRoles', 'selectedPlanId', 'editingUser']);
        $this->is_active = SystemSetting::getBool('auto_activate_users', false);

        $teacherRole = Role::where('slug', UserRole::TEACHER->value)->first();
        if ($teacherRole) {
            $this->selectedRoles = [(string) $teacherRole->id];
        }

        $freePlan = Plan::free();
        $this->selectedPlanId = $freePlan?->id;

        $this->showUserModal = true;
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->is_active = (bool) $user->is_active;
        $this->selectedPlanId = $user->currentPlan()->id;

        $studentRole = Role::where('slug', UserRole::STUDENT->value)->first();
        $userRoles = $user->roles;
        if ($studentRole) {
            $userRoles = $userRoles->where('id', '!=', $studentRole->id);
        }

        $this->selectedRoles = $userRoles->pluck('id')->map(fn($id) => (string) $id)->toArray();

        if (empty($this->selectedRoles)) {
            $teacherRole = Role::where('slug', UserRole::TEACHER->value)->first();
            if ($teacherRole) {
                $this->selectedRoles = [(string) $teacherRole->id];
            }
        }

        $this->showUserModal = true;
    }

    public function openPlanModal(User $user)
    {
        $this->managingPlanUser = $user;
        $this->newPlanId = $user->currentPlan()->id;
        $this->showPlanModal = true;
    }

    public function updatePlan()
    {
        if (! $this->managingPlanUser || ! $this->newPlanId) {
            return;
        }

        $plan = Plan::findOrFail($this->newPlanId);

        Subscription::updateOrCreate(
            ['user_id' => $this->managingPlanUser->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'gateway' => 'admin_manual',
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]
        );

        Flux::toast(
            heading: 'Plano Atualizado!',
            text: "O plano de {$this->managingPlanUser->name} foi alterado para {$plan->name}."
        );

        $this->showPlanModal = false;
        $this->managingPlanUser = null;
        $this->newPlanId = null;
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

        $this->validate($rules);

        // Descontinuar perfil de aluno: nunca atribuir este perfil
        $studentRole = Role::where('slug', UserRole::STUDENT->value)->first();
        if ($studentRole) {
            $this->selectedRoles = array_values(array_filter(
                $this->selectedRoles,
                fn ($id) => (string) $id !== (string) $studentRole->id
            ));
        }

        // Restringir perfil de administrador: somente usuários administradores podem conceder
        $adminRole = Role::where('slug', UserRole::ADMIN->value)->first();
        if ($adminRole && in_array((string) $adminRole->id, $this->selectedRoles, true)) {
            if (! auth()->user()?->isAdmin()) {
                abort(403, 'Apenas administradores podem atribuir o perfil de Administrador.');
            }
        }

        // Se editingUser for admin e o usuário autenticado não for admin, bloquear alteração
        if ($this->editingUser && $this->editingUser->isAdmin() && ! auth()->user()?->isAdmin()) {
            abort(403, 'Apenas administradores podem gerenciar usuários administradores.');
        }

        if (empty($this->selectedRoles)) {
            $teacherRole = Role::where('slug', UserRole::TEACHER->value)->first();
            if ($teacherRole) {
                $this->selectedRoles = [(string) $teacherRole->id];
            }
        }

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

        if ($this->selectedPlanId) {
            $plan = Plan::find($this->selectedPlanId);
            if ($plan) {
                Subscription::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'gateway' => 'admin_manual',
                        'current_period_start' => now(),
                        'current_period_end' => now()->addYear(),
                    ]
                );
            }
        }

        session()->flash('status', $this->editingUser ? 'Usuário atualizado com sucesso.' : 'Usuário criado com sucesso.');

        $this->reset(['name', 'email', 'password', 'selectedRoles', 'selectedPlanId', 'editingUser', 'showUserModal']);
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            session()->flash('error', 'Você não pode excluir a si mesmo.');
            return;
        }

        if ($user->isAdmin() && ! auth()->user()?->isAdmin()) {
            abort(403, 'Apenas administradores podem excluir usuários administradores.');
        }

        $user->delete();
        session()->flash('status', 'Usuário excluído com sucesso.');
    }

    public function with()
    {
        return [
            'users' => User::with(['roles', 'subscriptions.plan'])->orderBy('name', 'asc')->paginate(10),
            'allRoles' => Role::where('slug', '!=', UserRole::STUDENT->value)->orderBy('name', 'asc')->get(),
            'allPlans' => Plan::where('is_active', true)->orderBy('sort_order', 'asc')->get(),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Gerenciamento de Usuários</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="createUser">Adicionar Usuário</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <div class="overflow-x-auto w-full scrollbar-none">
            <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>E-mail</flux:table.column>
                <flux:table.column>Perfil</flux:table.column>
                <flux:table.column>Plano</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    @php
                        $userPlan = $user->currentPlan();
                        $badgeColor = match($userPlan->slug) {
                            'school' => 'purple',
                            'pro' => 'indigo',
                            default => 'zinc',
                        };
                    @endphp
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
                            <button wire:click="openPlanModal({{ $user->id }})" type="button" class="group inline-flex items-center gap-1.5 focus:outline-none">
                                <flux:badge size="sm" :color="$badgeColor" class="cursor-pointer group-hover:opacity-80 transition-opacity">
                                    <flux:icon.credit-card class="w-3 h-3 me-1" />
                                    {{ $userPlan->name }}
                                </flux:badge>
                            </button>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->is_active)
                                <flux:badge size="sm" color="green">Ativo</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Inativo</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button wire:click="openPlanModal({{ $user->id }})" title="Alterar Plano" size="sm" variant="ghost" icon="credit-card" />
                                <flux:button wire:click="editUser({{ $user->id }})" title="Editar Usuário" size="sm" variant="ghost" icon="pencil-square" />
                                <flux:button wire:click="deleteUser({{ $user->id }})" wire:confirm="Tem certeza que deseja excluir este usuário?" size="sm" variant="ghost" icon="trash" color="danger" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-6 text-zinc-500">
                            Nenhum usuário encontrado.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </flux:card>

    <flux:modal wire:model="showUserModal" class="md:w-[500px]">
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
                            @php
                                $isAdminRole = $role->slug === \App\Enums\UserRole::ADMIN->value;
                                $canAssign = ! $isAdminRole || (auth()->user()?->isAdmin() ?? false);
                            @endphp
                            <flux:checkbox wire:model="selectedRoles" value="{{ $role->id }}"
                                label="{{ $role->name }}" :disabled="!$canAssign" />
                        @endforeach
                    </div>
                    @error('selectedRoles')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <flux:select wire:model="selectedPlanId" label="Plano do Usuário">
                        @foreach ($allPlans as $plan)
                            <flux:select.option value="{{ $plan->id }}">
                                {{ $plan->name }} ({{ $plan->formattedPrice() }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
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

    <!-- MODAL DE ALTERAÇÃO RÁPIDA DE PLANO -->
    <flux:modal wire:model="showPlanModal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Alterar Plano de Usuário</flux:heading>
                <flux:subheading>
                    Atualize a assinatura e libere cotas e recursos para 
                    <strong class="text-zinc-900 dark:text-white">{{ $managingPlanUser?->name }}</strong>.
                </flux:subheading>
            </div>

            @if ($managingPlanUser)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60 text-sm space-y-1">
                    <div class="flex justify-between items-center">
                        <span class="text-zinc-500">Plano Atual:</span>
                        <flux:badge size="sm" color="indigo" class="font-bold">{{ $managingPlanUser->currentPlan()->name }}</flux:badge>
                    </div>
                    <div class="flex justify-between items-center text-xs text-zinc-500 pt-1">
                        <span>Uso no Mês Atual:</span>
                        <span>{{ $managingPlanUser->getCurrentMonthExamsCount() }} provas / {{ $managingPlanUser->getCurrentMonthSubmissionsCount() }} correções</span>
                    </div>
                </div>
            @endif

            <form wire:submit="updatePlan" class="space-y-4">
                <flux:select wire:model="newPlanId" label="Selecione o Novo Plano" required>
                    @foreach ($allPlans as $plan)
                        <flux:select.option value="{{ $plan->id }}">
                            {{ $plan->name }} ({{ $plan->formattedPrice() }})
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end space-x-3 mt-6">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Confirmar Alteração</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
