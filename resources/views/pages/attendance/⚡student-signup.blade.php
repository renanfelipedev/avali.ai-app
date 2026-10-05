<?php

use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Services\StudentNameService;
use Livewire\Component;

new class extends Component {
    public AttendanceSession $session;
    public string $student_name = '';
    public string $pin_input = '';
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
        $rules = [
            'student_name' => 'required|string|min:3|max:100',
        ];

        if ($this->session->require_pin) {
            $rules['pin_input'] = 'required|string|size:4';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        return [
            'student_name' => 'Seu Nome Completo',
            'pin_input' => 'Código PIN',
        ];
    }

    public function mount(string $uuid)
    {
        $this->session = AttendanceSession::with(['classroom.students', 'records'])->where('uuid', $uuid)->firstOrFail();
        $this->session->closeIfExpired();

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
        $this->session->closeIfExpired();

        $this->validate();

        // Double check session is active
        if (!$this->session->is_active) {
            $this->addError('student_name', 'Esta chamada já foi encerrada.');
            return;
        }

        // Validate PIN code if required
        if ($this->session->require_pin) {
            if (trim($this->pin_input) !== (string) $this->session->pin_code) {
                $this->addError('pin_input', 'Código PIN incorreto. Verifique os 4 dígitos na tela do professor.');
                return;
            }
        }

        // Check if device_id has already registered in this session
        if ($this->device_id) {
            $deviceExists = AttendanceRecord::where('attendance_session_id', $this->session->id)->where('device_id', $this->device_id)->exists();

            if ($deviceExists) {
                $this->addError('student_name', 'Este dispositivo já registrou uma presença nesta chamada.');
                return;
            }
        }

        $nameService = app(StudentNameService::class);

        // Check if only enrolled students are allowed
        if ($this->session->only_enrolled && $this->session->classroom_id && $this->session->classroom) {
            $enrolledStudents = $this->session->classroom->students;
            $normalizedInput = $nameService->normalize($this->student_name);

            $matchedEnrolled = $enrolledStudents->first(function ($student) use ($nameService, $normalizedInput) {
                $norm = $nameService->normalize($student->name);
                return $norm === $normalizedInput || str_contains($norm, $normalizedInput) || str_contains($normalizedInput, $norm);
            });

            if (!$matchedEnrolled) {
                $this->addError('student_name', 'Apenas alunos matriculados nesta turma podem registrar presença. Se você é aluno desta turma, fale com o professor.');
                return;
            }
        }

        $finalStudentName = $nameService->resolveStudentName($this->student_name, $this->session);

        // Check for duplicate name in this session to prevent spam (accent-insensitive)
        $normalizedFinal = $nameService->normalize($finalStudentName);
        $exists = $this->session->records()->get()->contains(function ($record) use ($nameService, $normalizedFinal) {
            return $nameService->normalize($record->student_name) === $normalizedFinal;
        });

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
            'student_name' => $finalStudentName,
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
    <flux:card class="w-full max-w-md p-6 sm:p-8 shadow-xl space-y-6">
        <!-- HEADER -->
        <div class="text-center space-y-2">
            <div
                class="inline-flex p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                <flux:icon.clipboard-document-check class="w-8 h-8" />
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-center">Chamada Online</flux:heading>
            <flux:subheading class="text-xs font-semibold uppercase tracking-wider text-center">Turma: {{ $session->class_name }}</flux:subheading>

            @if ($session->is_active && $session->expires_at)
                <div class="flex justify-center mt-2">
                    <flux:badge color="amber" icon="clock" size="sm">
                        Disponível até {{ $session->expires_at->setTimezone('America/Bahia')->format('H:i') }} (encerra {{ $session->expires_at->diffForHumans() }})
                    </flux:badge>
                </div>
            @endif
        </div>

        <flux:separator />

        <!-- CORE FLOWS -->
        @if ($hasRegistered)
            <!-- SUCCESS STATE -->
            <div class="text-center space-y-4 py-4 animate-fade-in">
                <div
                    class="inline-flex p-4 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon.check-circle class="w-12 h-12" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="lg">Presença Confirmada!</flux:heading>
                    <flux:subheading>Obrigado! Sua presença foi registrada com sucesso.</flux:subheading>
                </div>
            </div>
        @elseif (!$session->is_active)
            <!-- CLOSED SESSION STATE -->
            <div class="text-center space-y-4 py-4 animate-fade-in">
                <div class="inline-flex p-4 rounded-full bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400">
                    <flux:icon.exclamation-triangle class="w-12 h-12" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="lg">Chamada Encerrada</flux:heading>
                    <flux:subheading>Esta chamada foi finalizada e não aceita mais registros.</flux:subheading>
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
                    <flux:callout color="indigo" icon="map-pin" class="text-xs">
                        <flux:callout.heading class="font-bold">Validação por Localização (GPS)</flux:callout.heading>
                        <flux:callout.text>O professor exigiu presença física num raio de {{ $session->radius_meters ?? 100 }}m da sala.</flux:callout.text>
                    </flux:callout>
                @endif

                @if ($session->only_enrolled)
                    <flux:callout color="purple" icon="shield-check" class="text-xs">
                        <flux:callout.heading class="font-bold">Apenas Matriculados</flux:callout.heading>
                        <flux:callout.text>Apenas alunos cadastrados na lista oficial da turma podem confirmar presença.</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input wire:model="student_name" name="student_name" autocomplete="name" label="Nome Completo"
                    placeholder="Digite seu nome completo" icon="user" list="classroom-students" autofocus required />

                @if ($session->require_pin)
                    <flux:input wire:model="pin_input" name="pin_input" label="Código PIN da Sala (4 dígitos)"
                        placeholder="Ex: 1234" maxlength="4" icon="key"
                        description="Digite o código de 4 dígitos exibido no projetor do professor."
                        class="tracking-widest font-mono text-center font-bold text-lg" required />
                @endif

                @if ($session->classroom_id && $session->classroom && $session->classroom->students->isNotEmpty())
                    <datalist id="classroom-students">
                        @foreach ($session->classroom->students->sortBy(fn($s) => \Illuminate\Support\Str::slug($s->name)) as $enrolledStudent)
                            <option value="{{ $enrolledStudent->name }}"></option>
                        @endforeach
                    </datalist>
                @endif

                <flux:button type="submit" variant="primary" class="w-full" icon="check">
                    <span x-show="!loadingLocation">Confirmar Presença</span>
                    <span x-show="loadingLocation">Validando localização...</span>
                </flux:button>
            </form>
        @endif

        <div class="text-center text-[10px] text-zinc-400 dark:text-zinc-600 font-mono">
            ID: {{ substr($session->uuid, 0, 8) }}
        </div>
    </flux:card>
</div>
