<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'price_cents',
    'features',
    'is_active',
    'is_featured',
    'sort_order',
])]
class Plan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Retorna o plano gratuito padrão do sistema.
     */
    public static function free(): static
    {
        return static::firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Gratuito (Starter)',
                'description' => 'Ideal para professores experimentando a plataforma.',
                'price_cents' => 0,
                'features' => [
                    'monthly_exams_limit' => 5,
                    'monthly_submissions_limit' => 25,
                    'max_questions_per_exam' => 10,
                    'max_classrooms' => 2,
                    'max_students' => 50,
                    'allow_question_images' => false,
                    'allow_google_classroom' => false,
                    'allow_custom_print_profiles' => false,
                    'allow_docx_export' => false,
                    'allow_geofencing_attendance' => false,
                    'queue_priority' => 'default',
                    'ai_model' => 'gemini-3.5-flash-lite',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * Obtém o valor de uma feature com fallback.
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        $features = $this->features ?? [];

        return $features[$key] ?? $default;
    }

    /**
     * Verifica se o plano permite uma determinada funcionalidade booleana.
     */
    public function allows(string $featureKey): bool
    {
        return (bool) $this->getFeature($featureKey, false);
    }

    /**
     * Preço formatado em Reais (R$).
     */
    public function formattedPrice(): string
    {
        if ($this->price_cents === 0) {
            return 'Grátis';
        }

        return 'R$ '.number_format($this->price_cents / 100, 2, ',', '.');
    }
}
