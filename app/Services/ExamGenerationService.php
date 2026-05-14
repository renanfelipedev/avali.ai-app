<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamGenerationRequest;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExamGenerationService
{
    public function generateExam(ExamGenerationRequest $request): bool
    {
        $request->update(['status' => 'processing']);

        try {
            // Load Prompt Reference
            $promptReferencePath = base_path('.docs/prompts/system_exam_generator.md');
            $promptBase = file_exists($promptReferencePath) ? file_get_contents($promptReferencePath) : 'Atue como um Especialista em Educação e Elaboração de Provas.';

            $topics = is_array($request->topics) ? implode(', ', $request->topics) : $request->topics;

            $prompt = <<<PROMPT
$promptBase

## Parâmetros da Geração:
- Quantidade de Questões: {$request->questions_count}
- Temas: {$topics}

Sua resposta final deve ser exclusivamente a prova formulada em Markdown.
PROMPT;

            // Prepare Gemini parts
            $parts = [
                $prompt,
            ];

            // Load and append supporting materials
            $materials = $request->supporting_materials ?? [];
            foreach ($materials as $material) {
                if (isset($material['path']) && Storage::disk('public')->exists($material['path'])) {
                    $filePath = Storage::disk('public')->path($material['path']);
                    $mime = $this->getMimeType($filePath);
                    
                    if ($mime) {
                        $parts[] = new Blob(
                            mimeType: $mime,
                            data: base64_encode(file_get_contents($filePath))
                        );
                    }
                }
            }

            // Call Gemini
            $model = config('gemini.default_model');
            $response = Gemini::generativeModel($model)->generateContent(...$parts);
            $generatedText = trim($response->text());

            if (empty($generatedText)) {
                throw new \Exception('O Gemini retornou um texto vazio.');
            }

            // Save the generated exam as a Markdown file
            $fileName = 'exam_' . Str::uuid() . '.md';
            $filePath = 'generated_exams/' . $fileName;
            
            Storage::disk('public')->put($filePath, $generatedText);

            // Create the Exam record
            $exam = Exam::create([
                'user_id' => $request->user_id,
                'title' => 'Prova Gerada: ' . Str::limit($topics, 50),
                'description' => 'Prova de ' . $request->questions_count . ' questões. Temas: ' . $topics,
                'file_path' => $filePath,
                'original_name' => $fileName,
                'mime_type' => 'text/markdown',
                'file_size' => strlen($generatedText),
            ]);

            // Update request
            $request->update([
                'generated_exam_id' => $exam->id,
                'status' => 'completed',
            ]);

            return true;
        } catch (Throwable $e) {
            $request->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            \App\Models\AiLog::create([
                'module' => 'ExamGeneration',
                'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'request_payload' => [
                    'exam_generation_request_id' => $request->id,
                    'user_id' => $request->user_id,
                    'topics' => $request->topics,
                    'questions_count' => $request->questions_count,
                ],
            ]);

            return false;
        }
    }

    private function getMimeType(string $filePath): ?MimeType
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => MimeType::APPLICATION_PDF,
            'png' => MimeType::IMAGE_PNG,
            'jpg', 'jpeg' => MimeType::IMAGE_JPEG,
            'webp' => MimeType::IMAGE_WEBP,
            'txt', 'md', 'csv' => MimeType::TEXT_PLAIN,
            default => null, // Ignore unsupported files
        };
    }
}
