<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'classroom_id', 'title', 'description', 'file_path', 'original_name', 'mime_type', 'file_size', 'scheduled_at', 'sent_at', 'content_json'])]
class Exam extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'content_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function getParsedJsonDataAttribute(): ?array
    {
        $jsonData = null;

        if (! empty($this->content_json)) {
            $jsonData = is_array($this->content_json) ? $this->content_json : json_decode($this->content_json, true);
        } elseif ($this->mime_type === 'application/json' && ! empty($this->file_path) && Storage::disk('public')->exists($this->file_path)) {
            $rawContent = Storage::disk('public')->get($this->file_path);
            $jsonData = json_decode($rawContent, true);
        }

        if (! is_array($jsonData)) {
            return null;
        }

        // Normaliza o novo Schema estruturado (array unificado de 'questions')
        // para o formato legado (objective_questions e discursive_questions) esperado pelas Views.
        if (isset($jsonData['questions'])) {
            $objective = [];
            $discursive = [];
            $counter = 1;

            foreach ($jsonData['questions'] as $q) {
                if (! is_array($q)) {
                    continue; // Pula a questão caso a IA tenha alucinado e retornado uma string em vez de um objeto
                }

                $q['number'] = $counter++;
                $q['text'] = $q['statement'] ?? '';

                if (($q['type'] ?? '') === 'objective') {
                    $q['answer'] = $q['correct_answer'] ?? '';
                    if (isset($q['options'])) {
                        if (! is_array($q['options'])) {
                            $q['options'] = [$q['options']];
                        }

                        // Remove prefixos como "A) ", "(B) ", "c. ", "D - " das opções para não duplicar com as Views
                        $cleanOptions = [];
                        foreach ($q['options'] as $opt) {
                            $cleanOptions[] = preg_replace('/^\s*(?:\([a-e1-5]\)|[a-e1-5]\s*[\.\-\:\)])\s*(?:[\-\:]\s*)?/i', '', $opt);
                        }
                        $q['options'] = $cleanOptions;
                    }
                    $objective[] = $q;
                } else {
                    $answerKey = [];
                    if (! empty($q['expected_answer'])) {
                        $answerKey[] = 'Esperado: '.(is_array($q['expected_answer']) ? implode(', ', $q['expected_answer']) : $q['expected_answer']);
                    }
                    if (! empty($q['evaluation_criteria'])) {
                        $answerKey[] = 'Critérios: '.(is_array($q['evaluation_criteria']) ? implode(', ', $q['evaluation_criteria']) : $q['evaluation_criteria']);
                    }

                    $q['answer_key'] = implode(' | ', $answerKey);
                    $discursive[] = $q;
                }
            }

            $jsonData['objective_questions'] = $objective;
            $jsonData['discursive_questions'] = $discursive;
        }

        return $jsonData;
    }
}
