<?php

use App\Models\GeminiApiKey;
use App\Services\GeminiApiKeyService;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.main')] class extends Component {
    public string $tab = 'keys'; // 'keys' | 'models'

    public $showModal = false;
    public ?int $editingKeyId = null;

    // Form fields
    public string $name = '';
    public string $key = '';
    public bool $is_active = true;
    public bool $is_default = false;
    public int $priority = 0;

    // Key test state
    public ?int $testingKeyId = null;
    public ?array $testResult = null;
    public bool $showTestModal = false;
    public bool $isTestingActive = false;

    // Models Explorer state
    public array $apiModels = [];
    public bool $isLoadingModels = false;
    public ?string $testingModel = null;
    public ?array $modelTestOutput = null;
    public bool $showModelTestModal = false;

    public function createKey(): void
    {
        $this->reset(['editingKeyId', 'name', 'key', 'priority']);
        $this->is_active = true;
        $this->is_default = GeminiApiKey::count() === 0;
        $this->showModal = true;
    }

    public function editKey(int $id): void
    {
        $apiKey = GeminiApiKey::findOrFail($id);

        $this->editingKeyId = $apiKey->id;
        $this->name = $apiKey->name;
        $this->key = '';
        $this->is_active = (bool) $apiKey->is_active;
        $this->is_default = (bool) $apiKey->is_default;
        $this->priority = (int) $apiKey->priority;

        $this->showModal = true;
    }

    public function save(GeminiApiKeyService $keyService): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];

        if (! $this->editingKeyId) {
            $rules['key'] = 'required|string|min:20';
        } else {
            $rules['key'] = 'nullable|string|min:20';
        }

        $this->validate($rules, [
            'name.required' => 'Informe um nome identificador para a chave.',
            'key.required' => 'Insira a chave de API do Gemini.',
            'key.min' => 'A chave de API do Gemini deve ter pelo menos 20 caracteres.',
        ]);

        if ($this->editingKeyId) {
            $apiKey = GeminiApiKey::findOrFail($this->editingKeyId);

            $data = [
                'name' => $this->name,
                'is_active' => $this->is_active,
                'is_default' => $this->is_default,
                'priority' => $this->priority,
            ];

            if (! empty($this->key)) {
                $data['key'] = trim($this->key);
                $data['status'] = 'active';
                $data['last_error_message'] = null;
                $data['rate_limited_until'] = null;
            }

            $apiKey->update($data);

            Flux::toast('Chave de API atualizada com sucesso!');
        } else {
            $apiKey = GeminiApiKey::create([
                'name' => $this->name,
                'key' => trim($this->key),
                'is_active' => $this->is_active,
                'is_default' => $this->is_default,
                'priority' => $this->priority,
                'status' => 'active',
            ]);

            Flux::toast('Nova chave de API cadastrada com sucesso!');
        }

        $keyService->configureContainerClient();

        $this->showModal = false;
        $this->reset(['editingKeyId', 'name', 'key']);
    }

    public function deleteKey(int $id, GeminiApiKeyService $keyService): void
    {
        $apiKey = GeminiApiKey::findOrFail($id);
        $wasDefault = $apiKey->is_default;

        $apiKey->delete();

        if ($wasDefault) {
            $next = GeminiApiKey::where('is_active', true)->orderBy('priority')->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        $keyService->configureContainerClient();

        Flux::toast('Chave removida do sistema.');
    }

    public function toggleActive(int $id, GeminiApiKeyService $keyService): void
    {
        $apiKey = GeminiApiKey::findOrFail($id);
        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        $keyService->configureContainerClient();

        Flux::toast($apiKey->is_active ? 'Chave ativada.' : 'Chave desativada.');
    }

    public function setDefault(int $id, GeminiApiKeyService $keyService): void
    {
        $apiKey = GeminiApiKey::findOrFail($id);
        $apiKey->update(['is_default' => true, 'is_active' => true]);

        $keyService->configureContainerClient();

        Flux::toast("Chave '{$apiKey->name}' definida como principal!");
    }

    public function testSingleKey(int $id, GeminiApiKeyService $keyService): void
    {
        $this->testingKeyId = $id;
        $apiKey = GeminiApiKey::findOrFail($id);

        $result = $keyService->testKey($apiKey);

        $this->testResult = array_merge($result, [
            'key_name' => $apiKey->name,
            'masked_key' => $apiKey->maskedKey(),
        ]);

        $this->testingKeyId = null;
        $this->showTestModal = true;

        if ($result['success']) {
            Flux::toast('Conexão realizada com sucesso!');
        } else {
            Flux::toast(variant: 'danger', heading: 'Falha no teste', text: $result['message']);
        }
    }

    public function testActiveConnection(GeminiApiKeyService $keyService): void
    {
        $this->isTestingActive = true;
        $activeKey = $keyService->getActiveKey();

        if (! $activeKey && ! $keyService->hasValidKey()) {
            $this->isTestingActive = false;
            Flux::toast(variant: 'danger', heading: 'Sem chave ativa', text: 'Nenhuma chave ativa encontrada para testar.');

            return;
        }

        $target = $activeKey ?: $keyService->getActiveKeyString();
        $result = $keyService->testKey($target);

        $this->testResult = array_merge($result, [
            'key_name' => $activeKey ? $activeKey->name : 'Fallback (.env)',
            'masked_key' => $activeKey ? $activeKey->maskedKey() : 'Chave do Arquivo .env',
        ]);

        $this->isTestingActive = false;
        $this->showTestModal = true;

        if ($result['success']) {
            Flux::toast('IA Online e respondendo perfeitamente!');
        } else {
            Flux::toast(variant: 'danger', heading: 'Falha na conexão', text: $result['message']);
        }
    }

    public function importFromEnv(GeminiApiKeyService $keyService): void
    {
        $imported = $keyService->importFromEnv();

        if ($imported) {
            $keyService->configureContainerClient();
            Flux::toast('Chave do .env importada com sucesso para o banco de dados!');
        } else {
            Flux::toast(variant: 'warning', heading: 'Não foi possível importar', text: 'Nenhuma chave encontrada no .env ou a chave já está cadastrada.');
        }
    }

    public function loadApiModels(GeminiApiKeyService $keyService): void
    {
        $this->isLoadingModels = true;

        try {
            $this->apiModels = $keyService->getAvailableModels();

            if (empty($this->apiModels)) {
                Flux::toast(variant: 'warning', heading: 'Nenhum modelo retornado', text: 'A API não retornou modelos com suporte a generateContent para esta chave.');
            } else {
                Flux::toast(count($this->apiModels) . ' modelos encontrados na sua conta do Gemini!');
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', heading: 'Erro ao consultar modelos', text: $e->getMessage());
        } finally {
            $this->isLoadingModels = false;
        }
    }

    public function testSpecificModel(string $modelName, GeminiApiKeyService $keyService): void
    {
        $this->testingModel = $modelName;

        $result = $keyService->testSpecificModel($modelName);
        $this->modelTestOutput = $result;
        $this->showModelTestModal = true;

        $this->testingModel = null;

        if ($result['success']) {
            Flux::toast("Modelo '{$modelName}' online ({$result['latency_ms']}ms)!");
        } else {
            Flux::toast(variant: 'danger', heading: 'Modelo indisponível', text: $result['message']);
        }
    }

    public function setAsDefaultModel(string $modelName, GeminiApiKeyService $keyService): void
    {
        $keyService->setDefaultModel($modelName);
        Flux::toast("Modelo '{$modelName}' definido como principal do sistema!");
    }

    public function toggleFallback(string $modelName, GeminiApiKeyService $keyService): void
    {
        $fallbacks = $keyService->getFallbackModels();
        $defaultModel = $keyService->getDefaultModel();

        if (in_array($modelName, $fallbacks)) {
            if ($modelName === $defaultModel) {
                Flux::toast(variant: 'warning', heading: 'Ação não permitida', text: 'O modelo padrão deve permanecer na lista de fallback.');
                return;
            }
            $fallbacks = array_values(array_filter($fallbacks, fn ($m) => $m !== $modelName));
            $keyService->setFallbackModels($fallbacks);
            Flux::toast("Modelo '{$modelName}' removido da fila de fallback.");
        } else {
            $fallbacks[] = $modelName;
            $keyService->setFallbackModels($fallbacks);
            Flux::toast("Modelo '{$modelName}' adicionado à fila de fallback!");
        }
    }

    public function with(): array
    {
        $keyService = app(GeminiApiKeyService::class);
        $keys = $keyService->getAllKeys();
        $activeKey = $keyService->getActiveKey();
        $envKey = env('GEMINI_API_KEY');
        $hasEnvKeyNotImported = ! empty($envKey) && ! $keys->contains(fn ($k) => $k->key === $envKey);

        $totalRequests = $keys->sum('total_requests');
        $successfulRequests = $keys->sum('successful_requests');
        $failedRequests = $keys->sum('failed_requests');
        $successRate = $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 1) : 100;

        $defaultModel = $keyService->getDefaultModel();
        $fallbackModels = $keyService->getFallbackModels();

        return [
            'keys' => $keys,
            'activeKey' => $activeKey,
            'hasEnvKeyNotImported' => $hasEnvKeyNotImported,
            'totalRequests' => $totalRequests,
            'successfulRequests' => $successfulRequests,
            'failedRequests' => $failedRequests,
            'successRate' => $successRate,
            'defaultModel' => $defaultModel,
            'fallbackModels' => $fallbackModels,
        ];
    }
};
?>

