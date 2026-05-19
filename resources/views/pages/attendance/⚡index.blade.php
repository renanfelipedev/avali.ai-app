<?php

use App\Models\AttendanceSession;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component
{
    use WithPagination;

    public string $class_name = '';

    public function rules(): array
    {
        return [
            'class_name' => 'required|string|min:3|max:100',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'class_name' => 'Nome da Turma',
        ];
    }

    public function with(): array
    {
        return [
            'sessions' => auth()->user()
                ->attendanceSessions()
                ->withCount('records')
                ->latest()
                ->paginate(10),
        ];
    }

    public function startSession()
    {
        $this->validate();

        $session = AttendanceSession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'class_name' => $this->class_name,
            'is_active' => true,
        ]);

        $this->class_name = '';
        session()->flash('status', 'Chamada online iniciada com sucesso!');

        return $this->redirect(route('attendance.show', $session->uuid), navigate: true);
    }

    public function deleteSession(AttendanceSession $session)
    {
        if ($session->user_id === auth()->id()) {
            $session->delete();
            session()->flash('status', 'Chamada excluída com sucesso.');
        }
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Chamada Online (QR Code)</flux:heading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- FORM CARD TO START NEW SESSION -->
        <div class="lg:col-span-1">
            <flux:card class="space-y-4">
                <flux:heading size="lg">Iniciar Nova Chamada</flux:heading>
                <flux:subheading>Informe o nome da turma para criar o QR Code de presença.</flux:subheading>

                <form wire:submit="startSession" class="space-y-4">
                    <flux:input 
                        wire:model="class_name" 
                        label="Nome da Turma / Aula" 
                        placeholder="Ex: Engenharia de Software 3º A" 
                        icon="academic-cap" 
                    />

                    <flux:button type="submit" variant="primary" class="w-full" icon="qr-code">
                        Gerar QR Code e Iniciar
                    </flux:button>
                </form>
            </flux:card>
        </div>

        <!-- LIST OF PAST SESSIONS -->
        <div class="lg:col-span-2">
            <flux:card class="overflow-hidden">
                <flux:heading size="lg" class="mb-4">Chamadas Anteriores</flux:heading>

                <div class="overflow-x-auto w-full scrollbar-none">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Turma</flux:table.column>
                            <flux:table.column>Data / Hora</flux:table.column>
                            <flux:table.column>Presentes</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Ações</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($sessions as $session)
                                <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                                    <flux:table.cell>
                                        <span class="font-bold text-zinc-900 dark:text-white">{{ $session->class_name }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y H:i') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge color="zinc" size="sm" class="font-bold">{{ $session->records_count }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($session->is_active)
                                            <flux:badge color="indigo" class="animate-pulse">Ativa (Aberta)</flux:badge>
                                        @else
                                            <flux:badge color="green">Finalizada</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1">
                                            <flux:button href="{{ route('attendance.show', $session->uuid) }}" size="xs" variant="ghost" icon="eye" tooltip="Visualizar Chamada" />
                                            <flux:button wire:click="deleteSession({{ $session->id }})" wire:confirm="Tem certeza que deseja excluir esta chamada? Todos os registros de presença serão apagados." size="xs" variant="ghost" icon="trash" color="danger" tooltip="Excluir" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500 italic">
                                        Nenhuma chamada online realizada ainda.
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="mt-4">
                    {{ $sessions->links() }}
                </div>
            </flux:card>
        </div>
    </div>
</div>
