<?php

use App\Models\AttendanceSession;
use App\Traits\HasOwnership;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    use HasOwnership;

    public AttendanceSession $session;

    public function mount(AttendanceSession $session)
    {
        $this->authorizeOwnership($session);
        $session->refresh();
        $this->session = $session;
    }

    public $new_classroom_id = null;
    public $new_class_name = '';

    public function getRecordsProperty()
    {
        return $this->session->records()->latest()->get();
    }

    public function editClassroom()
    {
        $this->authorizeOwnership($this->session);
        $this->new_classroom_id = $this->session->classroom_id;
        $this->new_class_name = $this->session->class_name;
        $this->modal('edit-classroom-modal')->show();
    }

    public function updateClassroom()
    {
        $this->authorizeOwnership($this->session);

        $this->validate([
            'new_class_name' => 'required|string|max:255',
        ]);

        $updateData = [
            'class_name' => $this->new_class_name,
        ];

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
        if (!$this->session->classroom_id) {
            return collect();
        }

        $presentNames = $this->session->records()->pluck('student_name')->map(fn($name) => strtolower(trim($name)));
        
        return $this->session->classroom->students->filter(function ($student) use ($presentNames) {
            $studentNameLower = strtolower(trim($student->name));
            // Return true if student is absent (name not in present names)
            // Also do a partial match to be generous
            return !$presentNames->contains(function ($presentName) use ($studentNameLower) {
                return str_contains($studentNameLower, $presentName) || str_contains($presentName, $studentNameLower);
            });
        })->sortBy(fn($student) => \Illuminate\Support\Str::slug($student->name));
    }

    public function refreshRecords()
    {
        // Polling will call this to refresh data
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

    public function endSessionAndSendMail()
    {
        $this->authorizeOwnership($this->session);

        if (!$this->session->is_active) {
            return;
        }

        // Close the session
        $this->session->update(['is_active' => false]);

        // Dispatch Email
        \Illuminate\Support\Facades\Mail::to(auth()->user()->email)
            ->send(new \App\Mail\AttendanceReportMail($this->session));

        session()->flash('status', 'Chamada encerrada com sucesso! O relatório de presença foi enviado para o seu e-mail.');
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
        
        return response()->streamDownload(
            fn () => print($pdfContent),
            "chamada_{$safeClassName}_" . $this->session->created_at->format('Y-m-d') . ".pdf"
        );
    }

    public function resendMail()
    {
        $this->authorizeOwnership($this->session);

        \Illuminate\Support\Facades\Mail::to(auth()->user()->email)
            ->send(new \App\Mail\AttendanceReportMail($this->session));

        session()->flash('status', 'E-mail com o relatório de presença reenviado com sucesso.');
    }
};
?>

