<?php

use App\Models\AttendanceSession;
use App\Traits\HasOwnership;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component {
    use HasOwnership;

    public AttendanceSession $session;

    public function mount(AttendanceSession $session)
    {
        $this->authorizeOwnership($session);
        $session->refresh();
        $session->closeIfExpired();
        $session->loadMissing(['classroom.students', 'records']);
        $this->session = $session;
    }

    public $new_classroom_id = null;
    public $new_class_name = '';
    public ?int $new_duration_minutes = null;
    public ?int $new_duration_hours = null;
    public bool $new_require_pin = false;
    public ?string $new_pin_code = null;
    public bool $new_only_enrolled = false;

    public function getRecordsProperty()
    {
        return $this->session->records()->latest()->get();
    }

    public function editClassroom()
    {
        $this->authorizeOwnership($this->session);
        $this->new_classroom_id = $this->session->classroom_id;
        $this->new_class_name = $this->session->class_name;
        $this->new_duration_minutes = $this->session->duration_minutes;
        $this->new_duration_hours = $this->session->duration_hours;
        $this->new_require_pin = $this->session->require_pin;
        $this->new_pin_code = $this->session->pin_code;
        $this->new_only_enrolled = $this->session->only_enrolled;
        $this->modal('edit-classroom-modal')->show();
    }

    public function updateClassroom()
    {
        $this->authorizeOwnership($this->session);

        $this->validate([
            'new_class_name' => 'required|string|max:255',
            'new_duration_minutes' => 'nullable|integer|min:1|max:10080',
            'new_duration_hours' => 'nullable|integer|min:1|max:168',
            'new_require_pin' => 'boolean',
            'new_pin_code' => 'nullable|string|digits:4',
            'new_only_enrolled' => 'boolean',
        ]);

        $updateData = [
            'class_name' => $this->new_class_name,
            'require_pin' => $this->new_require_pin,
            'only_enrolled' => $this->new_classroom_id ? $this->new_only_enrolled : false,
        ];

        if ($this->new_require_pin) {
            $updateData['pin_code'] = $this->new_pin_code ?: ($this->session->pin_code ?: str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT));
        } else {
            $updateData['pin_code'] = null;
        }

        if ($this->session->is_active) {
            if ($this->new_duration_minutes) {
                $updateData['duration_minutes'] = (int) $this->new_duration_minutes;
                $updateData['duration_hours'] = max(1, (int) ceil($this->new_duration_minutes / 60));
                $updateData['expires_at'] = $this->session->created_at->copy()->addMinutes((int) $this->new_duration_minutes);
            } elseif ($this->new_duration_hours) {
                $updateData['duration_hours'] = (int) $this->new_duration_hours;
                $updateData['duration_minutes'] = (int) $this->new_duration_hours * 60;
                $updateData['expires_at'] = $this->session->created_at->copy()->addHours((int) $this->new_duration_hours);
            }
        }

        if ($this->new_classroom_id) {
            $classroom = \App\Models\Classroom::find($this->new_classroom_id);
            if ($classroom && $classroom->user_id === auth()->id()) {
                $updateData['classroom_id'] = $classroom->id;
            }
        } else {
            $updateData['classroom_id'] = null;
        }

        $this->session->update($updateData);

        $this->modal('edit-classroom-modal')->close();
        session()->flash('status', 'Chamada atualizada com sucesso.');
    }

    public function getAbsenteesProperty()
    {
        if (!$this->session->classroom_id || !$this->session->classroom) {
            return collect();
        }

        $nameService = app(\App\Services\StudentNameService::class);
        $presentNames = $this->session
            ->records()
            ->pluck('student_name')
            ->map(function ($name) use ($nameService) {
                return $nameService->normalize($name);
            });

        return $this->session->classroom->students
            ->filter(function ($student) use ($nameService, $presentNames) {
                $studentNameNormalized = $nameService->normalize($student->name);

                // Return true if student is absent (name not in present names)
                return !$presentNames->contains(function ($presentName) use ($studentNameNormalized) {
                    return $presentName === $studentNameNormalized || str_contains($studentNameNormalized, $presentName) || str_contains($presentName, $studentNameNormalized);
                });
            })
            ->sortBy(fn($student) => \Illuminate\Support\Str::slug($student->name));
    }

    public function refreshRecords()
    {
        // Polling will call this to refresh data and check expiration
        $this->session->refresh();
        if ($this->session->closeIfExpired()) {
            session()->flash('status', 'O tempo limite da chamada expirou e ela foi encerrada automaticamente.');
        }
        $this->session->load('records');
    }

    public function deleteRecord($recordId)
    {
        $this->authorizeOwnership($this->session);

        $record = $this->session->records()->find($recordId);
        if ($record) {
            $record->delete();
            session()->flash('status', 'Registro de presença excluído com sucesso.');
        }
    }

    public function endSession()
    {
        $this->authorizeOwnership($this->session);

        if (!$this->session->is_active) {
            return;
        }

        // Close the session
        $this->session->update(['is_active' => false]);

        session()->flash('status', 'Chamada encerrada com sucesso!');
    }


    public function downloadPdf()
    {
        $this->authorizeOwnership($this->session);

        $html = view('pdf.attendance-sheet', ['session' => $this->session])->render();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $safeClassName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $this->session->class_name);

        return response()->streamDownload(fn() => print $pdfContent, "chamada_{$safeClassName}_" . $this->session->created_at->format('Y-m-d') . '.pdf');
    }

    public function exportCsv()
    {
        $this->authorizeOwnership($this->session);

        $records = $this->session->records()->latest()->get()->sortBy(fn($r) => \Illuminate\Support\Str::slug($r->formatted_student_name));

        $csvHeader = ['#', 'Nome do Estudante', 'Data e Hora', 'Status Localização', 'Distância (m)', 'Endereço IP', 'Dispositivo'];

        $handle = fopen('php://memory', 'r+');
        // Add UTF-8 BOM for Excel
        fputs($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $csvHeader, ';');

        foreach ($records as $index => $record) {
            $locationStatus = 'N/A';
            if ($this->session->require_geolocation) {
                if ($record->is_valid_location === true) {
                    $locationStatus = 'Válido (Próximo)';
                } elseif ($record->is_valid_location === false && $record->distance_meters !== null) {
                    $locationStatus = 'Fora da Área';
                } else {
                    $locationStatus = 'GPS Bloqueado';
                }
            }

            fputcsv($handle, [$index + 1, $record->formatted_student_name, $record->created_at->setTimezone('America/Bahia')->format('d/m/Y H:i:s'), $locationStatus, $record->distance_meters ?? '', $record->ip_address ?? '', $record->user_agent ?? ''], ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $safeClassName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $this->session->class_name);

        return response()->streamDownload(fn() => print $content, "chamada_{$safeClassName}_" . $this->session->created_at->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
};
?>

<div @if ($session->is_active) wire:poll.3s="refreshRecords" @endif>
    <!-- TOP HEADER -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl" class="font-extrabold tracking-tight flex items-center gap-2">
                    Chamada: {{ $session->class_name }}
                    <flux:button wire:click="editClassroom" size="xs" variant="subtle" icon="pencil"
                        tooltip="Editar Chamada" />
                </flux:heading>
                @if ($session->is_active)
                    <flux:badge color="indigo" size="sm" class="animate-pulse">Ativa (Aberta)</flux:badge>
                @else
                    <flux:badge color="green" size="sm">Finalizada</flux:badge>
                @endif
                @if ($session->require_pin)
                    <flux:badge color="amber" size="sm" icon="key">PIN: {{ $session->pin_code }}</flux:badge>
                @endif
                @if ($session->only_enrolled)
                    <flux:badge color="purple" size="sm" icon="shield-check">Apenas Matriculados</flux:badge>
                @endif
                @if ($session->require_geolocation)
                    <flux:badge color="indigo" size="sm" icon="map-pin">GPS ({{ $session->radius_meters }}m)
                    </flux:badge>
                @endif
            </div>
            <flux:subheading>
                Iniciada em {{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y \à\s H:i') }}
                @if ($session->is_active && $session->expires_at)
                    • <span class="text-indigo-600 dark:text-indigo-400 font-semibold">Encerra automaticamente
                        {{ $session->expires_at->diffForHumans() }} (às
                        {{ $session->expires_at->setTimezone('America/Bahia')->format('H:i') }})</span>
                @else
                    • Duração: {{ $session->formatted_duration }}
                @endif
            </flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="exportCsv" variant="filled" color="zinc" icon="table-cells">
                Exportar CSV
            </flux:button>

            @if ($session->is_active)
                <flux:button wire:click="endSession" variant="primary" color="red" icon="check-circle"
                    wire:confirm="Tem certeza que deseja encerrar a chamada agora? A lista de presença será fechada para novos registros.">
                    Encerrar Chamada
                </flux:button>
            @else
                <flux:button wire:click="downloadPdf" variant="primary" icon="arrow-down-tray">
                    Baixar PDF
                </flux:button>
            @endif
            <flux:button href="{{ route('attendance.index') }}" variant="ghost" icon="arrow-left">Voltar</flux:button>
        </div>
    </div>

    <!-- QR CODE & INSTRUCTIONS (IF ACTIVE) -->
    <div x-data="{ fullscreen: false }" @keydown.escape.window="fullscreen = false"
        class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        @if ($session->is_active)
            <!-- QR CODE CARD -->
            <flux:card class="lg:col-span-1 flex flex-col items-center justify-center p-6 space-y-4">
                <flux:heading size="lg">QR Code para Celular</flux:heading>
                <flux:subheading class="text-center">Peça para os alunos escanearem a imagem abaixo para registrar o
                    nome.</flux:subheading>

                <div
                    class="p-4 bg-white rounded-2xl flex items-center justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode(route('attendance.student-signup', $session->uuid)) }}"
                        alt="QR Code" class="w-48 h-48 sm:w-56 sm:h-56">
                </div>

                @if ($session->require_pin)
                    <div class="w-full text-center py-3 px-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 flex flex-col items-center justify-center">
                        <div class="uppercase tracking-wider text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center justify-center gap-1.5 text-center w-full">
                            <flux:icon.key class="size-3.5 inline-block shrink-0" />
                            <span>Código PIN da Sala</span>
                        </div>
                        <div class="text-3xl sm:text-4xl font-black font-mono tracking-widest text-amber-600 dark:text-amber-400 mt-1 text-center w-full select-all">
                            {{ $session->pin_code }}
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 w-full">
                    <flux:button @click="fullscreen = true" icon="arrows-pointing-out" size="sm" class="flex-1">
                        Tela Cheia</flux:button>
                    <flux:button x-data="{
                        copied: false,
                        copy() {
                            let text = '{{ route('attendance.student-signup', $session->uuid) }}';
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text);
                            } else {
                                let textArea = document.createElement('textarea');
                                textArea.value = text;
                                textArea.style.position = 'fixed';
                                textArea.style.left = '-999999px';
                                textArea.style.top = '-999999px';
                                document.body.appendChild(textArea);
                                textArea.focus();
                                textArea.select();
                                try {
                                    document.execCommand('copy');
                                } catch (err) {
                                    console.error('Falha ao copiar link', err);
                                }
                                textArea.remove();
                            }
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        }
                    }" @click="copy()" variant="ghost" icon="document-duplicate"
                        size="sm" class="flex-1">
                        <span x-show="!copied">Copiar Link</span>
                        <span x-show="copied" class="text-green-600 dark:text-green-400">Copiado!</span>
                    </flux:button>
                </div>
            </flux:card>

            <!-- FULLSCREEN OVERLAY FOR PROJECTOR -->
            <div x-show="fullscreen" x-transition
                class="fixed inset-0 bg-white dark:bg-zinc-950 z-50 flex flex-col items-center justify-center p-8 space-y-6"
                style="display: none;">

                <flux:button @click="fullscreen = false" variant="ghost" icon="x-mark"
                    class="absolute top-6 right-6" />

                <flux:heading size="xl" class="text-3xl sm:text-5xl font-black text-center tracking-tight">
                    Chamada Online - Turma: {{ $session->class_name }}
                </flux:heading>
                <flux:subheading class="text-lg sm:text-xl text-center font-medium">
                    Escaneie o QR Code abaixo para registrar a sua presença!
                </flux:subheading>

                <!-- QR Code Wrapper -->
                <div
                    class="p-6 bg-white rounded-3xl shadow-2xl flex items-center justify-center animate-fade-in">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=450x450&data={{ urlencode(route('attendance.student-signup', $session->uuid)) }}"
                        alt="QR Code" class="w-72 h-72 sm:w-96 sm:h-96 md:w-[400px] md:h-[400px]">
                </div>

                @if ($session->require_pin)
                    <div class="max-w-xl w-full text-center py-6 px-8 rounded-3xl bg-amber-50 dark:bg-amber-950/30 shadow-lg flex flex-col items-center justify-center">
                        <div class="uppercase tracking-widest text-base sm:text-lg font-bold text-amber-700 dark:text-amber-400 flex items-center justify-center gap-2 text-center w-full">
                            <flux:icon.key class="size-6 inline-block shrink-0" />
                            <span>Código PIN Obrigatório</span>
                        </div>
                        <div class="font-black font-mono tracking-[0.25em] text-amber-600 dark:text-amber-400 text-center w-full select-all"
                             style="font-size: clamp(4.5rem, 12vw, 8rem); line-height: 1.1; margin-top: 0.5rem;">
                            {{ $session->pin_code }}
                        </div>
                    </div>
                @endif

                <!-- URL Fallback -->
                <div class="text-center max-w-xl break-all p-4 rounded-2xl bg-zinc-100 dark:bg-zinc-900">
                    <div class="text-xs uppercase tracking-wider font-bold text-zinc-500">Ou acesse pelo link:</div>
                    <div class="text-indigo-600 dark:text-indigo-400 font-mono font-bold text-base sm:text-lg mt-1">
                        {{ route('attendance.student-signup', $session->uuid) }}
                    </div>
                </div>
            </div>
        @else
            <!-- METRICS CARD WHEN COMPLETED -->
            <flux:card
                class="lg:col-span-1 flex flex-col items-center justify-center p-6 space-y-4 bg-emerald-50/20 dark:bg-emerald-950/10">
                <div
                    class="p-4 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon.check-circle class="w-12 h-12" />
                </div>
                <div class="text-center">
                    <flux:heading size="lg">Chamada Concluída</flux:heading>
                    <flux:subheading class="mt-1">A lista de presença foi gravada e finalizada de forma definitiva.</flux:subheading>
                </div>
            </flux:card>
        @endif

        <!-- DETAILS CARD -->
        <flux:card class="lg:col-span-2 space-y-4">
            <flux:heading size="lg">Resumo da Aula</flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div
                    class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 flex items-center gap-3">
                    <div
                        class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <flux:icon.users class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-black tracking-tight">{{ $session->records->count() }}</div>
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Presentes no Momento
                        </div>
                    </div>
                </div>

                <div
                    class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
                        <flux:icon.clock class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-lg font-bold tracking-tight">
                            {{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y H:i') }}</div>
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Início da Chamada
                        </div>
                    </div>
                </div>

                <div
                    class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                        <flux:icon.clock class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-lg font-bold tracking-tight">
                            {{ $session->formatted_duration }}
                            @if ($session->is_active && $session->expires_at)
                                <span
                                    class="text-xs font-normal text-zinc-500">({{ $session->expires_at->diffForHumans() }})</span>
                            @endif
                        </div>
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                            {{ $session->is_active ? 'Disponibilidade' : 'Duração' }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 text-xs text-zinc-500 space-y-2.5">
                <div class="flex justify-between">
                    <span class="font-bold">Professor:</span>
                    <span>{{ $session->user->name ?? auth()->user()->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">Recursos Ativos:</span>
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $session->require_pin ? "PIN ({$session->pin_code})" : 'Sem PIN' }} •
                        {{ $session->require_geolocation ? "GPS ({$session->radius_meters}m)" : 'Sem GPS' }} •
                        {{ $session->only_enrolled ? 'Apenas Matriculados' : 'Livre' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">ID Único da Chamada:</span>
                    <span class="font-mono text-[10px]">{{ $session->uuid }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- STUDENT LIST -->
    <flux:card class="overflow-hidden">
        <div class="mb-4">
            <flux:heading size="lg" class="font-extrabold tracking-tight">Estudantes Confirmados</flux:heading>
            <flux:subheading>Esta lista atualiza automaticamente a cada 3 segundos enquanto a chamada estiver ativa.
            </flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome do Estudante</flux:table.column>
                <flux:table.column>Horário de Check-in</flux:table.column>
                @if ($session->require_geolocation)
                    <flux:table.column>Localização</flux:table.column>
                @endif
                <flux:table.column>Endereço IP</flux:table.column>
                <flux:table.column>Navegador / Dispositivo</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($session->records->sortBy(fn($record) => \Illuminate\Support\Str::slug($record->formatted_student_name)) as $record)
                    <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                        <flux:table.cell>
                            <span
                                class="font-bold text-zinc-900 dark:text-zinc-50">{{ $record->formatted_student_name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $record->created_at->setTimezone('America/Bahia')->format('H:i:s') }}
                        </flux:table.cell>
                        @if ($session->require_geolocation)
                            <flux:table.cell>
                                @if ($record->is_valid_location === true)
                                    <flux:badge color="green" size="sm" icon="map-pin"
                                        tooltip="Distância aproximada calculada: {{ $record->distance_meters }} metros">
                                        Válido (Próximo)</flux:badge>
                                @elseif($record->is_valid_location === false && $record->distance_meters !== null)
                                    <flux:badge color="red" size="sm" icon="exclamation-triangle"
                                        tooltip="Distância aproximada: {{ $record->distance_meters > 1000 ? round($record->distance_meters / 1000, 1) . ' km' : $record->distance_meters . ' metros' }}">
                                        Fora da Área</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" icon="no-symbol"
                                        tooltip="O aluno recusou a permissão de GPS no celular.">GPS Bloqueado
                                    </flux:badge>
                                @endif
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <span class="font-mono text-xs text-zinc-500">{{ $record->ip_address ?? 'N/D' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate" title="{{ $record->user_agent }}">
                            <span
                                class="text-xs text-zinc-400 font-medium">{{ \Illuminate\Support\Str::limit($record->user_agent, 40) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="deleteRecord({{ $record->id }})"
                                wire:confirm="Tem certeza que deseja excluir o registro de presença de {{ $record->formatted_student_name }}?"
                                size="xs" variant="ghost" color="danger" icon="trash"
                                tooltip="Excluir Presença" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $session->require_geolocation ? '6' : '5' }}"
                            class="text-center text-zinc-500 py-12 italic">
                            Aguardando a confirmação de presença dos alunos...
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($session->classroom_id)
        <flux:card class="mt-6 overflow-hidden">
            <div class="mb-4">
                <flux:heading size="lg" class="font-extrabold tracking-tight text-red-600 dark:text-red-400">
                    Estudantes Ausentes ({{ $this->absentees->count() }})</flux:heading>
                <flux:subheading>Alunos matriculados na turma que ainda não registraram presença.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @forelse($this->absentees as $absentee)
                    <div
                        class="p-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl flex items-center gap-2">
                        <flux:icon.x-circle class="w-5 h-5 text-red-500 shrink-0" />
                        <span
                            class="font-medium text-sm text-zinc-700 dark:text-zinc-300">{{ $absentee->name }}</span>
                    </div>
                @empty
                    <div class="col-span-full p-4 text-center rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 flex items-center justify-center gap-2 font-bold text-sm">
                        <flux:icon.check-badge class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <span>Todos os alunos da turma estão presentes!</span>
                    </div>
                @endforelse
            </div>
        </flux:card>
    @endif

    <!-- Modal de Edição de Turma -->
    <flux:modal name="edit-classroom-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Chamada</flux:heading>
                <flux:subheading>Atualize as configurações e parâmetros da chamada.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="new_class_name" label="Nome/Título da Chamada" required
                    icon="academic-cap" />

                <flux:select wire:model.live="new_classroom_id" label="Vincular a uma Turma (Opcional)">
                    <flux:select.option value="">-- Nenhuma (Chamada Avulsa) --</flux:select.option>
                    @foreach (auth()->user()->classrooms as $classroom)
                        <flux:select.option value="{{ $classroom->id }}">{{ $classroom->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($new_classroom_id)
                    <flux:switch wire:model="new_only_enrolled" label="Apenas Alunos Matriculados"
                        description="Bloqueia check-in de quem não estiver na lista oficial desta turma." />
                @endif

                @if ($session->is_active)
                    <flux:input wire:model="new_duration_minutes" label="Tempo de Disponibilidade (em minutos)"
                        type="number" min="1" max="10080" placeholder="Ex: 30" icon="clock"
                        description="Tempo total a contar do início da chamada." />
                @endif

                <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider pt-1">Segurança & Antifraude</div>

                <div class="space-y-4">
                    <flux:switch wire:model.live="new_require_pin" label="Exigir Código PIN (4 dígitos)"
                        description="Exige que os alunos digitem o PIN para confirmar presença." />

                    @if ($new_require_pin)
                        <flux:input wire:model="new_pin_code" label="Código PIN" maxlength="4"
                            placeholder="Ex: 1234 (Vazio = automático)" icon="key"
                            description="Deixe vazio para manter ou gerar 4 dígitos automáticos." />
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="updateClassroom" variant="primary">Salvar Alterações</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
