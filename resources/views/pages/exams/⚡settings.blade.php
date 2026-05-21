<?php

use App\Models\PrintProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component
{
    use WithFileUploads;

    // Campos do Modal de Criação/Edição
    #[Validate('required|string|max:255')]
    public string $institutionName = '';

    #[Validate('nullable|image|max:2048')]
    public $logoFile;

    public ?PrintProfile $editingProfile = null;

    public function with(): array
    {
        return [
            'profiles' => Auth::user()->printProfiles()->latest()->get(),
        ];
    }

    public function openCreateModal()
    {
        $this->reset(['institutionName', 'logoFile', 'editingProfile']);
        $this->modal('profile-modal')->show();
    }

    public function editProfile(PrintProfile $profile)
    {
        if ($profile->user_id !== Auth::id()) abort(403);
        
        $this->resetValidation();
        $this->editingProfile = $profile;
        $this->institutionName = $profile->institution_name;
        $this->logoFile = null;
        
        $this->modal('profile-modal')->show();
    }

    public function saveProfile()
    {
        $this->validate();

        $path = $this->editingProfile ? $this->editingProfile->logo_path : null;

        if ($this->logoFile) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $path = $this->logoFile->store('logos', 'public');
        }

        if ($this->editingProfile) {
            $this->editingProfile->update([
                'institution_name' => $this->institutionName,
                'logo_path' => $path,
            ]);
            session()->flash('status', 'Perfil atualizado com sucesso!');
        } else {
            Auth::user()->printProfiles()->create([
                'institution_name' => $this->institutionName,
                'logo_path' => $path,
            ]);
            session()->flash('status', 'Perfil criado com sucesso!');
        }

        $this->modal('profile-modal')->close();
    }

    public function deleteProfile($id)
    {
        $profile = PrintProfile::findOrFail($id);
        if ($profile->user_id !== Auth::id()) abort(403);

        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }
        
        $profile->delete();
        session()->flash('status', 'Perfil de impressão excluído.');
    }
};
?>

<div>
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('exams.index') }}">Provas</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Configurações de Impressão</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <flux:heading size="xl">Perfis de Impressão</flux:heading>
            <flux:subheading>Gerencie os cabeçalhos personalizados para suas provas em PDF.</flux:subheading>
        </div>
        
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">Novo Perfil</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Logo</flux:table.column>
                <flux:table.column>Nome da Instituição</flux:table.column>
                <flux:table.column align="right">Ações</flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse($profiles as $profile)
                    <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                        <flux:table.cell>
                            @if($profile->logo_path)
                                <img src="{{ Storage::url($profile->logo_path) }}" class="h-10 w-auto rounded object-contain border border-zinc-200 dark:border-zinc-800 bg-white" alt="Logo">
                            @else
                                <div class="h-10 w-10 flex items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-400">
                                    <flux:icon.photo class="size-5" />
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">
                            {{ $profile->institution_name }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button wire:click="editProfile({{ $profile->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                            <flux:button wire:click="deleteProfile({{ $profile->id }})" wire:confirm="Tem certeza que deseja excluir este perfil?" variant="danger" size="sm" icon="trash">Excluir</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center py-12 text-zinc-500">
                            <flux:icon.printer class="size-12 mx-auto mb-4 text-zinc-400" />
                            <p>Você ainda não configurou nenhum perfil de impressão.</p>
                            <p class="text-xs mt-1">O cabeçalho padrão "Avali.AI" será utilizado nas suas provas.</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="profile-modal" class="max-w-md">
        <form wire:submit="saveProfile">
            <flux:heading size="lg" class="mb-6">{{ $editingProfile ? 'Editar Perfil' : 'Novo Perfil de Impressão' }}</flux:heading>
            
            <div class="space-y-4">
                <flux:input wire:model="institutionName" label="Nome da Instituição" placeholder="Ex: Colégio Estadual..." required />
                
                <div>
                    <flux:input type="file" wire:model="logoFile" label="Logo da Instituição (Opcional)" accept="image/*" />
                    <div wire:loading wire:target="logoFile" class="text-xs text-indigo-600 mt-1">Enviando imagem...</div>
                    @error('logoFile') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                    
                    @if ($logoFile)
                        <div class="mt-2 text-xs text-zinc-500">Pré-visualização da nova logo:</div>
                        <img src="{{ $logoFile->temporaryUrl() }}" class="mt-1 h-12 w-auto object-contain border border-zinc-200 dark:border-zinc-800 rounded bg-white">
                    @elseif ($editingProfile && $editingProfile->logo_path)
                        <div class="mt-2 text-xs text-zinc-500">Logo atual:</div>
                        <img src="{{ Storage::url($editingProfile->logo_path) }}" class="mt-1 h-12 w-auto object-contain border border-zinc-200 dark:border-zinc-800 rounded bg-white">
                    @endif
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
