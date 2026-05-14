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
     * @param array $parts
     * @param string|null $preferredModel
     * @return mixed
     * @throws Throwable
     */
    public function generateContent(array $parts, ?string $preferredModel = null)
    {
        $models = config('gemini.fallback_models', []);
        
        // Se houver um modelo preferido que não está na lista, coloca ele no topo
        if ($preferredModel && !in_array($preferredModel, $models)) {
            array_unshift($models, $preferredModel);
        }

        $lastException = null;

        foreach ($models as $model) {
            try {
                return Gemini::generativeModel($model)->generateContent(...$parts);
            } catch (Throwable $e) {
                $lastException = $e;
                
                // Se o erro for de cota ou limite, tenta o próximo modelo
                if ($this->isQuotaError($e)) {
                    Log::warning("Cota excedida para o modelo {$model}. Tentando o próximo modelo da lista de fallback.");
                    continue;
                }

                // Se for outro tipo de erro, interrompe e joga a exceção
                throw $e;
            }
        }

        throw $lastException;
    }

    /**
     * Identifica se a exceção é relacionada a limites de cota.
     */
    private function isQuotaError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'quota') || 
               str_contains($message, 'limit') || 
               str_contains($message, 'too many requests') ||
               str_contains($message, '429');
    }
}
