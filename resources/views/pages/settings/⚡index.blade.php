<?php

use App\Models\SystemSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component
{
    public string $tab = 'security'; // 'security' | 'general'

    // Aba Segurança & Anti-bot
    public bool $recaptcha_enabled = true;

    public string $recaptcha_site_key = '';

    public string $recaptcha_secret_key = '';

    public float $recaptcha_min_score = 0.5;

    public bool $allow_registration = true;

    public bool $auto_activate_users = false;

    public bool $google_login_enabled = true;

    // Estado do teste do reCAPTCHA
    public bool $isTestingRecaptcha = false;

    public ?array $recaptchaTestResult = null;

    // Aba Geral do Sistema
    public bool $maintenance_banner_enabled = false;

    public string $maintenance_banner_message = '';

    public int $daily_exam_limit_per_teacher = 20;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);

        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        // Segurança & reCAPTCHA
        $this->recaptcha_enabled = SystemSetting::getBool('recaptcha_enabled', true);
        $this->recaptcha_site_key = (string) SystemSetting::get('recaptcha_site_key', config('services.recaptcha.site_key', ''));
        $this->recaptcha_secret_key = (string) SystemSetting::get('recaptcha_secret_key', config('services.recaptcha.secret_key', ''));
        $this->recaptcha_min_score = SystemSetting::getFloat('recaptcha_min_score', (float) config('services.recaptcha.min_score', 0.5));
        $this->allow_registration = SystemSetting::getBool('allow_registration', true);
        $this->auto_activate_users = SystemSetting::getBool('auto_activate_users', false);
        $this->google_login_enabled = SystemSetting::getBool('google_login_enabled', true);

        // Geral
        $this->maintenance_banner_enabled = SystemSetting::getBool('maintenance_banner_enabled', false);
        $this->maintenance_banner_message = (string) SystemSetting::get('maintenance_banner_message', '');
        $this->daily_exam_limit_per_teacher = (int) SystemSetting::get('daily_exam_limit_per_teacher', 20);
    }

    public function saveSecurity(): void
    {
        $this->validate([
            'recaptcha_min_score' => 'required|numeric|min:0.1|max:1.0',
            'recaptcha_site_key' => 'nullable|string|max:255',
            'recaptcha_secret_key' => 'nullable|string|max:255',
        ]);

        SystemSetting::set('recaptcha_enabled', $this->recaptcha_enabled);
        SystemSetting::set('recaptcha_site_key', trim($this->recaptcha_site_key));
        SystemSetting::set('recaptcha_secret_key', trim($this->recaptcha_secret_key));
        SystemSetting::set('recaptcha_min_score', $this->recaptcha_min_score);
        SystemSetting::set('allow_registration', $this->allow_registration);
        SystemSetting::set('auto_activate_users', $this->auto_activate_users);
        SystemSetting::set('google_login_enabled', $this->google_login_enabled);

        Log::info('Configurações de segurança atualizadas pelo administrador ID '.Auth::id());

        Flux::toast('Configurações de segurança salvas com sucesso!');
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'maintenance_banner_message' => 'nullable|string|max:500',
            'daily_exam_limit_per_teacher' => 'required|integer|min:1|max:500',
        ]);

        SystemSetting::set('maintenance_banner_enabled', $this->maintenance_banner_enabled);
        SystemSetting::set('maintenance_banner_message', trim($this->maintenance_banner_message));
        SystemSetting::set('daily_exam_limit_per_teacher', $this->daily_exam_limit_per_teacher);

        Log::info('Configurações gerais atualizadas pelo administrador ID '.Auth::id());

        Flux::toast('Configurações gerais salvas com sucesso!');
    }

    public function testRecaptchaKeys(): void
    {
        $this->isTestingRecaptcha = true;
        $this->recaptchaTestResult = null;

        $secretKey = trim($this->recaptcha_secret_key) ?: config('services.recaptcha.secret_key');

        if (empty($secretKey)) {
            $this->recaptchaTestResult = [
                'success' => false,
                'message' => 'Nenhuma Secret Key configurada para testar.',
            ];
            $this->isTestingRecaptcha = false;

            return;
        }

        try {
            // Enviamos uma verificação intencionalmente com token simulado para checar a chave secreta com o Google
            $response = Http::asForm()->timeout(6)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => 'test_health_ping',
            ]);

            $json = $response->json();
            $errors = $json['error-codes'] ?? [];

            // Se o Google reclamar que a secret é inválida ('invalid-input-secret'):
            if (in_array('invalid-input-secret', $errors, true)) {
                $this->recaptchaTestResult = [
                    'success' => false,
                    'message' => 'A Secret Key foi rejeitada pelo Google (chave inválida).',
                ];
                Flux::toast(variant: 'danger', heading: 'Falha na Chave', text: 'O Google rejeitou esta Secret Key.');
            } else {
                // Se retornou 'invalid-input-response', significa que a Secret Key É VÁLIDA e foi aceita pelo Google!
                $this->recaptchaTestResult = [
                    'success' => true,
                    'message' => 'A Secret Key é autêntica e foi aceita pelos servidores do Google!',
                ];
                Flux::toast('Chave Secreta validada com sucesso pelo Google!');
            }
        } catch (Throwable $e) {
            $this->recaptchaTestResult = [
                'success' => false,
                'message' => 'Erro ao comunicar com a API do Google: '.$e->getMessage(),
            ];
            Flux::toast(variant: 'danger', heading: 'Erro de Conexão', text: 'Não foi possível conectar ao Google.');
        } finally {
            $this->isTestingRecaptcha = false;
        }
    }
};
?>

