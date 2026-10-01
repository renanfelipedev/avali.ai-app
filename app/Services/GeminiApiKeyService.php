<?php

namespace App\Services;

use App\Models\GeminiApiKey;
use Gemini\Client;
use Gemini\Contracts\ClientContract;
use Gemini\Laravel\Facades\Gemini;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GeminiApiKeyService
{
    /**
     * Retorna a melhor chave ativa e disponível no momento.
     */
    public function getActiveKey(): ?GeminiApiKey
    {
        try {
            if (! Schema::hasTable('gemini_api_keys')) {
                return null;
            }

            return GeminiApiKey::query()
                ->available()
                ->orderByDesc('is_default')
                ->orderBy('priority')
                ->orderBy('last_used_at')
                ->first();
        } catch (Throwable $e) {
            Log::warning('Erro ao buscar chave Gemini no banco: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Retorna a string da chave de API ativa (do banco ou fallback do .env).
     */
    public function getActiveKeyString(): ?string
    {
        $activeKey = $this->getActiveKey();

        if ($activeKey && ! empty($activeKey->key)) {
            return $activeKey->key;
        }

        $envKey = config('gemini.api_key') ?: env('GEMINI_API_KEY');

        return ! empty($envKey) ? (string) $envKey : null;
    }

    /**
     * Verifica se existe qualquer chave utilizável (seja no banco ou .env).
     */
    public function hasValidKey(): bool
    {
        return ! empty($this->getActiveKeyString());
    }

    /**
     * Cria uma instância de cliente Gemini configurada com a chave informada.
     */
    public function createClient(string $apiKey): Client
    {
        $timeout = (int) config('gemini.request_timeout', 30);
        $baseURL = config('gemini.base_url');

        $factory = \Gemini::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new GuzzleClient(['timeout' => $timeout]));

        if (! empty($baseURL)) {
            $factory->withBaseUrl($baseURL);
        }

        return $factory->make();
    }

    /**
     * Configura o cliente do Gemini no container de serviços do Laravel.
     * Desta forma, chamadas a Gemini::generativeModel(...) utilizarão a chave ativa.
     */
    public function configureContainerClient(?GeminiApiKey $key = null): ?Client
    {
        $apiKeyString = $key ? $key->key : $this->getActiveKeyString();

        if (empty($apiKeyString)) {
            return null;
        }

        // Atualiza a configuração em tempo de execução
        config(['gemini.api_key' => $apiKeyString]);

        $client = $this->createClient($apiKeyString);

        app()->singleton(ClientContract::class, fn () => $client);
        app()->alias(ClientContract::class, 'gemini');
        app()->alias(ClientContract::class, Client::class);

        return $client;
    }

    /**
     * Executa teste em tempo real na API do Google para validar uma chave.
     *
     * @return array{success: bool, message: string, error?: string, models_count?: int}
     */
    public function testKey(string|GeminiApiKey $keyOrModel): array
    {
        $isModel = $keyOrModel instanceof GeminiApiKey;
        $rawKey = $isModel ? $keyOrModel->key : $keyOrModel;

        if (empty($rawKey)) {
            return [
                'success' => false,
                'message' => 'A chave de API fornecida está vazia.',
            ];
        }

        try {
            $client = $this->createClient($rawKey);
            $models = $client->models()->list();

            $modelsCount = isset($models->models) ? count($models->models) : 0;

            if ($isModel) {
                $keyOrModel->forceFill([
                    'last_tested_at' => now(),
                    'status' => 'active',
                    'last_error_message' => null,
                ])->saveQuietly();
            }

            return [
                'success' => true,
                'message' => "Chave válida! Conexão realizada com sucesso ({$modelsCount} modelos disponíveis).",
                'models_count' => $modelsCount,
            ];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $lower = strtolower($msg);

            $isRateLimit = str_contains($lower, 'quota') ||
                           str_contains($lower, '429') ||
                           str_contains($lower, 'resource exhausted') ||
                           str_contains($lower, 'too many requests');

            $isInvalid = str_contains($lower, 'api_key_invalid') ||
                         str_contains($lower, 'invalid api key') ||
                         str_contains($lower, 'permission_denied') ||
                         str_contains($lower, '403') ||
                         str_contains($lower, '401');

            if ($isModel) {
                $keyOrModel->recordFailure($msg, isRateLimit: $isRateLimit, isInvalid: $isInvalid);
            }

            if ($isInvalid) {
                return [
                    'success' => false,
                    'message' => 'Chave de API inválida ou sem permissões no Google AI Studio.',
                    'error' => $msg,
                ];
            }

            if ($isRateLimit) {
                return [
                    'success' => false,
                    'message' => 'Chave válida, porém excedeu a cota de requisições ou limite da API.',
                    'error' => $msg,
                ];
            }

            return [
                'success' => false,
                'message' => 'Falha ao testar conexão com o Gemini: '.$msg,
                'error' => $msg,
            ];
        }
    }

    /**
     * Rotaciona para outra chave disponível quando a atual falha por limite de cota.
     */
    public function rotateKey(GeminiApiKey $failedKey, Throwable $exception): ?GeminiApiKey
    {
        $failedKey->recordFailure($exception->getMessage(), isRateLimit: true);

        try {
            $nextKey = GeminiApiKey::query()
                ->available()
                ->where('id', '!=', $failedKey->id)
                ->orderByDesc('is_default')
                ->orderBy('priority')
                ->orderBy('last_used_at')
                ->first();

            if ($nextKey) {
                $this->configureContainerClient($nextKey);
                Log::info("Chave Gemini rotacionada para [{$nextKey->name}] após esgotamento da chave [{$failedKey->name}].");

                return $nextKey;
            }
        } catch (Throwable $e) {
            Log::error('Erro ao rotacionar chave Gemini: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Retorna todas as chaves cadastradas ordenadas.
     *
     * @return Collection<int, GeminiApiKey>
     */
    public function getAllKeys(): Collection
    {
        return GeminiApiKey::query()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    /**
     * Importa a chave definida no .env caso ainda não esteja cadastrada no banco.
     */
    public function importFromEnv(): ?GeminiApiKey
    {
        $envKey = config('gemini.api_key') ?: env('GEMINI_API_KEY');

        if (empty($envKey)) {
            return null;
        }

        // Verifica se já existe alguma chave com este mesmo valor
        $existing = GeminiApiKey::all()->first(fn ($k) => $k->key === $envKey);
        if ($existing) {
            return $existing;
        }

        $hasKeys = GeminiApiKey::count() > 0;

        return GeminiApiKey::create([
            'name' => 'Chave Importada (.env)',
            'key' => $envKey,
            'is_active' => true,
            'is_default' => ! $hasKeys, // se for a primeira, define como padrão
            'priority' => 0,
            'status' => 'active',
        ]);
    }
}
