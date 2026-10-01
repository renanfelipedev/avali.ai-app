<?php

use App\Models\AiLog;
use App\Services\GeminiApiKeyService;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component
{
    public $tokensToday = 0;
    public $status = [];
    public $isLoading = false;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->tokensToday = AiLog::whereDate('created_at', today())->sum('tokens_used');

        // Cache persistente de 30 minutos para evitar requisições síncronas lentas durante a navegação
        $this->status = Cache::remember('gemini_status', 1800, function () {
            return $this->performCheck();
        });
    }

    public function testApi()
    {
        $this->isLoading = true;

        $this->status = $this->performCheck(true);

        // Atualiza o cache com o novo teste manual por 30 minutos
        Cache::put('gemini_status', $this->status, 1800);

        $this->tokensToday = AiLog::whereDate('created_at', today())->sum('tokens_used');
        $this->isLoading = false;

        if ($this->status['online']) {
            Flux::toast('Conexão com a IA estabelecida com sucesso!');
        } else {
            Flux::toast(
                variant: 'danger',
                heading: 'Falha na conexão',
                text: $this->status['message'] . ': ' . Str::limit($this->status['error'] ?? '', 60)
            );
        }
    }

    /**
     * Placeholder renderizado instantaneamente pelo Livewire enquanto o card carrega em background.
     */
    public function placeholder()
    {
        return <<<'HTML'
        <flux:card class="flex flex-col items-center justify-center p-6 text-center animate-pulse">
            <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-700 mb-2"></div>
            <div class="h-6 w-20 bg-zinc-200 dark:bg-zinc-700 rounded mb-1"></div>
            <div class="h-3 w-28 bg-zinc-200 dark:bg-zinc-700 rounded mb-3"></div>
            <div class="h-3 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
        </flux:card>
        HTML;
    }

    /**
     * Executa a checagem de saúde da API.
     * Utiliza listagem rápida de modelos (~400ms) por padrão ou teste generativo sob demanda.
     */
    private function performCheck(bool $forceGenerative = false): array
    {
        $keyService = app(GeminiApiKeyService::class);
        if (! $keyService->hasValidKey()) {
            return [
                'online' => false,
                'message' => 'Sem chave',
                'model' => 'N/A',
                'error' => 'Nenhuma chave de API configurada no sistema. Acesse Chaves Gemini.',
            ];
        }

        $defaultModel = $keyService->getDefaultModel();
        $fallbackModels = $keyService->getFallbackModels();
        if (! in_array($defaultModel, $fallbackModels)) {
            array_unshift($fallbackModels, $defaultModel);
        }

        // Teste de conectividade rápido via listagem de modelos (400ms vs 8000ms de geração)
        if (! $forceGenerative) {
            try {
                $rawKey = $keyService->getActiveKeyString();
                $client = $keyService->createClient($rawKey);
                $modelsResponse = $client->models()->list();

                $availableNames = [];
                foreach ($modelsResponse->models ?? [] as $m) {
                    $availableNames[] = str_replace('models/', '', $m->name);
                }

                // Verifica qual dos modelos configurados está presente na conta
                $activeModel = $defaultModel;
                if (! in_array($defaultModel, $availableNames)) {
                    foreach ($fallbackModels as $fb) {
                        if (in_array($fb, $availableNames)) {
                            $activeModel = $fb;
                            break;
                        }
                    }
                }

                return [
                    'online' => true,
                    'message' => 'Online',
                    'model' => $activeModel,
                    'checked_at' => now()->format('H:i'),
                ];
            } catch (\Throwable $e) {
                // Se a listagem rápida falhar, tenta o ping generativo abaixo
            }
        }

        // Teste generativo sob demanda ou fallback
        $keyService->configureContainerClient();
        $lastError = null;

        foreach ($fallbackModels as $model) {
            try {
                $result = $keyService->testSpecificModel($model);
                if ($result['success']) {
                    return [
                        'online' => true,
                        'message' => 'Online',
                        'model' => $model,
                        'latency_ms' => $result['latency_ms'] ?? null,
                        'checked_at' => now()->format('H:i'),
                    ];
                }
                $lastError = $result['error'] ?? $result['message'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        return [
            'online' => false,
            'message' => 'Indisponível',
            'model' => $defaultModel,
            'error' => $lastError ?? 'Todos os modelos testados falharam.',
            'checked_at' => now()->format('H:i'),
        ];
    }
};
?>

<flux:card class="flex flex-col items-center justify-center p-6 text-center relative">
    <div class="relative mb-2">
        <flux:icon.sparkles class="size-8 text-amber-500" />
        <div class="absolute -top-1 -right-1">
            <span class="flex size-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ ($status['online'] ?? false) ? 'bg-green-400' : 'bg-red-400' }} opacity-75"></span>
                <span class="relative inline-flex size-3 rounded-full {{ ($status['online'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></span>
            </span>
        </div>
    </div>

    <flux:heading size="lg">{{ number_format($tokensToday, 0, ',', '.') }}</flux:heading>
    <flux:subheading>Tokens Usados Hoje</flux:subheading>
    
    <div class="mt-2 text-xs font-medium mb-1">
        <span class="{{ ($status['online'] ?? false) ? 'text-green-600' : 'text-red-600' }}">
            API: {{ $status['message'] ?? 'Verificando...' }}
        </span>
    </div>
    
    <div class="text-[10px] text-zinc-500 uppercase tracking-widest mb-4">
        Modelo: {{ $status['model'] ?? 'Carregando...' }}
    </div>

    <flux:button wire:click="testApi" size="xs" variant="ghost" icon="arrow-path" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="testApi">Testar Disponibilidade</span>
        <span wire:loading wire:target="testApi">Testando...</span>
    </flux:button>
</flux:card>