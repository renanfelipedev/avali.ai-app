<?php

use App\Models\Exam;
use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Services\GoogleClassroomService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component {
    // Active Tab State
    public string $active_tab = 'profile'; // 'profile', 'classroom', 'stats', 'activities'

    // Password state
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    // Summary data
    public int $total_exams = 0;
    public int $total_evaluations = 0;
    public int $total_submissions = 0;

    // Google Classroom courses list
    public array $google_courses = [];
    public bool $is_connected_google = false;

    // Recent items lists
    public $recent_exams = [];
    public $recent_evaluations = [];

    public function mount()
    {
        $user = auth()->user();

        // 1. Google Classroom integration detection
        $this->is_connected_google = !empty($user->google_token);
        if ($this->is_connected_google) {
            try {
                $classroomService = app(GoogleClassroomService::class);
                $this->google_courses = $classroomService->listCourses($user);
            } catch (\Exception $e) {
                // If token failed, mark as disconnected
                \Log::warning('Falha ao listar turmas no perfil', ['error' => $e->getMessage()]);
                $this->is_connected_google = false;
            }
        }

        // 2. Summary metrics
        $this->total_exams = $user->exams()->count();
        $this->total_evaluations = $user->examEvaluations()->count();
        
        $evaluationIds = $user->examEvaluations()->pluck('id');
        $this->total_submissions = ExamSubmission::whereIn('exam_evaluation_id', $evaluationIds)->count();

        // 3. Recent history
        $this->recent_exams = $user->exams()->latest()->take(5)->get();
        $this->recent_evaluations = $user->examEvaluations()->latest()->take(5)->get();
    }

    public function changePassword()
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'A senha atual é obrigatória.',
            'new_password.required' => 'A nova senha é obrigatória.',
            'new_password.min' => 'A nova senha deve ter no mínimo 8 caracteres.',
            'new_password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        $user = auth()->user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'A senha atual informada está incorreta.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password)
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('password_success', 'Sua senha foi alterada com sucesso.');
    }
};
?>