<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl">Configurações do Sistema</flux:heading>
            <flux:subheading>Gerencie as regras operacionais, segurança anti-bot e parâmetros da plataforma.</flux:subheading>
        </div>
    </div>

    {{-- Tabs de Navegação --}}
    <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-800">
        <button
            type="button"
            wire:click="$set('tab', 'security')"
            class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $tab === 'security' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="shield-check" class="size-4" />
                <span>Segurança & Anti-Bot</span>
            </div>
        </button>

        <button
            type="button"
            wire:click="$set('tab', 'general')"
            class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $tab === 'general' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="adjustments-horizontal" class="size-4" />
                <span>Geral & Limites</span>
            </div>
        </button>
    </div>

    {{-- ABA: SEGURANÇA & ANTI-BOT --}}
    @if ($tab === 'security')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Coluna Principal: Formulário --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Card reCAPTCHA --}}
                <flux:card class="space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">Google reCAPTCHA v3</flux:heading>
                            <flux:subheading>Proteção invisível contra robôs e automações no cadastro público.</flux:subheading>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($recaptcha_enabled)
                                <flux:badge size="sm" color="green">Ativado</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Desativado</flux:badge>
                            @endif
                        </div>
                    </div>

                    <flux:separator />

                    <div class="space-y-4">
                        {{-- Toggle Liga/Desliga --}}
                        <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                            <div>
                                <div class="font-medium text-sm text-zinc-900 dark:text-white">Exigir reCAPTCHA no cadastro</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Se desativado, o formulário de cadastro aceitará submissões sem validar o token do Google (útil em caso de instabilidade da API do Google).
                                </div>
                            </div>
                            <flux:switch wire:model.live="recaptcha_enabled" />
                        </div>

                        {{-- Site Key --}}
                        <flux:input
                            label="Site Key (Pública)"
                            wire:model="recaptcha_site_key"
                            placeholder="6Lfkh..."
                            description="Chave pública carregada no navegador do usuário."
                        />

                        {{-- Secret Key --}}
                        <flux:input
                            label="Secret Key (Privada)"
                            type="password"
                            wire:model="recaptcha_secret_key"
                            placeholder="••••••••••••••••••••••••••••••••••••••••"
                            description="Chave privada utilizada pelo backend para autenticar no Google."
                        />

                        {{-- Score Mínimo --}}
                        <div>
                            <flux:input
                                label="Score Mínimo de Aprovação (0.1 a 1.0)"
                                type="number"
                                step="0.05"
                                min="0.1"
                                max="1.0"
                                wire:model="recaptcha_min_score"
                                description="0.5 é o padrão recomendado. Valores maiores (ex: 0.7) são mais rígidos; menores (ex: 0.3) são mais tolerantes."
                            />
                        </div>
                    </div>

                    {{-- Resultado do teste --}}
                    @if ($recaptchaTestResult)
                        <flux:callout :variant="$recaptchaTestResult['success'] ? 'success' : 'danger'" icon="check-circle" class="mt-4">
                            <flux:callout.text>
                                {{ $recaptchaTestResult['message'] }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:button
                            variant="outline"
                            icon="bolt"
                            wire:click="testRecaptchaKeys"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="testRecaptchaKeys">Testar Chaves com Google</span>
                            <span wire:loading wire:target="testRecaptchaKeys">Testando...</span>
                        </flux:button>

                        <flux:button variant="primary" wire:click="saveSecurity">
                            Salvar Alterações
                        </flux:button>
                    </div>
                </flux:card>

                {{-- Card Controle de Cadastros --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">Controle de Cadastros Públicos</flux:heading>
                        <flux:subheading>Controle se novos usuários podem solicitar cadastro no sistema.</flux:subheading>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <div>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">Permitir novos cadastros públicos</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                Quando desmarcado, a página pública <code class="font-mono px-1 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700">/cadastro</code> não aceitará novas submissões.
                            </div>
                        </div>
                        <flux:switch wire:model="allow_registration" />
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <div>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">Ativar novos usuários automaticamente</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                Quando ativado, os novos cadastros serão criados diretamente com status "Ativo" e poderão realizar login sem aguardar aprovação manual do administrador.
                            </div>
                        </div>
                        <flux:switch wire:model="auto_activate_users" />
                    </div>

                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:button variant="primary" wire:click="saveSecurity">
                            Salvar Alterações
                        </flux:button>
                    </div>
                </flux:card>

                {{-- Card Login Social com Google --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">Autenticação com o Google (OAuth)</flux:heading>
                        <flux:subheading>Gerencie a disponibilidade do login e cadastro rápido com conta Google.</flux:subheading>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <div>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">Permitir login e cadastro com o Google</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                Quando desmarcado, o botão "Entrar com o Google" desaparece das telas de Login e Cadastro, bloqueando o fluxo de OAuth.
                            </div>
                        </div>
                        <flux:switch wire:model="google_login_enabled" />
                    </div>

                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:button variant="primary" wire:click="saveSecurity">
                            Salvar Alterações
                        </flux:button>
                    </div>
                </flux:card>
            </div>

            {{-- Coluna Lateral: Ajuda & Status --}}
            <div class="space-y-6">
                <flux:card class="bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-100 dark:border-indigo-900/50">
                    <div class="flex items-start gap-3">
                        <flux:icon name="information-circle" class="size-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                        <div class="space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                            <div class="font-semibold text-zinc-900 dark:text-white text-sm">Padrão com Fallback</div>
                            <p>
                                Caso os campos de chaves fiquem vazios aqui no painel, o sistema utilizará automaticamente as chaves definidas no arquivo <code class="font-mono bg-zinc-100 dark:bg-zinc-800 px-1 py-0.5 rounded">.env</code>.
                            </p>
                            <p>
                                Isso permite alternar credenciais ou ajustar o score instantaneamente pelo painel sem precisar reiniciar os serviços de produção.
                            </p>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="sm" class="mb-3">Camadas Ativas de Proteção</flux:heading>
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Rate Limiter (10 req/min)</span>
                            <flux:badge size="sm" color="green">Ativo</flux:badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Campo Armadilha (Honeypot)</span>
                            <flux:badge size="sm" color="green">Ativo</flux:badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">reCAPTCHA v3</span>
                            @if ($recaptcha_enabled)
                                <flux:badge size="sm" color="green">Ativo</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Inativo</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Login com Google</span>
                            @if ($google_login_enabled)
                                <flux:badge size="sm" color="green">Habilitado</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Desabilitado</flux:badge>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    {{-- ABA: GERAL & LIMITES --}}
    @if ($tab === 'general')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">Banner de Manutenção ou Aviso Global</flux:heading>
                        <flux:subheading>Exibe um aviso em destaque no topo da área de trabalho de todos os usuários logados.</flux:subheading>
                    </div>

                    <flux:separator />

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                            <div>
                                <div class="font-medium text-sm text-zinc-900 dark:text-white">Exibir Banner de Aviso</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Ative para alertar professores sobre novidades, manutenções programadas ou avisos urgentes.
                                </div>
                            </div>
                            <flux:switch wire:model="maintenance_banner_enabled" />
                        </div>

                        <flux:input
                            label="Mensagem do Aviso"
                            wire:model="maintenance_banner_message"
                            placeholder="Ex: Manutenção programada hoje a partir das 22h. Salve seu trabalho."
                        />
                    </div>

                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:button variant="primary" wire:click="saveGeneral">
                            Salvar Alterações
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">Limites de Uso da IA</flux:heading>
                        <flux:subheading>Políticas de contenção para uso sustentável de tokens e APIs.</flux:subheading>
                    </div>

                    <flux:separator />

                    <flux:input
                        label="Limite diário de geração de provas por professor"
                        type="number"
                        min="1"
                        max="500"
                        wire:model="daily_exam_limit_per_teacher"
                        description="Evita consumo descontrolado das cotas da API Gemini."
                    />

                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:button variant="primary" wire:click="saveGeneral">
                            Salvar Alterações
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif
</div>
