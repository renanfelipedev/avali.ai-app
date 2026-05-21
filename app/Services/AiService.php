<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiService
{
    /**
     * Gera conteúdo utilizando o sistema de fallback para evitar erros de cota.
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public function generateContent(array $parts, ?string $preferredModel = null)
    {
        $models = config('gemini.fallback_models', []);

        // Se houver um modelo preferido que não está na lista, coloca ele no topo
        if ($preferredModel && ! in_array($preferredModel, $models)) {
            array_unshift($models, $preferredModel);
        }

        $lastException = null;

        foreach ($models as $model) {
            try {
                return Gemini::generativeModel($model)->generateContent(...$parts);
            } catch (Throwable $e) {
                $lastException = $e;

                // Se o erro for de cota, limite ou sobrecarga/alta demanda, tenta o próximo modelo
                if ($this->isQuotaOrOverloadError($e)) {
                    Log::warning("Cota excedida ou alta demanda para o modelo {$model} (Erro: {$e->getMessage()}). Tentando o próximo modelo da lista de fallback.");

                    continue;
                }

                // Se for outro tipo de erro, interrompe e joga a exceção
                throw $e;
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