<div class="space-y-6">
    <!-- TITULO DA PAGINA -->
    <div class="mb-4 border-b border-zinc-150 dark:border-zinc-800 pb-4">
        <flux:heading size="xl" class="font-extrabold tracking-tight">👤 Meu Perfil e Central do Professor</flux:heading>
        <flux:subheading>Gerencie sua conta, visualize estatísticas integradas e configure suas integrações.</flux:subheading>
    </div>

    <!-- NAVEGAÇÃO POR ABAS (TOUCH-SWIPE EM SMARTPHONES E SEM QUEBRA DE LINHA) -->
    <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-3 overflow-x-auto scrollbar-none whitespace-nowrap">
        <flux:button wire:click="$set('active_tab', 'profile')" 
            variant="{{ $active_tab === 'profile' ? 'primary' : 'ghost' }}"
            icon="user" class="font-bold flex-shrink-0">1. Perfil & Senha</flux:button>
            
        <flux:button wire:click="$set('active_tab', 'classroom')" 
            variant="{{ $active_tab === 'classroom' ? 'primary' : 'ghost' }}"
            icon="academic-cap" class="font-bold flex-shrink-0">2. Google Classroom</flux:button>
            
        <flux:button wire:click="$set('active_tab', 'stats')" 
            variant="{{ $active_tab === 'stats' ? 'primary' : 'ghost' }}"
            icon="chart-bar" class="font-bold flex-shrink-0">3. Estatísticas</flux:button>
            
        <flux:button wire:click="$set('active_tab', 'activities')" 
            variant="{{ $active_tab === 'activities' ? 'primary' : 'ghost' }}"
            icon="clock" class="font-bold flex-shrink-0">4. Atividades Recentes</flux:button>
    </div>

    <!-- CONTEÚDO DINÂMICO CONFORME A ABA ATIVA -->
    <div class="mt-4 animate-fade-in">
        @if ($active_tab === 'profile')
            <!-- TÓPICO 1: DADOS PESSOAIS & SEGURANÇA -->
            <section class="space-y-4">
                <flux:card class="space-y-6 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <!-- User Basic Information (ReadOnly) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input label="Nome Completo" value="{{ auth()->user()->name }}" readonly disabled icon="user" />
                        <flux:input label="Endereço de E-mail" value="{{ auth()->user()->email }}" readonly disabled icon="envelope" />
                    </div>

                    <flux:separator />

                    <!-- Password Change Form -->
                    <form wire:submit="changePassword" class="space-y-4">
                        <flux:heading size="md" class="font-bold">Alterar Senha de Acesso</flux:heading>
                        <flux:subheading>Para alterar sua senha, preencha as informações abaixo com atenção.</flux:subheading>

                        @if (session()->has('password_success'))
                            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-700 dark:text-emerald-300 flex items-center gap-2 animate-fade-in shadow-sm">
                                <flux:icon.check-circle class="w-4 h-4 text-emerald-600" />
                                <span class="font-bold">{{ session('password_success') }}</span>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <flux:input type="password" wire:model="current_password" label="Senha Atual" placeholder="Sua senha de login" required icon="key" />
                            <flux:input type="password" wire:model="new_password" label="Nova Senha" placeholder="Mínimo 8 caracteres" required icon="lock-closed" />
                            <flux:input type="password" wire:model="new_password_confirmation" label="Confirmar Nova Senha" placeholder="Repita a nova senha" required icon="lock-closed" />
                        </div>

                        <div class="flex justify-end pt-2">
                            <flux:button type="submit" variant="primary" icon="key" class="font-bold">Alterar Senha</flux:button>
                        </div>
                    </form>
                </flux:card>
            </section>
        @endif

        @if ($active_tab === 'classroom')
            <!-- TÓPICO 2: INTEGRAÇÃO GOOGLE CLASSROOM -->
            <section class="space-y-4">
                @if ($is_connected_google)
                    <flux:card class="space-y-6 shadow-sm border border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/30 shadow-sm">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                    </svg>
                                </div>
                                <div>
                                    <flux:heading size="md" class="font-bold">Conexão Google Classroom Ativa</flux:heading>
                                    <flux:subheading>Sua conta do Google está vinculada com sucesso ao avali.ai.</flux:subheading>
                                </div>
                            </div>
                            <flux:badge color="green" class="animate-pulse">Conectado</flux:badge>
                        </div>

                        <div class="space-y-3">
                            <flux:heading size="sm" class="font-bold uppercase tracking-wider text-zinc-400">Suas Turmas Ativas:</flux:heading>
                            
                            @if (count($google_courses) > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach ($google_courses as $course)
                                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow">
                                            <div>
                                                <div class="font-bold text-zinc-800 dark:text-zinc-200 text-sm leading-snug">{{ $course['name'] }}</div>
                                                @if ($course['section'])
                                                    <div class="text-xs text-zinc-400 mt-0.5">{{ $course['section'] }}</div>
                                                @endif
                                            </div>
                                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-900/60 text-[10px] text-zinc-400">
                                                <span class="font-mono">ID: {{ substr($course['id'], 0, 10) }}...</span>
                                                <flux:badge color="emerald" size="sm">Ativo</flux:badge>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-xs text-zinc-500 italic py-3">Nenhuma turma ativa encontrada no Classroom.</div>
                            @endif
                        </div>
                    </flux:card>
                @else
                    <flux:card class="border-2 border-dashed border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950/20 shadow-sm p-8 text-center space-y-4">
                        <div class="p-4 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 max-w-max mx-auto shadow-sm">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </div>
                        <div class="max-w-md mx-auto space-y-2">
                            <flux:heading size="md" class="font-bold">Vincule sua conta Google Classroom</flux:heading>
                            <flux:subheading class="text-xs leading-relaxed">Conecte-se à sua conta educacional do Google para importar provas diretamente de suas tarefas e sincronizar notas dos alunos de forma totalmente automatizada.</flux:subheading>
                        </div>
                        <div class="pt-2">
                            <flux:button href="{{ route('auth.google') }}" color="green" icon="arrow-right-start-on-rectangle" class="font-bold">Conectar ao Google Classroom</flux:button>
                        </div>
                    </flux:card>
                @endif
            </section>
        @endif

        @if ($active_tab === 'stats')
            <!-- TÓPICO 3: RESUMO METRIFICADO -->
            <section class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Provas Geradas -->
                    <flux:card class="p-6 flex items-center gap-4 border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow bg-white dark:bg-zinc-900">
                        <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 shadow-sm">
                            <flux:icon.sparkles class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">{{ $total_exams }}</div>
                            <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Provas Geradas</div>
                        </div>
                    </flux:card>

                    <!-- Correções Criadas -->
                    <flux:card class="p-6 flex items-center gap-4 border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow bg-white dark:bg-zinc-900">
                        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 shadow-sm">
                            <flux:icon.check-badge class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">{{ $total_evaluations }}</div>
                            <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Correções Criadas</div>
                        </div>
                    </flux:card>

                    <!-- Alunos Corrigidos -->
                    <flux:card class="p-6 flex items-center gap-4 border border-zinc-200 dark:border-zinc-800 shadow-sm transition-all hover:shadow bg-white dark:bg-zinc-900">
                        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 shadow-sm">
                            <flux:icon.users class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">{{ $total_submissions }}</div>
                            <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Alunos Corrigidos</div>
                        </div>
                    </flux:card>
                </div>
            </section>
        @endif

        @if ($active_tab === 'activities')
            <!-- TÓPICO 4: ATIVIDADES RECENTES -->
            <section class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Ultimas Correções -->
                    <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-sm bg-white dark:bg-zinc-900">
                        <div class="pb-2 border-b border-zinc-100 dark:border-zinc-800">
                            <flux:heading size="md" class="font-bold">Últimas Correções Criadas</flux:heading>
                        </div>
                        
                        <div class="space-y-3">
                            @forelse ($recent_evaluations as $eval)
                                <div class="flex items-center justify-between gap-4 p-3 rounded-xl border border-zinc-100 dark:border-zinc-950 bg-zinc-50/50 dark:bg-zinc-950/50 shadow-inner">
                                    <div>
                                        <span class="font-bold text-xs text-zinc-800 dark:text-zinc-200 block truncate max-w-xs">{{ $eval->title }}</span>
                                        <span class="text-[10px] text-zinc-400 block mt-0.5">{{ $eval->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <flux:button href="{{ route('evaluations.show', $eval->id) }}" size="xs" variant="ghost" icon="eye" />
                                </div>
                            @empty
                                <div class="text-xs text-zinc-500 italic py-4 text-center">Nenhuma correção criada até o momento.</div>
                            @endforelse
                        </div>
                    </flux:card>

                    <!-- Ultimas Provas Geradas -->
                    <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-sm bg-white dark:bg-zinc-900">
                        <div class="pb-2 border-b border-zinc-100 dark:border-zinc-800">
                            <flux:heading size="md" class="font-bold">Últimas Provas Geradas</flux:heading>
                        </div>
                        
                        <div class="space-y-3">
                            @forelse ($recent_exams as $exam)
                                <div class="flex items-center justify-between gap-4 p-3 rounded-xl border border-zinc-100 dark:border-zinc-950 bg-zinc-50/50 dark:bg-zinc-950/50 shadow-inner">
                                    <div>
                                        <span class="font-bold text-xs text-zinc-800 dark:text-zinc-200 block truncate max-w-xs">{{ $exam->title }}</span>
                                        <span class="text-[10px] text-zinc-400 block mt-0.5">{{ $exam->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <flux:button href="{{ route('exams.show', $exam->id) }}" size="xs" variant="ghost" icon="eye" />
                                </div>
                            @empty
                                <div class="text-xs text-zinc-500 italic py-4 text-center">Nenhuma prova gerada até o momento.</div>
                            @endforelse
                        </div>
                    </flux:card>
                </div>
            </section>
        @endif
    </div>
</div>
