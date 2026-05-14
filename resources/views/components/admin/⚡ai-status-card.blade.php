<?php

use App\Models\AiLog;
use Illuminate\Support\Facades\Cache;
use Gemini\Laravel\Facades\Gemini;
use Livewire\Component;

new class extends Component
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
        $this->status = Cache::remember('gemini_status', 120, function () {
            return $this->performCheck();
        });
    }

    public function testApi()
    {
        $this->isLoading = true;
        
        // Simular um pequeno atraso para feedback visual se necessário, 
        // mas aqui vamos direto ao ponto
        $this->status = $this->performCheck();
        
        // Atualiza o cache com o novo status manual
        Cache::put('gemini_status', $this->status, 120);
        
        $this->tokensToday = AiLog::whereDate('created_at', today())->sum('tokens_used');
        
        $this->isLoading = false;

        if ($this->status['online']) {
            Flux::toast('Conexão com a IA estabelecida com sucesso!');
        } else {
            Flux::toast(
                variant: 'danger',
                heading: 'Falha na conexão',
                text: $this->status['message'] . ': ' . Str::limit($this->status['error'] ?? '', 50)
            );
        }
    }

    private function performCheck()
    {
        $models = config('gemini.fallback_models', ['gemini-1.5-flash']);
        
        foreach ($models as $model) {
            try {
                // Teste minimalista de geração com cada modelo da lista de fallback
                Gemini::generativeModel($model)->generateContent('ping');
                return [
                    'online' => true, 
                    'message' => 'Online', 
                    'model' => $model
                ];
            } catch (\Exception $e) {
                $err = strtolower($e->getMessage());
                // Se o erro não for de cota, interrompe e mostra o erro do modelo atual
                if (!str_contains($err, 'quota') && !str_contains($err, 'limit')) {
                    return [
                        'online' => false, 
                        'message' => 'Erro: ' . $model, 
                        'model' => $model,
                        'error' => $e->getMessage()
                    ];
                }
                // Se for cota, tenta o próximo modelo...
                continue;
            }
        }

        return [
            'online' => false, 
            'message' => 'Cota Esgotada (Todos)', 
            'model' => $models[0] ?? 'N/A'
        ];
    }
};
?>

<flux:card class="flex flex-col items-center justify-center p-6 text-center relative">
    <div class="relative mb-2">
        <flux:icon.sparkles class="size-8 text-amber-500" />
        <div class="absolute -top-1 -right-1">
            <span class="flex size-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $status['online'] ? 'bg-green-400' : 'bg-red-400' }} opacity-75"></span>
                <span class="relative inline-flex size-3 rounded-full {{ $status['online'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
            </span>
        </div>
    </div>

    <flux:heading size="lg">{{ number_format($tokensToday, 0, ',', '.') }}</flux:heading>
    <flux:subheading>Tokens Usados Hoje</flux:subheading>
    
    <div class="mt-2 text-xs font-medium mb-1">
        <span class="{{ $status['online'] ? 'text-green-600' : 'text-red-600' }}">
            API: {{ $status['message'] }}
        </span>
    </div>
    
    <div class="text-[10px] text-zinc-500 uppercase tracking-widest mb-4">
        Modelo: {{ $status['model'] ?? 'Indisponível' }}
    </div>

    <flux:button wire:click="testApi" size="xs" variant="ghost" icon="arrow-path" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="testApi">Testar Disponibilidade</span>
        <span wire:loading wire:target="testApi">Testando...</span>
    </flux:button>
</flux:card>