<div @if ($session->is_active) wire:poll.3s="refreshRecords" @endif>
    <!-- TOP HEADER -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" class="font-extrabold tracking-tight flex items-center gap-2">
                    Chamada: {{ $session->class_name }}
                    <flux:button wire:click="editClassroom" size="xs" variant="subtle" icon="pencil" tooltip="Editar Chamada" />
                </flux:heading>
                @if($session->is_active)
                    <flux:badge color="indigo" size="sm" class="animate-pulse">Ativa (Aberta)</flux:badge>
                @else
                    <flux:badge color="green" size="sm">Finalizada</flux:badge>
                @endif
            </div>
            <flux:subheading>Iniciada em {{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y \à\s H:i:s') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @if ($session->is_active)
                <flux:button wire:click="endSessionAndSendMail" variant="primary" color="red" icon="check-circle" wire:confirm="Tem certeza que deseja encerrar a chamada agora? A lista de presença será fechada e enviada para o seu e-mail.">
                    Encerrar Chamada e Enviar E-mail
                </flux:button>
            @else
                <flux:button wire:click="downloadPdf" variant="primary" icon="arrow-down-tray">
                    Baixar PDF
                </flux:button>
                <flux:button wire:click="resendMail" variant="filled" color="zinc" icon="envelope">
                    Reenviar E-mail
                </flux:button>
            @endif
            <flux:button href="{{ route('attendance.index') }}" variant="ghost" icon="arrow-left">Voltar</flux:button>
        </div>
    </div>

    <!-- QR CODE & INSTRUCTIONS (IF ACTIVE) -->
    <div x-data="{ fullscreen: false }" @keydown.escape.window="fullscreen = false" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        @if ($session->is_active)
            <!-- QR CODE CARD -->
            <flux:card class="lg:col-span-1 flex flex-col items-center justify-center p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">QR Code para Celular</flux:heading>
                <flux:subheading class="text-center">Peça para os alunos escanearem a imagem abaixo para registrar o nome.</flux:subheading>
                
                <div class="p-4 bg-white rounded-2xl border border-zinc-200 shadow-inner flex items-center justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode(route('attendance.student-signup', $session->uuid)) }}" alt="QR Code" class="w-48 h-48 sm:w-56 sm:h-56">
                </div>
                
                <div class="flex gap-2 w-full">
                    <flux:button @click="fullscreen = true" icon="arrows-pointing-out" size="sm" class="flex-1">Tela Cheia</flux:button>
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
                    }" @click="copy()" variant="ghost" icon="document-duplicate" size="sm" class="flex-1">
                        <span x-show="!copied">Copiar Link</span>
                        <span x-show="copied" class="text-green-600 dark:text-green-400">Copiado!</span>
                    </flux:button>
                </div>
            </flux:card>

            <!-- FULLSCREEN OVERLAY FOR PROJECTOR -->
            <div x-show="fullscreen" 
                 x-transition
                 class="fixed inset-0 bg-white dark:bg-zinc-950 z-50 flex flex-col items-center justify-center p-8 space-y-6"
                 style="display: none;">
                 
                 <button @click="fullscreen = false" class="absolute top-6 right-6 p-2 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 hover:text-zinc-800 dark:hover:text-white transition-colors">
                     <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                     </svg>
                 </button>

                 <h1 class="text-3xl sm:text-5xl font-black text-indigo-600 dark:text-indigo-400 text-center tracking-tight">
                     Chamada Online - Turma: {{ $session->class_name }}
                 </h1>
                 <p class="text-lg sm:text-xl text-zinc-500 dark:text-zinc-400 text-center font-medium">
                     Escaneie o QR Code abaixo para registrar a sua presença!
                 </p>

                 <!-- QR Code Wrapper -->
                 <div class="p-6 bg-white rounded-3xl shadow-2xl border border-zinc-200 flex items-center justify-center animate-fade-in">
                     <img src="https://api.qrserver.com/v1/create-qr-code/?size=450x450&data={{ urlencode(route('attendance.student-signup', $session->uuid)) }}" alt="QR Code" class="w-72 h-72 sm:w-96 sm:h-96 md:w-[420px] md:h-[420px]">
                 </div>

                 <!-- URL Fallback -->
                 <div class="text-center bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 px-6 py-3 rounded-2xl max-w-xl break-all shadow-inner">
                     <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold block mb-1">Ou acesse pelo link:</span>
                     <span class="text-indigo-600 dark:text-indigo-400 font-mono font-bold text-base sm:text-lg">
                         {{ route('attendance.student-signup', $session->uuid) }}
                     </span>
                 </div>
            </div>
        @else
            <!-- METRICS CARD WHEN COMPLETED -->
            <flux:card class="lg:col-span-1 flex flex-col items-center justify-center p-6 space-y-4 shadow-sm border border-emerald-100 dark:border-emerald-950/20 bg-emerald-50/10 dark:bg-emerald-950/5">
                <div class="p-4 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon.check-circle class="w-12 h-12" />
                </div>
                <div class="text-center">
                    <flux:heading size="lg">Chamada Concluída</flux:heading>
                    <flux:subheading class="mt-1">A lista de presença foi gravada de forma definitiva e o e-mail enviado ao professor.</flux:subheading>
                </div>
            </flux:card>
        @endif

        <!-- DETAILS CARD -->
        <flux:card class="lg:col-span-2 space-y-4">
            <flux:heading size="lg">Resumo da Aula</flux:heading>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                        <flux:icon.users class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-2xl font-black tracking-tight">{{ $session->records->count() }}</div>
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Presentes no Momento</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
                        <flux:icon.clock class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-lg font-bold tracking-tight">{{ $session->created_at->setTimezone('America/Bahia')->format('d/m/Y H:i') }}</div>
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Início da Chamada</div>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs text-zinc-500 space-y-2">
                <div class="flex justify-between border-b border-zinc-150 dark:border-zinc-800 pb-1.5">
                    <span class="font-bold">E-mail de Destino:</span>
                    <span>{{ auth()->user()->email }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">ID Único da Chamada:</span>
                    <span class="font-mono text-[10px]">{{ $session->uuid }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- STUDENT LIST -->
    <flux:card class="relative overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="mb-4">
            <flux:heading size="lg" class="font-extrabold tracking-tight">Estudantes Confirmados</flux:heading>
            <flux:subheading>Esta lista atualiza automaticamente a cada 3 segundos enquanto a chamada estiver ativa.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome do Estudante</flux:table.column>
                <flux:table.column>Horário de Check-in</flux:table.column>
                @if($session->require_geolocation)
                    <flux:table.column>Localização</flux:table.column>
                @endif
                <flux:table.column>Endereço IP</flux:table.column>
                <flux:table.column>Navegador / Dispositivo</flux:table.column>
                <flux:table.column>Ações</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($session->records->sortBy(fn($record) => \Illuminate\Support\Str::slug($record->student_name)) as $record)
                    <flux:table.row class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">
                        <flux:table.cell>
                            <span class="font-bold text-zinc-900 dark:text-zinc-50">{{ $record->student_name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $record->created_at->setTimezone('America/Bahia')->format('H:i:s') }}
                        </flux:table.cell>
                        @if($session->require_geolocation)
                            <flux:table.cell>
                                @if($record->is_valid_location === true)
                                    <flux:badge color="green" size="sm" icon="map-pin" tooltip="Distância aproximada calculada: {{ $record->distance_meters }} metros">Válido (Próximo)</flux:badge>
                                @elseif($record->is_valid_location === false && $record->distance_meters !== null)
                                    <flux:badge color="red" size="sm" icon="exclamation-triangle" tooltip="Distância aproximada: {{ $record->distance_meters > 1000 ? round($record->distance_meters / 1000, 1) . ' km' : $record->distance_meters . ' metros' }}">Fora da Área</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" icon="no-symbol" tooltip="O aluno recusou a permissão de GPS no celular.">GPS Bloqueado</flux:badge>
                                @endif
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <span class="font-mono text-xs text-zinc-500">{{ $record->ip_address ?? 'N/D' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate" title="{{ $record->user_agent }}">
                            <span class="text-xs text-zinc-400 font-medium">{{ \Illuminate\Support\Str::limit($record->user_agent, 40) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="deleteRecord({{ $record->id }})" wire:confirm="Tem certeza que deseja excluir o registro de presença de {{ $record->student_name }}?" size="xs" variant="ghost" color="danger" icon="trash" tooltip="Excluir Presença" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $session->require_geolocation ? '6' : '5' }}" class="text-center text-zinc-500 py-12 italic">
                            Aguardando a confirmação de presença dos alunos...
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if($session->classroom_id)
        <flux:card class="mt-6 relative overflow-hidden border border-red-200 dark:border-red-900/30 shadow-sm bg-red-50/10 dark:bg-red-950/5">
            <div class="mb-4">
                <flux:heading size="lg" class="font-extrabold tracking-tight text-red-600 dark:text-red-400">Estudantes Ausentes ({{ $this->absentees->count() }})</flux:heading>
                <flux:subheading>Alunos matriculados na turma que ainda não registraram presença.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @forelse($this->absentees as $absentee)
                    <div class="p-3 bg-white dark:bg-zinc-900 border border-red-100 dark:border-red-900/20 rounded-lg flex items-center gap-2">
                        <flux:icon.x-circle class="w-5 h-5 text-red-500" />
                        <span class="font-medium text-sm text-zinc-700 dark:text-zinc-300">{{ $absentee->name }}</span>
                    </div>
                @empty
                    <div class="col-span-full p-4 text-center text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/20">
                        <flux:icon.check-badge class="w-6 h-6 mx-auto mb-2" />
                        <span class="font-bold">Todos os alunos da turma estão presentes!</span>
                    </div>
                @endforelse
            </div>
        </flux:card>
    @endif

    <!-- Modal de Edição de Turma -->
    <flux:modal name="edit-classroom-modal" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">Editar Chamada</flux:heading>
            
            <flux:input wire:model="new_class_name" label="Nome/Título da Chamada" required />

            <flux:select wire:model="new_classroom_id" label="Vincular a uma Turma (Opcional)">
                <flux:select.option value="">-- Nenhuma (Chamada Avulsa) --</flux:select.option>
                @foreach (auth()->user()->classrooms as $classroom)
                    <flux:select.option value="{{ $classroom->id }}">{{ $classroom->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button wire:click="updateClassroom" variant="primary">Salvar Alterações</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
