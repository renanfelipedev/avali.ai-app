<?php

use App\Models\Plan;
use App\Models\Subscription;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        $currentPlan = $user->currentPlan();
        $activeSubscription = $user->activeSubscription;

        $examsLimit = $currentPlan->getFeature('monthly_exams_limit', 5);
        $examsUsed = $user->getCurrentMonthExamsCount();
        $examsPercentage = $examsLimit > 0 ? min(100, round(($examsUsed / $examsLimit) * 100)) : 0;

        $submissionsLimit = $currentPlan->getFeature('monthly_submissions_limit', 25);
        $submissionsUsed = $user->getCurrentMonthSubmissionsCount();
        $submissionsPercentage = $submissionsLimit > 0 ? min(100, round(($submissionsUsed / $submissionsLimit) * 100)) : 0;

        return [
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'currentPlan' => $currentPlan,
            'activeSubscription' => $activeSubscription,
            'examsUsed' => $examsUsed,
            'examsLimit' => $examsLimit,
            'examsPercentage' => $examsPercentage,
            'submissionsUsed' => $submissionsUsed,
            'submissionsLimit' => $submissionsLimit,
            'submissionsPercentage' => $submissionsPercentage,
        ];
    }

    public function selectPlan(int $planId): void
    {
        $user = Auth::user();
        $plan = Plan::findOrFail($planId);

        if ($user->currentPlan()->id === $plan->id) {
            Flux::toast(variant: 'warning', heading: 'Plano Atual', text: 'Você já está utilizando este plano.');
            return;
        }

        // Atualiza ou cria a assinatura do usuário (pronto para receber webhooks do Stripe/MercadoPago/Asaas)
        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'gateway' => 'manual',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]
        );

        Flux::toast(
            heading: 'Plano Atualizado!',
            text: "Seu plano foi alterado para {$plan->name}. Os novos limites e prioridades já estão ativos!"
        );
    }
};
?>

