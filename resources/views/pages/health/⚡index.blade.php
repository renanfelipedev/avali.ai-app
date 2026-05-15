<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

new #[Layout('layouts.main')] class extends Component {
    public $services = [];
    public $lastChecked;

    public function mount()
    {
        $this->checkHealth();
    }

    public function checkHealth()
    {
        $this->services = [
            [
                'name' => 'Banco de Dados',
                'description' => 'Persistência de dados principal',
                'status' => $this->checkDatabase(),
                'icon' => 'circle-stack',
            ],
            [
                'name' => 'Redis (Cache/Fila)',
                'description' => 'Performance e processamento assíncrono',
                'status' => $this->checkRedis(),
                'icon' => 'bolt',
            ],
            [
                'name' => 'Armazenamento',
                'description' => 'Sistema de arquivos e uploads',
                'status' => $this->checkStorage(),
                'icon' => 'folder',
            ],
            [
                'name' => 'Google Gemini AI',
                'description' => 'Inteligência Artificial Generativa',
                'status' => $this->checkGemini(),
                'icon' => 'sparkles',
            ],
        ];

        $this->lastChecked = now()->format('H:i:s');
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkRedis()
    {
        try {
            Redis::connection()->ping();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkStorage()
    {
        try {
            Storage::disk('local')->exists('.');
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkGemini()
    {
        try {
            $apiKey = config('gemini.api_key');
            if (!$apiKey) {
                return 'unconfigured';
            }
            $response = Http::timeout(3)->get('https://generativelanguage.googleapis.com');
            return $response->status() < 500 ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }
};
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">Saúde do Sistema</flux:heading>
            <flux:subheading>Monitoramento de serviços e infraestrutura.</flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-xs text-zinc-500 font-mono">Check: {{ $lastChecked }}</span>
            <flux:button variant="ghost" icon="arrow-path" wire:click="checkHealth" size="sm" />
        </div>
    </div>

    <flux:separator />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach ($services as $service)
            @php
                $isHealthy = $service['status'] === 'healthy';
                $isUnconfigured = $service['status'] === 'unconfigured';
                $statusColor = $isHealthy ? 'green' : ($isUnconfigured ? 'zinc' : 'red');
                $statusLabel = $isHealthy ? 'Online' : ($isUnconfigured ? 'Pendente' : 'Offline');
            @endphp

            <flux:card class="space-y-4">
                <div class="flex justify-between items-start">
                    <flux:icon :name="$service['icon']" variant="outline" class="text-zinc-400" />
                    <flux:badge :color="$statusColor" size="sm" inset="top">{{ $statusLabel }}</flux:badge>
                </div>

                <div>
                    <flux:heading>{{ $service['name'] }}</flux:heading>
                    <flux:subheading>{{ $service['description'] }}</flux:subheading>
                </div>
            </flux:card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <flux:card class="lg:col-span-2 space-y-6">
            <flux:heading size="lg">Informações do Ambiente</flux:heading>
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="space-y-1">
                    <flux:heading level="3" size="sm" class="text-zinc-500 uppercase tracking-widest">PHP</flux:heading>
                    <div class="text-sm font-medium">{{ PHP_VERSION }}</div>
                </div>

                <div class="space-y-1">
                    <flux:heading level="3" size="sm" class="text-zinc-500 uppercase tracking-widest">Laravel</flux:heading>
                    <div class="text-sm font-medium">{{ app()->version() }}</div>
                </div>

                <div class="space-y-1">
                    <flux:heading level="3" size="sm" class="text-zinc-500 uppercase tracking-widest">Env</flux:heading>
                    <div><flux:badge color="indigo" size="sm">{{ strtoupper(app()->environment()) }}</flux:badge></div>
                </div>

                <div class="space-y-1">
                    <flux:heading level="3" size="sm" class="text-zinc-500 uppercase tracking-widest">Debug</flux:heading>
                    <div><flux:badge :color="config('app.debug') ? 'orange' : 'green'" size="sm">{{ config('app.debug') ? 'ON' : 'OFF' }}</flux:badge></div>
                </div>
            </div>
        </flux:card>

        <flux:card class="bg-zinc-50 dark:bg-zinc-900/50 flex flex-col justify-center items-center text-center p-6">
            <flux:icon name="shield-check" class="text-indigo-600 mb-4" />
            <flux:heading>Segurança</flux:heading>
            <flux:subheading>Painel restrito a administradores em produção.</flux:subheading>
        </flux:card>
    </div>
</div>
