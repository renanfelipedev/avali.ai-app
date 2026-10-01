<?php

namespace App\Services;

use Gemini\Data\GenerationConfig;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiService
{
    public function __construct(
        protected GeminiApiKeyService $keyService
    ) {}

    /**
     * Gera conteúdo utilizando o sistema de chave dinâmica e fallback de modelos.
     *
     * @param  array<int, mixed>  $parts
     * @return mixed
     *
     * @throws Throwable
     */
    public function generateContent(array $parts, ?string $preferredModel = null, ?GenerationConfig $generationConfig = null)
    {
        if (! $this->keyService->hasValidKey()) {
            throw new \Exception('Nenhuma chave de API do Gemini configurada no sistema. Acesse o menu de Chaves Gemini para cadastrar uma chave.');
        }

        $currentKey = $this->keyService->getActiveKey();
        $this->keyService->configureContainerClient($currentKey);

        $models = $this->keyService->getFallbackModels();

        // Se houver um modelo preferido que não está na lista, coloca ele no topo
        if ($preferredModel && ! in_array($preferredModel, $models)) {
            array_unshift($models, $preferredModel);
        }

        $lastException = null;

        foreach ($models as $model) {
            $keyAttempts = 0;
            $maxKeyAttempts = 3; // Limite de rotação de chaves por modelo para evitar loop infinito

            while ($keyAttempts < $maxKeyAttempts) {
                $keyAttempts++;

                try {
                    $generativeModel = Gemini::generativeModel($model);

                    if ($generationConfig) {
                        $generativeModel = $generativeModel->withGenerationConfig($generationConfig);
                    }

                    $response = $generativeModel->generateContent(...$parts);

                    if ($currentKey) {
                        $currentKey->recordSuccess();
                    }

                    return $response;
                } catch (Throwable $e) {
                    $lastException = $e;

                    if ($this->isQuotaOrOverloadError($e)) {
                        // Tenta rotacionar para outra chave ativa antes de mudar o modelo
                        if ($currentKey) {
                            $nextKey = $this->keyService->rotateKey($currentKey, $e);
                            if ($nextKey) {
                                Log::warning("Cota da chave [{$currentKey->name}] excedida. Alternando para a chave [{$nextKey->name}] e tentando novamente o modelo {$model}.");
                                $currentKey = $nextKey;

                                continue; // Tenta o mesmo modelo com a nova chave
                            }
                        }

                        Log::warning("Cota ou alta demanda para o modelo {$model} (Erro: {$e->getMessage()}). Tentando o próximo modelo da lista de fallback.");
                        break; // Sai do while de chaves e vai para o próximo modelo
                    }

                    // Se for erro de credencial inválida, marca no registro da chave
                    if ($currentKey && (str_contains(strtolower($e->getMessage()), 'api_key_invalid') || str_contains(strtolower($e->getMessage()), 'invalid api key'))) {
                        $currentKey->recordFailure($e->getMessage(), isInvalid: true);
                    } elseif ($currentKey) {
                        $currentKey->recordFailure($e->getMessage());
                    }

                    throw $e;
                }
            }
        }

        throw $lastException;
    }

    /**
     * Identifica se a exceção é relacionada a limites de cota ou sobrecarga/alta demanda temporária.
     */
    private function isQuotaOrOverloadError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'quota') ||
               str_contains($message, 'limit') ||
               str_contains($message, 'too many requests') ||
               str_contains($message, '429') ||
               str_contains($message, 'demand') ||
               str_contains($message, 'spike') ||
               str_contains($message, 'overload') ||
               str_contains($message, 'unavailable') ||
               str_contains($message, '503') ||
               str_contains($message, 'resource exhausted') ||
               str_contains($message, 'try again later');
    }
}
