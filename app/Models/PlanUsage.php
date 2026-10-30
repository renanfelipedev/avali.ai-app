<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'period',
    'exams_generated_count',
    'submissions_evaluated_count',
])]
class PlanUsage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exams_generated_count' => 'integer',
            'submissions_evaluated_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna ou cria o registro de uso do usuário para o mês corrente.
     */
    public static function currentForUser(int|User $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;
        $period = now()->format('Y-m');

        return static::firstOrCreate(
            ['user_id' => $userId, 'period' => $period],
            ['exams_generated_count' => 0, 'submissions_evaluated_count' => 0]
        );
    }
}