<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl">Gerenciamento de APIs Gemini</flux:heading>
            <flux:subheading>Gerencie as chaves e descubra quais modelos da inteligência artificial estão disponíveis para uso.</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            @if ($hasEnvKeyNotImported)
                <flux:button variant="ghost" icon="arrow-down-tray" wire:click="importFromEnv" size="sm">
                    Importar do .env
                </flux:button>
            @endif

            <flux:button variant="outline" icon="bolt" wire:click="testActiveConnection" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="testActiveConnection">Testar Conexão</span>
                <span wire:loading wire:target="testActiveConnection">Testando...</span>
            </flux:button>

            <flux:button variant="primary" icon="plus" wire:click="createKey">
                Nova Chave
            </flux:button>
        </div>
    </div>

    {{-- Banner de Chave do .env não migrada --}}
    @if ($hasEnvKeyNotImported)
        <flux:card class="bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon name="exclamation-triangle" class="text-amber-600 dark:text-amber-400 size-6 shrink-0" />
                    <div>
                        <div class="font-semibold text-amber-900 dark:text-amber-200">Chave no arquivo .env detectada</div>
                        <div class="text-xs text-amber-700 dark:text-amber-300">
                            Uma chave de API está definida no seu arquivo <code class="font-mono bg-amber-100 dark:bg-amber-900/50 px-1 py-0.5 rounded">.env</code>. Importe-a para gerenciar e monitorar seu status diretamente pelo sistema.
                        </div>
                    </div>
                </div>
                <flux:button variant="primary" size="sm" wire:click="importFromEnv" class="shrink-0">
                    Importar Agora
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Cards de Métricas e Diagnóstico --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Chaves Cadastradas --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase font-medium tracking-wider text-zinc-500 dark:text-zinc-400">Total de Chaves</span>
                <flux:icon name="key" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $keys->count() }}</span>
                <span class="text-xs text-zinc-500">
                    ({{ $keys->where('is_active', true)->count() }} ativas)
                </span>
            </div>
            <div class="mt-2 text-xs text-zinc-500">
                Rotação automática configurada
            </div>
        </flux:card>

        {{-- Card 2: Chave Ativa em Uso --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase font-medium tracking-wider text-zinc-500 dark:text-zinc-400">Chave Principal</span>
                <flux:icon name="sparkles" class="text-indigo-500 size-5" />
            </div>
            <div class="mt-2">
                @if ($activeKey)
                    <div class="text-sm font-bold text-zinc-900 dark:text-white truncate" title="{{ $activeKey->name }}">
                        {{ $activeKey->name }}
                    </div>
                    <div class="font-mono text-xs text-zinc-500 mt-0.5">
                        {{ $activeKey->maskedKey() }}
                    </div>
                @else
                    <div class="text-sm font-semibold text-amber-600 dark:text-amber-400">
                        {{ env('GEMINI_API_KEY') ? 'Fallback (.env)' : 'Nenhuma chave ativa' }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-0.5">Cadastre uma chave abaixo</div>
                @endif
            </div>
            <div class="mt-2">
                @if ($activeKey && $activeKey->status === 'active')
                    <flux:badge size="sm" color="green">Operacional</flux:badge>
                @elseif ($activeKey && $activeKey->status === 'rate_limited')
                    <flux:badge size="sm" color="amber">Limite Atingido</flux:badge>
                @elseif (! $activeKey && env('GEMINI_API_KEY'))
                    <flux:badge size="sm" color="zinc">Modo Legado (.env)</flux:badge>
                @else
                    <flux:badge size="sm" color="red">Inoperante</flux:badge>
                @endif
            </div>
        </flux:card>

        {{-- Card 3: Requisições Totais --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase font-medium tracking-wider text-zinc-500 dark:text-zinc-400">Requisições Realizadas</span>
                <flux:icon name="chart-bar" class="text-emerald-500 size-5" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalRequests) }}</span>
                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ $successRate }}% sucesso
                </span>
            </div>
            <div class="mt-2 text-xs text-zinc-500">
                {{ number_format($failedRequests) }} com erro ou cota excedida
            </div>
        </flux:card>

        {{-- Card 4: Modelo Padrão do Sistema --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase font-medium tracking-wider text-zinc-500 dark:text-zinc-400">Modelo Padrão Ativo</span>
                <flux:icon name="cpu-chip" class="text-indigo-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-sm font-bold font-mono text-zinc-900 dark:text-white">
                    {{ $defaultModel }}
                </span>
            </div>
            <div class="mt-2 flex items-center gap-1.5 text-xs text-zinc-500 truncate" title="{{ implode(', ', $fallbackModels) }}">
                <span class="size-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="truncate">Fallback: {{ implode(' → ', $fallbackModels) }}</span>
            </div>
        </flux:card>
    </div>

    {{-- Tabs de Navegação: Chaves vs Explorador de Modelos --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-700 gap-4">
        <button
            type="button"
            wire:click="$set('tab', 'keys')"
            class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $tab === 'keys' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="key" class="size-4" />
                <span>Chaves de API ({{ $keys->count() }})</span>
            </div>
        </button>

        <button
            type="button"
            wire:click="$set('tab', 'models')"
            class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $tab === 'models' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="cpu-chip" class="size-4" />
                <span>Explorador de Modelos da API</span>
                @if (count($apiModels) > 0)
                    <flux:badge size="sm" color="zinc">{{ count($apiModels) }}</flux:badge>
                @endif
            </div>
        </button>
    </div>

    {{-- TAB 1: CHAVES DE API --}}
    @if ($tab === 'keys')
        <flux:card class="overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Chaves Cadastradas</flux:heading>
                    <flux:subheading>Gerencie as credenciais e prioridades de rotação da API.</flux:subheading>
                </div>
            </div>

            <div class="overflow-x-auto w-full scrollbar-none">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Identificação</flux:table.column>
                        <flux:table.column>Chave Mascarada</flux:table.column>
                        <flux:table.column>Papel</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Uso (Sucesso / Falha)</flux:table.column>
                        <flux:table.column>Último Teste</flux:table.column>
                        <flux:table.column class="text-right">Ações</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($keys as $k)
                            <flux:table.row :key="$k->id">
                                {{-- Nome --}}
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white flex items-center gap-2">
                                        {{ $k->name }}
                                        @if ($k->is_default)
                                            <flux:badge size="sm" color="indigo" icon="star">Principal</flux:badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-400">Prioridade: {{ $k->priority }}</div>
                                </flux:table.cell>

                                {{-- Chave --}}
                                <flux:table.cell>
                                    <code class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded text-zinc-700 dark:text-zinc-300">
                                        {{ $k->maskedKey() }}
                                    </code>
                                </flux:table.cell>

                                {{-- Papel / Padrão --}}
                                <flux:table.cell>
                                    @if ($k->is_default)
                                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Principal (Rotação 1)</span>
                                    @else
                                        <flux:button variant="ghost" size="sm" wire:click="setDefault({{ $k->id }})" title="Tornar chave principal">
                                            Definir como Principal
                                        </flux:button>
                                    @endif
                                </flux:table.cell>

                                {{-- Status --}}
                                <flux:table.cell>
                                    @if (! $k->is_active)
                                        <flux:badge size="sm" color="zinc">Inativa</flux:badge>
                                    @elseif ($k->status === 'rate_limited')
                                        <div class="space-y-1">
                                            <flux:badge size="sm" color="amber">Limite Atingido (429)</flux:badge>
                                            @if ($k->rate_limited_until)
                                                <div class="text-[10px] text-zinc-400">
                                                    Até {{ $k->rate_limited_until->format('H:i:s') }}
                                                </div>
                                            @endif
                                        </div>
                                    @elseif ($k->status === 'invalid')
                                        <flux:badge size="sm" color="red">Chave Inválida</flux:badge>
                                    @elseif ($k->status === 'error')
                                        <flux:badge size="sm" color="red">Erro de Conexão</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green">Ativa & Pronta</flux:badge>
                                    @endif
                                </flux:table.cell>

                                {{-- Uso --}}
                                <flux:table.cell>
                                    <div class="text-xs">
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $k->successful_requests }}</span>
                                        <span class="text-zinc-400"> / </span>
                                        <span class="text-red-600 dark:text-red-400">{{ $k->failed_requests }}</span>
                                        <span class="text-zinc-400"> ({{ $k->total_requests }} total)</span>
                                    </div>
                                    @if ($k->last_used_at)
                                        <div class="text-[10px] text-zinc-400">
                                            Último uso: {{ $k->last_used_at->diffForHumans() }}
                                        </div>
                                    @endif
                                </flux:table.cell>

                                {{-- Último Teste --}}
                                <flux:table.cell>
                                    @if ($k->last_tested_at)
                                        <span class="text-xs text-zinc-600 dark:text-zinc-400" title="{{ $k->last_tested_at->format('d/m/Y H:i:s') }}">
                                            {{ $k->last_tested_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">Nunca testada</span>
                                    @endif
                                </flux:table.cell>

                                {{-- Ações --}}
                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            wire:click="testSingleKey({{ $k->id }})"
                                            size="sm"
                                            variant="ghost"
                                            icon="sparkles"
                                            title="Testar conexão desta chave"
                                            wire:loading.attr="disabled"
                                        />

                                        <flux:button
                                            wire:click="toggleActive({{ $k->id }})"
                                            size="sm"
                                            variant="ghost"
                                            icon="{{ $k->is_active ? 'eye-slash' : 'eye' }}"
                                            title="{{ $k->is_active ? 'Desativar chave' : 'Ativar chave' }}"
                                        />

                                        <flux:button
                                            wire:click="editKey({{ $k->id }})"
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            title="Editar informações da chave"
                                        />

                                        <flux:button
                                            wire:click="deleteKey({{ $k->id }})"
                                            wire:confirm="Tem certeza que deseja remover esta chave do Gemini? O sistema usará as demais chaves ativas."
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            color="danger"
                                            title="Excluir chave"
                                        />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="text-center py-10">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-full text-zinc-500">
                                            <flux:icon name="key" class="size-6" />
                                        </div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                            Nenhuma chave de API do Gemini cadastrada no banco de dados.
                                        </div>
                                        <div class="text-xs text-zinc-500 max-w-md">
                                            Cadastre sua chave do Google AI Studio para que o avali.ai possa gerar e corrigir provas automaticamente sem depender do arquivo .env.
                                        </div>
                                        <flux:button variant="primary" size="sm" icon="plus" wire:click="createKey">
                                            Cadastrar Primeira Chave
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif

    {{-- TAB 2: EXPLORADOR DE MODELOS DA API --}}
    @if ($tab === 'models')
        <div class="space-y-6">
            <flux:card>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <flux:heading size="lg">Catálogo em Tempo Real do Google Gemini</flux:heading>
                        <flux:subheading>
                            Descubra quais versões de modelos (ex: Flash, Pro) estão liberadas para a sua conta do Google AI Studio e configure a ordem de fallback do sistema.
                        </flux:subheading>
                    </div>

                    <flux:button
                        variant="primary"
                        icon="arrow-path"
                        wire:click="loadApiModels"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="loadApiModels">Consultar Modelos da Conta</span>
                        <span wire:loading wire:target="loadApiModels">Consultando API do Google...</span>
                    </flux:button>
                </div>

                {{-- Explicação de versões e descontinuações --}}
                <div class="mt-4 p-4 rounded-lg bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200 space-y-1">
                    <div class="font-semibold flex items-center gap-1.5">
                        <flux:icon name="information-circle" class="size-4 text-indigo-600 dark:text-indigo-400" />
                        <span>Por que versões como gemini-2.5 ou gemini-3.5 podem falhar?</span>
                    </div>
                    <p class="text-indigo-700 dark:text-indigo-300">
                        O Google AI frequentemente descontinua versões antigas, lança versões Preview ou exige cotas específicas por região/tier. Ao consultar o catálogo abaixo, você vê <strong>exatamente os identificadores oficiais</strong> liberados para a sua chave e pode definir qual modelo o sistema deve usar como principal.
                    </p>
                </div>
            </flux:card>

            @if (count($apiModels) > 0)
                <flux:card class="overflow-hidden">
                    <div class="overflow-x-auto w-full scrollbar-none">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Identificador do Modelo</flux:table.column>
                                <flux:table.column>Nome de Exibição / Descrição</flux:table.column>
                                <flux:table.column>Limites de Tokens</flux:table.column>
                                <flux:table.column>Configuração no Sistema</flux:table.column>
                                <flux:table.column class="text-right">Ações</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($apiModels as $m)
                                    @php
                                        $isDefault = ($m['name'] === $defaultModel);
                                        $inFallback = in_array($m['name'], $fallbackModels);
                                    @endphp
                                    <flux:table.row :key="$m['name']">
                                        {{-- Identificador --}}
                                        <flux:table.cell>
                                            <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                                {{ $m['name'] }}
                                            </div>
                                            <div class="text-[10px] text-zinc-400">
                                                {{ $m['full_name'] }}
                                            </div>
                                        </flux:table.cell>

                                        {{-- Nome / Descrição --}}
                                        <flux:table.cell>
                                            <div class="font-medium text-xs text-zinc-900 dark:text-white">
                                                {{ $m['display_name'] }}
                                            </div>
                                            @if (! empty($m['description']))
                                                <div class="text-[11px] text-zinc-500 max-w-md truncate" title="{{ $m['description'] }}">
                                                    {{ $m['description'] }}
                                                </div>
                                            @endif
                                        </flux:table.cell>

                                        {{-- Limites de Tokens --}}
                                        <flux:table.cell>
                                            <div class="text-xs space-y-0.5">
                                                @if ($m['input_token_limit'])
                                                    <div class="text-zinc-600 dark:text-zinc-300">
                                                        <span class="text-zinc-400">Entrada:</span> {{ number_format($m['input_token_limit']) }}
                                                    </div>
                                                @endif
                                                @if ($m['output_token_limit'])
                                                    <div class="text-zinc-600 dark:text-zinc-300">
                                                        <span class="text-zinc-400">Saída:</span> {{ number_format($m['output_token_limit']) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </flux:table.cell>

                                        {{-- Configuração no Sistema --}}
                                        <flux:table.cell>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                @if ($isDefault)
                                                    <flux:badge size="sm" color="indigo" icon="star">Principal</flux:badge>
                                                @endif

                                                @if ($inFallback && ! $isDefault)
                                                    <flux:badge size="sm" color="emerald">Fallback</flux:badge>
                                                @endif

                                                @if (! $inFallback && ! $isDefault)
                                                    <span class="text-[11px] text-zinc-400">Não utilizado</span>
                                                @endif
                                            </div>
                                        </flux:table.cell>

                                        {{-- Ações --}}
                                        <flux:table.cell class="text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                {{-- Testar Modelo --}}
                                                <flux:button
                                                    wire:click="testSpecificModel('{{ $m['name'] }}')"
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="sparkles"
                                                    title="Testar resposta deste modelo em tempo real"
                                                    wire:loading.attr="disabled"
                                                >
                                                    Testar
                                                </flux:button>

                                                {{-- Definir como Padrão --}}
                                                @if (! $isDefault)
                                                    <flux:button
                                                        wire:click="setAsDefaultModel('{{ $m['name'] }}')"
                                                        size="sm"
                                                        variant="ghost"
                                                        title="Definir este modelo como padrão do sistema"
                                                    >
                                                        Tornar Padrão
                                                    </flux:button>
                                                @endif

                                                {{-- Adicionar / Remover do Fallback --}}
                                                <flux:button
                                                    wire:click="toggleFallback('{{ $m['name'] }}')"
                                                    size="sm"
                                                    variant="ghost"
                                                    title="{{ $inFallback ? 'Remover da fila de fallback' : 'Adicionar à fila de fallback' }}"
                                                    icon="{{ $inFallback ? 'minus-circle' : 'plus-circle' }}"
                                                />
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </flux:card>
            @elseif (! $isLoadingModels)
                <flux:card class="text-center py-12">
                    <div class="flex flex-col items-center justify-center space-y-3">
                        <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-full text-zinc-500">
                            <flux:icon name="cpu-chip" class="size-8" />
                        </div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">
                            Nenhum modelo carregado ainda
                        </div>
                        <div class="text-xs text-zinc-500 max-w-md">
                            Clique no botão abaixo para que o avali.ai consulte a API do Google usando a sua chave ativa e liste todos os modelos disponíveis para a sua conta.
                        </div>
                        <flux:button variant="primary" size="sm" icon="arrow-path" wire:click="loadApiModels">
                            Carregar Modelos da API
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    @endif

    {{-- Modal para Cadastrar / Editar Chave --}}
    <flux:modal wire:model="showModal" class="md:w-[550px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingKeyId ? 'Editar Chave de API' : 'Cadastrar Chave do Gemini' }}</flux:heading>
                <flux:subheading>
                    Obtenha sua chave gratuitamente no <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-indigo-600 dark:text-indigo-400 underline">Google AI Studio</a>.
                </flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:input
                    label="Nome Identificador"
                    wire:model="name"
                    placeholder="Ex: Chave Principal (Google AI Studio) ou Conta Secundária"
                    required
                />

                <flux:input
                    label="Chave de API (Secret Key)"
                    type="password"
                    wire:model="key"
                    placeholder="{{ $editingKeyId ? 'Deixe em branco para manter a chave atual' : 'AIzaSy...' }}"
                    viewable
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <flux:input
                        label="Prioridade de Rotação"
                        type="number"
                        wire:model="priority"
                        placeholder="0"
                        min="0"
                    />

                    <div class="flex flex-col justify-end space-y-3 pb-1">
                        <flux:switch wire:model="is_default" label="Definir como Principal" />
                    </div>
                </div>

                <div class="pt-1">
                    <flux:switch wire:model="is_active" label="Chave Ativa para Requisições" />
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Salvar Chave</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal com Resultado do Teste de Chave --}}
    <flux:modal wire:model="showTestModal" class="md:w-[500px]">
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                @if ($testResult['success'] ?? false)
                    <div class="p-2.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="check-circle" class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="lg">Conexão Estabelecida</flux:heading>
                        <flux:subheading>{{ $testResult['key_name'] ?? 'Chave Gemini' }}</flux:subheading>
                    </div>
                @else
                    <div class="p-2.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400">
                        <flux:icon name="x-circle" class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="lg">Falha na Comunicação</flux:heading>
                        <flux:subheading>{{ $testResult['key_name'] ?? 'Chave Gemini' }}</flux:subheading>
                    </div>
                @endif
            </div>

            <flux:separator />

            <div class="space-y-3 text-sm">
                <div>
                    <span class="font-medium text-zinc-900 dark:text-white">Resultado:</span>
                    <p class="text-zinc-600 dark:text-zinc-300 mt-0.5">{{ $testResult['message'] ?? '' }}</p>
                </div>

                @if (! empty($testResult['masked_key']))
                    <div>
                        <span class="font-medium text-zinc-900 dark:text-white">Chave:</span>
                        <p class="font-mono text-xs text-zinc-500 mt-0.5">{{ $testResult['masked_key'] }}</p>
                    </div>
                @endif

                @if (! empty($testResult['models_count']))
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-lg text-xs text-zinc-600 dark:text-zinc-300">
                        A API do Google Gemini respondeu com sucesso. Estão liberados {{ $testResult['models_count'] }} modelos nesta credencial.
                    </div>
                @endif

                @if (! empty($testResult['error']))
                    <div class="p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-lg text-xs text-red-700 dark:text-red-300 font-mono break-all">
                        {{ $testResult['error'] }}
                    </div>
                @endif
            </div>

            <div class="flex justify-end pt-3">
                <flux:modal.close>
                    <flux:button variant="primary">Fechar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Modal com Resultado do Teste de Modelo Específico --}}
    <flux:modal wire:model="showModelTestModal" class="md:w-[500px]">
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                @if ($modelTestOutput['success'] ?? false)
                    <div class="p-2.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="check-circle" class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="lg">Modelo Operacional</flux:heading>
                        <flux:subheading>{{ $modelTestOutput['model'] ?? 'Modelo' }}</flux:subheading>
                    </div>
                @else
                    <div class="p-2.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400">
                        <flux:icon name="x-circle" class="size-6" />
                    </div>
                    <div>
                        <flux:heading size="lg">Falha no Modelo</flux:heading>
                        <flux:subheading>{{ $modelTestOutput['model'] ?? 'Modelo' }}</flux:subheading>
                    </div>
                @endif
            </div>

            <flux:separator />

            <div class="space-y-3 text-sm">
                <div>
                    <span class="font-medium text-zinc-900 dark:text-white">Status da Resposta:</span>
                    <p class="text-zinc-600 dark:text-zinc-300 mt-0.5">{{ $modelTestOutput['message'] ?? '' }}</p>
                </div>

                @if (isset($modelTestOutput['latency_ms']))
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <flux:icon name="clock" class="size-4" />
                        <span>Latência de resposta: <strong class="text-zinc-700 dark:text-zinc-200">{{ $modelTestOutput['latency_ms'] }} ms</strong></span>
                    </div>
                @endif

                @if (! empty($modelTestOutput['response_text']))
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-lg text-xs font-mono text-zinc-700 dark:text-zinc-300">
                        Retorno do Modelo: "{{ $modelTestOutput['response_text'] }}"
                    </div>
                @endif

                @if (! empty($modelTestOutput['error']))
                    <div class="p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-lg text-xs text-red-700 dark:text-red-300 font-mono break-all">
                        {{ $modelTestOutput['error'] }}
                    </div>
                @endif
            </div>

            <div class="flex justify-between items-center pt-3">
                @if (($modelTestOutput['success'] ?? false) && isset($modelTestOutput['model']) && $modelTestOutput['model'] !== $defaultModel)
                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="setAsDefaultModel('{{ $modelTestOutput['model'] }}')"
                    >
                        Tornar este Modelo Principal
                    </flux:button>
                @else
                    <div></div>
                @endif

                <flux:modal.close>
                    <flux:button variant="primary">Fechar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>