<div>
    <!-- BREADCRUMBS -->
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('home') }}">Home</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Planos & Assinatura</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <!-- PAGE HEADER -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-extrabold tracking-tight">Planos & Assinatura</flux:heading>
            <flux:subheading>Gerencie sua assinatura, acompanhe o consumo mensal de IA e desbloqueie superpoderes pedagógicos.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge color="indigo" class="font-bold px-3 py-1">
                Plano Atual: {{ $currentPlan->name }}
            </flux:badge>
        </div>
    </div>

    <!-- CARD: CONSUMO DO MÊS ATUAL -->
    <flux:card class="mb-10 bg-gradient-to-br from-white to-zinc-50 dark:from-zinc-900 dark:to-zinc-950 border border-zinc-200 dark:border-zinc-800 shadow-sm p-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-zinc-200 dark:border-zinc-800">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Ciclo Atual</span>
                <flux:heading size="lg" class="font-bold mt-1">Consumo de IA em {{ now()->translatedFormat('F / Y') }}</flux:heading>
                <p class="text-xs text-zinc-500 mt-0.5">As cotas são renovadas automaticamente no primeiro dia de cada mês.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="text-xs text-zinc-500">Prioridade na Fila:</span>
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        @if($currentPlan->getFeature('queue_priority') === 'vip')
                            🚀 VIP Instantânea
                        @elseif($currentPlan->getFeature('queue_priority') === 'high')
                            ⚡ Alta Prioridade
                        @else
                            🐢 Fila Padrão
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6">
            <!-- Barra 1: Provas Geradas -->
            <div class="space-y-2">
                <div class="flex justify-between items-center text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">Geração de Provas com IA</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $examsUsed }} / {{ $examsLimit === -1 ? 'Ilimitado' : $examsLimit }}
                    </span>
                </div>
                @if($examsLimit === -1)
                    <div class="w-full bg-emerald-100 dark:bg-emerald-950/40 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-emerald-500 h-2.5 rounded-full w-full"></div>
                    </div>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Geração ilimitada ativa!</span>
                @else
                    <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                        <div class="h-2.5 rounded-full {{ $examsPercentage >= 90 ? 'bg-rose-500' : ($examsPercentage >= 70 ? 'bg-amber-500' : 'bg-indigo-600') }}" style="width: {{ $examsPercentage }}%"></div>
                    </div>
                    <span class="text-[11px] text-zinc-500">{{ 100 - $examsPercentage }}% restante neste ciclo.</span>
                @endif
            </div>

            <!-- Barra 2: Correções Realizadas -->
            <div class="space-y-2">
                <div class="flex justify-between items-center text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">Correções com OCR Multimodal</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $submissionsUsed }} / {{ $submissionsLimit === -1 ? 'Ilimitado' : $submissionsLimit }}
                    </span>
                </div>
                @if($submissionsLimit === -1)
                    <div class="w-full bg-emerald-100 dark:bg-emerald-950/40 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-emerald-500 h-2.5 rounded-full w-full"></div>
                    </div>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Correções ilimitadas ativas!</span>
                @else
                    <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                        <div class="h-2.5 rounded-full {{ $submissionsPercentage >= 90 ? 'bg-rose-500' : ($submissionsPercentage >= 70 ? 'bg-amber-500' : 'bg-indigo-600') }}" style="width: {{ $submissionsPercentage }}%"></div>
                    </div>
                    <span class="text-[11px] text-zinc-500">{{ 100 - $submissionsPercentage }}% restante neste ciclo.</span>
                @endif
            </div>
        </div>
    </flux:card>

    <!-- CARDS DE PLANOS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        @foreach($plans as $plan)
            @php
                $isCurrent = $currentPlan->id === $plan->id;
            @endphp
            <div class="relative flex flex-col justify-between rounded-3xl border {{ $plan->is_featured ? 'border-indigo-500 ring-2 ring-indigo-500/20 bg-gradient-to-b from-indigo-50/20 to-transparent dark:from-indigo-950/10' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' }} p-8 shadow-sm transition-all hover:shadow-md">
                @if($plan->is_featured)
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-3 py-1 text-xs font-black uppercase tracking-wider text-white shadow-sm">
                            <flux:icon.sparkles class="w-3.5 h-3.5" /> Mais Popular
                        </span>
                    </div>
                @endif

                <div>
                    <!-- Nome e Preço -->
                    <div class="mb-6">
                        <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-zinc-50">{{ $plan->name }}</flux:heading>
                        <p class="text-xs text-zinc-500 mt-1 min-h-[32px]">{{ $plan->description }}</p>
                        
                        <div class="mt-4 flex items-baseline gap-1">
                            <span class="text-3xl font-black tracking-tight text-zinc-900 dark:text-zinc-50">
                                {{ $plan->formattedPrice() }}
                            </span>
                            @if($plan->price_cents > 0)
                                <span class="text-xs font-medium text-zinc-500">/ mês</span>
                            @endif
                        </div>
                    </div>

                    <!-- Lista de Recursos -->
                    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-6 space-y-3.5 text-xs">
                        <!-- Limite de Provas -->
                        <div class="flex items-center gap-2.5">
                            <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <span>
                                <strong>{{ $plan->getFeature('monthly_exams_limit') === -1 ? 'Provas Ilimitadas' : $plan->getFeature('monthly_exams_limit') . ' provas' }}</strong> por mês
                            </span>
                        </div>

                        <!-- Limite de Correções -->
                        <div class="flex items-center gap-2.5">
                            <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <span>
                                <strong>{{ $plan->getFeature('monthly_submissions_limit') === -1 ? 'Correções Ilimitadas' : $plan->getFeature('monthly_submissions_limit') . ' correções' }}</strong> / mês
                            </span>
                        </div>

                        <!-- Imagens e Tirinhas -->
                        <div class="flex items-center gap-2.5">
                            @if($plan->allows('allow_question_images'))
                                <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">Imagens, Tirinhas & Charges</span>
                            @else
                                <flux:icon.x-mark class="w-4 h-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                                <span class="text-zinc-400 dark:text-zinc-500 line-through">Imagens e Tirinhas na prova</span>
                            @endif
                        </div>

                        <!-- Prioridade na Fila -->
                        <div class="flex items-center gap-2.5">
                            <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <span>
                                Fila de IA: <strong>{{ ucfirst($plan->getFeature('queue_priority', 'default')) }}</strong>
                            </span>
                        </div>

                        <!-- Perfis de Impressão Word/PDF -->
                        <div class="flex items-center gap-2.5">
                            @if($plan->allows('allow_custom_print_profiles'))
                                <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>Cabeçalho Escolar & Exportação Word</span>
                            @else
                                <flux:icon.x-mark class="w-4 h-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                                <span class="text-zinc-400 dark:text-zinc-500">Cabeçalho Escolar Oficial</span>
                            @endif
                        </div>

                        <!-- Google Classroom -->
                        <div class="flex items-center gap-2.5">
                            @if($plan->allows('allow_google_classroom'))
                                <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span class="font-semibold text-indigo-600 dark:text-indigo-400">Google Classroom Integrado</span>
                            @else
                                <flux:icon.x-mark class="w-4 h-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                                <span class="text-zinc-400 dark:text-zinc-500 line-through">Google Classroom Integrado</span>
                            @endif
                        </div>

                        <!-- Chamada Online -->
                        <div class="flex items-center gap-2.5">
                            @if($plan->allows('allow_geofencing_attendance'))
                                <flux:icon.check class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                                <span>Chamada com GPS & PIN Dinâmico</span>
                            @else
                                <flux:icon.check class="w-4 h-4 text-zinc-400 flex-shrink-0" />
                                <span class="text-zinc-500">Chamada Online Básica</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Botão de Ação -->
                <div class="mt-8 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    @if($isCurrent)
                        <flux:button disabled class="w-full font-bold opacity-75">
                            Plano Atual
                        </flux:button>
                    @else
                        <flux:button wire:click="selectPlan({{ $plan->id }})" wire:loading.attr="disabled"
                            variant="{{ $plan->is_featured ? 'primary' : 'filled' }}"
                            class="w-full font-bold">
                            {{ $plan->price_cents === 0 ? 'Mudar para Grátis' : ($isCurrent ? 'Plano Atual' : 'Selecionar Plano') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
