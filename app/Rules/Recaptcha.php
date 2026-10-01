<?php

namespace App\Rules;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

class Recaptcha implements ValidationRule
{
    public function __construct(
        protected ?string $action = null,
        protected ?float $minScore = null
    ) {
        $this->minScore = $minScore ?? SystemSetting::getFloat('recaptcha_min_score', (float) config('services.recaptcha.min_score', 0.5));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Se o reCAPTCHA estiver desativado no sistema, não bloqueia
        if (! SystemSetting::getBool('recaptcha_enabled', true)) {
            return;
        }

        // Se estiver em ambiente de teste e sem token enviado (ou sem chave), ignora para não quebrar testes unitários
        if (app()->environment('testing') && empty($value)) {
            return;
        }

        $secretKey = SystemSetting::get('recaptcha_secret_key', config('services.recaptcha.secret_key'));

        if (empty($secretKey)) {
            if (app()->environment('local', 'testing')) {
                return;
            }
            $fail('A validação de segurança não está configurada.');

            return;
        }

        if (empty($value) || ! is_string($value)) {
            $fail('A verificação de segurança (reCAPTCHA) falhou. Por favor, atualize a página.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false)) {
                Log::warning('reCAPTCHA validation failed', [
                    'errors' => $result['error-codes'] ?? [],
                    'ip' => request()->ip(),
                ]);

                $fail('Falha na validação do reCAPTCHA. Por favor, recarregue a página e tente novamente.');

                return;
            }

            if (isset($result['score']) && (float) $result['score'] < $this->minScore) {
                Log::warning('reCAPTCHA score too low', [
                    'score' => $result['score'],
                    'min_score' => $this->minScore,
                    'action' => $result['action'] ?? null,
                    'ip' => request()->ip(),
                ]);

                $fail('Atividade automatizada detectada. Se você não é um robô, tente novamente.');

                return;
            }

            if ($this->action && isset($result['action']) && $result['action'] !== $this->action) {
                Log::warning('reCAPTCHA action mismatch', [
                    'expected' => $this->action,
                    'received' => $result['action'],
                    'ip' => request()->ip(),
                ]);

                $fail('Falha na ação de segurança.');

                return;
            }
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA service error: '.$e->getMessage());

            if (app()->environment('production')) {
                $fail('Serviço de verificação temporariamente indisponível. Tente novamente.');
            }
        }
    }
}
