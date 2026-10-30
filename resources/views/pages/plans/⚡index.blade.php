<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AsaasService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component {
    public bool $showCheckoutModal = false;
    public ?Plan $selectedPlan = null;
    public string $cpf = '';
    public bool $isProcessingCheckout = false;
    public ?string $paymentUrl = null;

    public function mount(): void
    {
        $this->cpf = Auth::user()->cpf ?? '';
    }

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

        // Se o plano for gratuito, altera diretamente
        if ($plan->price_cents === 0) {
            Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'gateway' => 'manual',
                    'current_period_start' => now(),
                    'current_period_end' => null,
                ]
            );

            Flux::toast(
                heading: 'Plano Atualizado!',
                text: "Seu plano foi alterado para {$plan->name}."
            );
            return;
        }

        // Para planos pagos, abre o modal de checkout com Asaas
        $this->selectedPlan = $plan;
        $this->cpf = $user->cpf ?? '';
        $this->paymentUrl = null;
        $this->showCheckoutModal = true;
    }

    public function confirmCheckout(AsaasService $asaasService): void
    {
        $user = Auth::user();

        if (! $this->selectedPlan) {
            return;
        }

        $this->validate([
            'cpf' => ['required', 'string', 'min:11'],
        ], [
            'cpf.required' => 'Informe o CPF do titular para emissão da assinatura.',
            'cpf.min' => 'O CPF informado está incompleto.',
        ]);

        $this->isProcessingCheckout = true;

        try {
            $cleanCpf = preg_replace('/\D/', '', $this->cpf);

            // Atualiza CPF do usuário
            $user->cpf = $cleanCpf;
            $user->save();

            // Atualiza ou cria cliente no Asaas
            $asaasService->updateCustomer($user, ['cpfCnpj' => $cleanCpf]);

            // Cria assinatura recorrente no Asaas
            $subResponse = $asaasService->createSubscription($user, $this->selectedPlan);
            $subId = $subResponse['id'] ?? null;

            // Cria registro de assinatura pendente aguardando webhook de pagamento
            Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_id' => $this->selectedPlan->id,
                    'status' => 'pending',
                    'gateway' => 'asaas',
                    'gateway_subscription_id' => $subId,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]
            );

            // Recupera a primeira cobrança para pegar o link da fatura (Pix, Cartão, Boleto)
            $payments = $asaasService->getSubscriptionPayments($subId);
            $firstPayment = $payments[0] ?? null;

            if ($firstPayment && ! empty($firstPayment['invoiceUrl'])) {
                $this->paymentUrl = $firstPayment['invoiceUrl'];
                // Redireciona para o checkout do Asaas
                $this->redirect($this->paymentUrl);
                return;
            }

            $this->showCheckoutModal = false;
            Flux::toast(
                heading: 'Assinatura Iniciada!',
                text: 'Sua assinatura foi criada. Assim que o pagamento for aprovado, seu plano será ativado!'
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Erro no Checkout',
                text: $e->getMessage()
            );
        } finally {
            $this->isProcessingCheckout = false;
        }
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

    <!-- GRID DE PLANOS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        @foreach($plans as $plan)
            @php
                $isCurrent = $currentPlan->id === $plan->id;
            @endphp
            <div class="relative flex flex-col justify-between rounded-3xl p-6 sm:p-8 transition-all duration-300
                {{ $plan->is_featured ? 'bg-white dark:bg-zinc-900 border-2 border-indigo-500 shadow-xl shadow-indigo-500/10 scale-[1.02]' : 'bg-white/80 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700' }}
                {{ $isCurrent ? 'ring-2 ring-emerald-500/50' : '' }}">

                @if($plan->is_featured)
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-[11px] font-black uppercase tracking-widest px-3.5 py-1 rounded-full shadow-md">
                            Mais Escolhido
                        </span>
                    </div>
                @endif

                <div>
                    <!-- Cabeçalho do Card -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <flux:heading size="lg" class="font-extrabold">{{ $plan->name }}</flux:heading>
                            <p class="text-xs text-zinc-500 mt-1 line-clamp-2">{{ $plan->description }}</p>
                        </div>
                    </div>

                    <!-- Preço -->
                    <div class="my-6">
                        <div class="flex items-baseline gap-1">
                            @if($plan->price_cents === 0)
                                <span class="text-4xl font-black text-zinc-900 dark:text-white">Grátis</span>
                            @else
                                <span class="text-2xl font-bold text-zinc-500">R$</span>
                                <span class="text-4xl font-black text-zinc-900 dark:text-white">
                                    {{ number_format($plan->price_cents / 100, 2, ',', '.') }}
                                </span>
                                <span class="text-xs text-zinc-500 font-medium">/ mês</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-zinc-400 mt-1">Cancele quando quiser, sem fidelidade.</p>
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
                            {{ $plan->price_cents === 0 ? 'Mudar para Grátis' : ($isCurrent ? 'Plano Atual' : 'Assinar Plano') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- MODAL DE CHECKOUT COM ASAAS -->
    <flux:modal wire:model="showCheckoutModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="font-extrabold">Finalizar Assinatura</flux:heading>
                <flux:subheading>
                    Você está prestes a assinar o plano <strong>{{ $selectedPlan?->name }}</strong> por
                    <strong>R$ {{ number_format(($selectedPlan?->price_cents ?? 0) / 100, 2, ',', '.') }} / mês</strong>.
                </flux:subheading>
            </div>

            <form wire:submit="confirmCheckout" class="space-y-4">
                <flux:input wire:model="cpf" label="CPF do Titular" placeholder="000.000.000-00" required
                    description="Obrigatório para emissão de cobrança e notas fiscais pelo Asaas (Pix, Cartão ou Boleto)." />

                <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-700 dark:text-indigo-300 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <flux:icon.shield-check class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        Pagamento Seguro via Asaas Gateway
                    </p>
                    <p>Você poderá escolher pagar via <strong>Pix com ativação instantânea</strong>, Cartão de Crédito ou Boleto bancário.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button type="button" wire:click="$set('showCheckoutModal', false)" variant="ghost">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="font-bold">
                        <span wire:loading.remove wire:target="confirmCheckout">Ir para Pagamento Seguro</span>
                        <span wire:loading wire:target="confirmCheckout">Gerando Cobrança...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
