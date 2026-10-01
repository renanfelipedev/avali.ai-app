<?php

namespace App\Console\Commands;

use App\Services\GeminiApiKeyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('ia:check-status')]
#[Description('Verifica a saúde da API do Gemini e pré-aquece o cache de status da tela inicial')]
class CheckAiStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GeminiApiKeyService $keyService)
    {
        $this->info('Verificando conectividade com o Google Gemini...');

        if (! $keyService->hasValidKey()) {
            $status = [
                'online' => false,
                'message' => 'Sem chave',
                'model' => 'N/A',
                'error' => 'Nenhuma chave de API configurada no sistema.',
                'checked_at' => now()->format('H:i'),
            ];

            Cache::put('gemini_status', $status, 1800);
            $this->warn('Nenhuma chave de API configurada.');

            return self::FAILURE;
        }

        $defaultModel = $keyService->getDefaultModel();
        $fallbackModels = $keyService->getFallbackModels();
        if (! in_array($defaultModel, $fallbackModels)) {
            array_unshift($fallbackModels, $defaultModel);
        }

        try {
            $rawKey = $keyService->getActiveKeyString();
            $client = $keyService->createClient($rawKey);
            $modelsResponse = $client->models()->list();

            $availableNames = [];
            foreach ($modelsResponse->models ?? [] as $m) {
                $availableNames[] = str_replace('models/', '', $m->name);
            }

            $activeModel = $defaultModel;
            if (! in_array($defaultModel, $availableNames)) {
                foreach ($fallbackModels as $fb) {
                    if (in_array($fb, $availableNames)) {
                        $activeModel = $fb;
                        break;
                    }
                }
            }

            $status = [
                'online' => true,
                'message' => 'Online',
                'model' => $activeModel,
                'checked_at' => now()->format('H:i'),
            ];

            Cache::put('gemini_status', $status, 1800);
            $this->info("✅ Gemini Online! Modelo ativo: {$activeModel}. Status em cache por 30 minutos.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $status = [
                'online' => false,
                'message' => 'Indisponível',
                'model' => $defaultModel,
                'error' => $e->getMessage(),
                'checked_at' => now()->format('H:i'),
            ];

            Cache::put('gemini_status', $status, 1800);
            $this->error('❌ Falha ao conectar com o Gemini: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
