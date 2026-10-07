<?php

use App\Models\AttendanceSession;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.main')] class extends Component {
    use WithPagination;

    public bool $showCreateModal = false;

    public string $class_name = '';
    public ?int $classroom_id = null;
    public string $duration_type = '120';
    public ?int $custom_duration_minutes = null;
    public ?int $duration_hours = 2;
    public bool $require_geolocation = false;
    public int $radius_meters = 100;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $require_pin = false;
    public ?string $pin_code = null;
    public bool $only_enrolled = false;

    public string $searchClass = '';
    public string $searchStatus = 'all';

    public function mount(): void
    {
        if (request()->query('nova') === '1') {
            $this->showCreateModal = true;
        }

        if (request()->filled('classroom_id')) {
            $classroomId = (int) request()->query('classroom_id');
            if (auth()->user()->classrooms()->where('id', $classroomId)->exists()) {
                $this->classroom_id = $classroomId;
                $classroom = auth()->user()->classrooms()->find($classroomId);
                if ($classroom && empty($this->class_name)) {
                    $this->class_name = $classroom->name;
                }
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function updatingSearchClass()
    {
        $this->resetPage();
    }

    public function updatingSearchStatus()
    {
        $this->resetPage();
    }

    public function rules(): array
    {
        return [
            'class_name' => 'required|string|min:3|max:100',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'duration_type' => 'required|string',
            'custom_duration_minutes' => 'nullable|required_if:duration_type,custom|integer|min:1|max:10080',
            'duration_hours' => 'nullable|integer|min:1|max:168',
            'require_geolocation' => 'boolean',
            'radius_meters' => 'nullable|integer|min:10|max:5000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'require_pin' => 'boolean',
            'pin_code' => 'nullable|string|digits:4',
            'only_enrolled' => 'boolean',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'class_name' => 'Nome da Turma',
            'classroom_id' => 'Turma Vinculada',
            'duration_type' => 'Duração da Chamada',
            'custom_duration_minutes' => 'Duração em minutos',
            'duration_hours' => 'Tempo de Disponibilidade (horas)',
            'radius_meters' => 'Raio de Distância Permitido',
            'pin_code' => 'Código PIN',
        ];
    }

    public function with(): array
    {
        AttendanceSession::closeAllExpired();

        $query = auth()->user()->attendanceSessions()->withCount('records')->latest();

        if (!empty($this->searchClass)) {
            $query->where(function ($q) {
                $q->where('class_name', 'like', '%' . $this->searchClass . '%')->orWhereHas('classroom', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->searchClass . '%');
                });
            });
        }

        if ($this->searchStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($this->searchStatus === 'inactive') {
            $query->where('is_active', false);
        }

        return [
            'sessions' => $query->paginate(10),
            'classrooms' => auth()->user()->classrooms()->latest()->get(),
        ];
    }

    public function startSession()
    {
        $this->validate();

        $minutes = $this->duration_type === 'custom'
            ? (int) $this->custom_duration_minutes
            : (int) $this->duration_type;

        // If duration_hours was set directly (e.g. in tests)
        if ($this->duration_type === '120' && $this->duration_hours && $this->duration_hours !== 2) {
            $minutes = $this->duration_hours * 60;
        }

        $durationHours = max(1, (int) ceil($minutes / 60));
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes) : null;

        $finalPin = null;
        if ($this->require_pin) {
            $finalPin = $this->pin_code ?: str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        }

        $session = AttendanceSession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'classroom_id' => $this->classroom_id,
            'class_name' => $this->class_name,
            'duration_hours' => $durationHours,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt,
            'is_active' => true,
            'require_geolocation' => $this->require_geolocation,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->require_geolocation ? (int) $this->radius_meters : null,
            'require_pin' => $this->require_pin,
            'pin_code' => $finalPin,
            'only_enrolled' => $this->classroom_id ? $this->only_enrolled : false,
        ]);

        $this->class_name = '';
        $this->classroom_id = null;
        $this->duration_type = '120';
        $this->custom_duration_minutes = null;
        $this->duration_hours = 2;
        $this->require_geolocation = false;
        $this->radius_meters = 100;
        $this->require_pin = false;
        $this->pin_code = null;
        $this->only_enrolled = false;
        $this->showCreateModal = false;

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
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <flux:heading size="xl">Chamada Online (QR Code)</flux:heading>
            <flux:subheading>Gerencie as chamadas e gere QR Codes para registro rápido de presença dos alunos.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Iniciar Nova Chamada
        </flux:button>
    </div>

    <!-- LIST OF PAST SESSIONS -->
    <flux:card class="overflow-hidden">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <flux:heading size="lg">Chamadas Realizadas</flux:heading>
            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                Total de {{ $sessions->total() }} chamada(s) encontrada(s)
            </div>
        </div>

        <!-- Filtros -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="sm:col-span-2 flex gap-2">
                <flux:input wire:model.live.debounce.250ms="searchClass"
                    placeholder="Filtrar por turma ou título..." icon="magnifying-glass" class="flex-1" />
                <flux:button wire:click="$refresh" variant="primary">Filtrar</flux:button>
            </div>
            <div class="sm:col-span-1">
                <flux:select wire:model.live="searchStatus">
                    <flux:select.option value="all">Todos os Status</flux:select.option>
                    <flux:select.option value="active">Ativas (Abertas)</flux:select.option>
                    <flux:select.option value="inactive">Finalizadas</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="overflow-x-auto w-full scrollbar-none">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Turma / Aula</flux:table.column>
                    <flux:table.column>Data / Duração</flux:table.column>
                    <flux:table.column>Recursos</flux:table.column>
                    <flux:table.column>Presentes</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Ações</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($sessions as $session)
                        <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                            <flux:table.cell>
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $session->class_name }}</div>
                                @if($session->classroom)
                                    <div class="text-[11px] text-zinc-400 flex items-center gap-1">
                                        <flux:icon.academic-cap class="w-3 h-3 inline shrink-0" />
                                        {{ $session->classroom->name }}
                                    </div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-xs">{{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y H:i') }}</div>
                                <div class="text-[11px] text-zinc-400 font-medium">Duração: {{ $session->formatted_duration }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @if($session->require_pin)
                                        <flux:badge size="xs" color="amber" icon="key" tooltip="PIN: {{ $session->pin_code }}">PIN</flux:badge>
                                    @endif
                                    @if($session->require_geolocation)
                                        <flux:badge size="xs" color="indigo" icon="map-pin" tooltip="GPS: {{ $session->radius_meters }}m">GPS {{ $session->radius_meters }}m</flux:badge>
                                    @endif
                                    @if($session->only_enrolled)
                                        <flux:badge size="xs" color="purple" icon="shield-check" tooltip="Apenas matriculados">Estrito</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm" class="font-bold">
                                    {{ $session->records_count }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($session->is_active)
                                    <flux:badge color="indigo" class="animate-pulse">Ativa (Aberta)</flux:badge>
                                    @if ($session->expires_at)
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1">
                                            <flux:icon.clock class="w-3 h-3 text-indigo-500 inline shrink-0" />
                                            <span>Encerra {{ $session->expires_at->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                @else
                                    <flux:badge color="green">Finalizada</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1">
                                    <flux:button href="{{ route('attendance.show', $session->uuid) }}"
                                        size="xs" variant="ghost" icon="eye"
                                        tooltip="Visualizar Chamada" />
                                    <flux:button wire:click="deleteSession({{ $session->id }})"
                                        wire:confirm="Tem certeza que deseja excluir esta chamada? Todos os registros de presença serão apagados."
                                        size="xs" variant="ghost" icon="trash" color="danger"
                                        tooltip="Excluir" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500 italic">
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

    <!-- MODAL DE CRIAÇÃO DE NOVA CHAMADA -->
    <flux:modal wire:model="showCreateModal" class="md:w-[580px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Iniciar Nova Chamada</flux:heading>
                <flux:subheading>Informe os dados da turma para gerar o QR Code de presença.</flux:subheading>
            </div>

            <form x-data="{
                loadingLocation: false,
                submitForm() {
                    if (!this.$wire.require_geolocation) {
                        this.$wire.startSession();
                        return;
                    }

                    this.loadingLocation = true;

                    if (!navigator.geolocation) {
                        alert('Geolocalização não é suportada pelo seu navegador.');
                        this.loadingLocation = false;
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.$wire.set('latitude', position.coords.latitude);
                            this.$wire.set('longitude', position.coords.longitude);
                            this.$wire.startSession();
                        },
                        (error) => {
                            alert('Para utilizar o Geofencing, você precisa PERMITIR que o navegador acesse sua localização (GPS).');
                            this.loadingLocation = false;
                        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                }
            }" @submit.prevent="submitForm" class="space-y-5">

                <div class="space-y-4">
                    <flux:input wire:model="class_name" label="Nome da Turma / Aula"
                        placeholder="Ex: Engenharia de Software 3º A" icon="academic-cap" required />

                    <flux:select wire:model.live="classroom_id" label="Vincular a uma Turma (Opcional)">
                        <flux:select.option value="">Sem vínculo (Chamada Avulsa)</flux:select.option>
                        @foreach ($classrooms as $classroom)
                            <flux:select.option value="{{ $classroom->id }}">{{ $classroom->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($classroom_id)
                        <flux:switch wire:model="only_enrolled" label="Apenas Alunos Matriculados"
                            description="Bloqueia check-in de qualquer pessoa que não conste na lista oficial desta turma." />
                    @endif

                    <flux:select wire:model.live="duration_type" label="Duração da Chamada" icon="clock"
                        description="A chamada encerra automaticamente após esse período.">
                        <flux:select.option value="5">5 minutos (Chamada Relâmpago)</flux:select.option>
                        <flux:select.option value="10">10 minutos</flux:select.option>
                        <flux:select.option value="15">15 minutos (Início de Aula)</flux:select.option>
                        <flux:select.option value="30">30 minutos</flux:select.option>
                        <flux:select.option value="45">45 minutos</flux:select.option>
                        <flux:select.option value="60">1 hora</flux:select.option>
                        <flux:select.option value="120">2 horas (Padrão)</flux:select.option>
                        <flux:select.option value="240">4 horas (Turno Completo)</flux:select.option>
                        <flux:select.option value="custom">Personalizado (em minutos)</flux:select.option>
                    </flux:select>

                    @if ($duration_type === 'custom')
                        <flux:input wire:model="custom_duration_minutes" type="number" min="1" max="10080"
                            label="Tempo em Minutos" placeholder="Ex: 25" icon="clock" />
                    @endif
                </div>

                <flux:separator />

                <div class="space-y-4">
                    <flux:subheading class="text-xs font-semibold uppercase tracking-wider">Segurança & Antifraude</flux:subheading>

                    <flux:switch wire:model.live="require_pin" label="Exigir Código PIN (4 dígitos)"
                        description="Exibe um PIN no projetor que os alunos precisam digitar (impede envio por WhatsApp)." />

                    @if ($require_pin)
                        <flux:input wire:model="pin_code" label="Código PIN Personalizado (Opcional)"
                            placeholder="Ex: 4892 (Vazio = gerar aleatório)" maxlength="4" icon="key"
                            description="Deixe em branco para gerar um código aleatório de 4 dígitos." />
                    @endif

                    <flux:switch wire:model.live="require_geolocation" label="Exigir Localização (Geofencing)"
                        description="Valida se os alunos estão presentes fisicamente perto de você." />

                    @if ($require_geolocation)
                        <flux:select wire:model="radius_meters" label="Raio de Distância Permitido" icon="map-pin"
                            description="Área máxima ao redor do professor permitida para assinar a presença.">
                            <flux:select.option value="30">30 metros (Sala pequena / Laboratório)</flux:select.option>
                            <flux:select.option value="50">50 metros (Sala de aula padrão)</flux:select.option>
                            <flux:select.option value="100">100 metros (Auditório / Bloco) - Recomendado</flux:select.option>
                            <flux:select.option value="250">250 metros (Prédio / Centro)</flux:select.option>
                            <flux:select.option value="500">500 metros (Campus Universitário)</flux:select.option>
                        </flux:select>
                    @endif
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="qr-code">
                        <span x-show="!loadingLocation">Gerar QR Code e Iniciar</span>
                        <span x-show="loadingLocation">Obtendo GPS...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
