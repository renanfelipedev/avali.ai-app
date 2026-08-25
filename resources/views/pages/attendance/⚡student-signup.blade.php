<?php

use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use Livewire\Component;

new class extends Component {
    public AttendanceSession $session;
    public string $student_name = '';
    public bool $hasRegistered = false;
    public ?string $device_id = null;

    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $geolocation_denied = false;

    public function rendering(\Illuminate\View\View $view)
    {
        $view->extends('layouts.app')->section('main');
    }

    public function rules(): array
    {
        return [
            'student_name' => 'required|string|min:3|max:100',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'student_name' => 'Seu Nome Completo',
        ];
    }

    public function mount(string $uuid)
    {
        $this->session = AttendanceSession::where('uuid', $uuid)->firstOrFail();

        // Check if the student has already checked in via cookie
        if (request()->cookie('presence_' . $this->session->uuid)) {
            $this->hasRegistered = true;
        }

        // Get or generate a persistent device_id cookie
        $this->device_id = request()->cookie('device_id');
        if (!$this->device_id) {
            $this->device_id = (string) \Illuminate\Support\Str::uuid();
            cookie()->queue('device_id', $this->device_id, 60 * 24 * 365); // 1 year
        }
    }

    public function register()
    {
        $this->session->refresh();

        $this->validate();

        // Double check session is active
        if (!$this->session->is_active) {
            $this->addError('student_name', 'Esta chamada já foi encerrada pelo professor.');
            return;
        }

        // Check if device_id has already registered in this session
        if ($this->device_id) {
            $deviceExists = AttendanceRecord::where('attendance_session_id', $this->session->id)->where('device_id', $this->device_id)->exists();

            if ($deviceExists) {
                $this->addError('student_name', 'Este dispositivo já registrou uma presença nesta chamada.');
                return;
            }
        }

        // Check for duplicate name in this session to prevent spam
        $exists = AttendanceRecord::where('attendance_session_id', $this->session->id)
            ->where('student_name', trim($this->student_name))
            ->exists();

        if ($exists) {
            $this->addError('student_name', 'Este nome já foi registrado nesta chamada.');
            return;
        }

        $distance = null;
        $isValidLocation = null;

        if ($this->session->require_geolocation) {
            if ($this->latitude && $this->longitude && $this->session->latitude && $this->session->longitude) {
                $distance = $this->calculateDistance($this->session->latitude, $this->session->longitude, $this->latitude, $this->longitude);
                $isValidLocation = $distance <= ($this->session->radius_meters ?? 100);
            } else {
                $isValidLocation = false;
            }
        }

        // Record presence
        AttendanceRecord::create([
            'attendance_session_id' => $this->session->id,
            'student_name' => trim($this->student_name),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_id' => $this->device_id,
            'distance_meters' => $distance,
            'is_valid_location' => $isValidLocation,
        ]);

        $this->hasRegistered = true;

        // Set a cookie for 24 hours to remember check-in
        cookie()->queue('presence_' . $this->session->uuid, '1', 60 * 24);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
};
?>
<div x-data="{
    init() {
        let localDeviceId = localStorage.getItem('device_id');
        if (localDeviceId) {
            $wire.set('device_id', localDeviceId);
        } else {
            localStorage.setItem('device_id', '{{ $device_id }}');
        }
    }
}"
    class="min-h-screen bg-zinc-50 dark:bg-zinc-950 flex flex-col justify-center items-center p-4">
    <div
        class="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
        <!-- HEADER -->
        <div class="text-center space-y-2">
            <div
                class="inline-flex p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
            </div>
            <h1 class="text-2xl font-black text-zinc-900 dark:text-white tracking-tight">Chamada Online</h1>
            <h2 class="text-sm font-semibold text-zinc-500 uppercase tracking-wider">Turma: {{ $session->class_name }}
            </h2>
        </div>

        <flux:separator />

        <!-- CORE FLOWS -->
        @if ($hasRegistered)
            <!-- SUCCESS STATE -->
            <div class="text-center space-y-4 py-4 animate-fade-in">
                <div
                    class="inline-flex p-4 rounded-full bg-green-100 dark:bg-green-950/40 text-green-600 dark:text-green-400">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Presença Confirmada!</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Obrigado! Sua presença foi registrada com
                        sucesso.</p>
                </div>
            </div>
        @elseif (!$session->is_active)
            <!-- CLOSED SESSION STATE -->
            <div class="text-center space-y-4 py-4 animate-fade-in">
                <div class="inline-flex p-4 rounded-full bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Chamada Encerrada</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Esta chamada já foi fechada pelo professor e não
                        aceita mais registros.</p>
                </div>
            </div>
        @else
            <!-- SIGNUP STATE -->
            <form x-data="{
                loadingLocation: false,
                submitForm() {
                    if (!{{ $session->require_geolocation ? 'true' : 'false' }}) {
                        this.$wire.register();
                        return;
                    }
            
                    this.loadingLocation = true;
            
                    if (!navigator.geolocation) {
                        this.$wire.set('geolocation_denied', true);
                        this.$wire.register();
                        return;
                    }
            
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.$wire.set('latitude', position.coords.latitude);
                            this.$wire.set('longitude', position.coords.longitude);
                            this.$wire.register();
                        },
                        (error) => {
                            this.$wire.set('geolocation_denied', true);
                            this.$wire.register();
                        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                }
            }" @submit.prevent="submitForm" class="space-y-4">
                @if ($session->require_geolocation)
                    <div
                        class="p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-800 dark:text-indigo-300 text-xs rounded-xl border border-indigo-100 dark:border-indigo-800/30">
                        <strong>Aviso de Privacidade:</strong> O professor exigiu validação presencial (Geofencing). Ao
                        continuar, a sua distância até o professor será calculada. <strong>Sua localização exata NÃO
                            será guardada pelo sistema.</strong>
                    </div>
                @endif

                <flux:input wire:model="student_name" name="student_name" autocomplete="name" label="Nome Completo"
                    placeholder="Digite seu nome completo" icon="user" autofocus required />

                <flux:button type="submit" variant="primary" class="w-full">
                    <span x-show="!loadingLocation">Confirmar Presença</span>
                    <span x-show="loadingLocation">Validando localização...</span>
                </flux:button>
            </form>
        @endif

        <div class="text-center text-[10px] text-zinc-400 dark:text-zinc-600 font-mono">
            ID: {{ substr($session->uuid, 0, 8) }}
        </div>
    </div>
</div>
