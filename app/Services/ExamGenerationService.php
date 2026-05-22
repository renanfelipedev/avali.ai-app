<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Exam;
use App\Models\ExamGenerationRequest;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExamGenerationService
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function generateExam(ExamGenerationRequest $request): bool
    {
        $generatedText = null;
        $request->update(['status' => 'processing']);

        $topicsString = is_array($request->topics) ? implode(', ', $request->topics) : $request->topics;

        try {
            $prompt = view('prompts.generation.exam', [
                'objective_count' => $request->objective_count,
                'discursive_count' => $request->discursive_count,
                'topics' => $topicsString,
                'title' => $request->title,
                'additional_criteria' => $request->additional_criteria,
            ])->render();

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

            // Call Gemini via Fallback Service com configuração JSON (sem schema rigoroso para evitar alucinação em modelos menores)
            $generationConfig = new \Gemini\Data\GenerationConfig(
                responseMimeType: \Gemini\Enums\ResponseMimeType::APPLICATION_JSON
            );
            $response = $this->aiService->generateContent($parts, null, $generationConfig);
            $generatedText = trim($response->text());

            // Extract JSON safely using bounds and ignoring markdown
            $generatedText = $this->extractJson($generatedText);

            if (empty($generatedText)) {
                throw new \Exception('O Gemini retornou um texto vazio ou não foi possível extrair um JSON.');
            }

            // Interpret JSON using PHP tools to validate and extract data
            $examData = json_decode($generatedText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Tentar limpar possíveis sujeiras que quebram o JSON (ex: trailing commas, unescaped newlines)
                $cleanJson = $this->sanitizeJsonString($generatedText);
                $examData = json_decode($cleanJson, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $generatedText = $cleanJson;
                } else {
                    throw new \Exception('O retorno da IA não é um JSON válido: '.json_last_error_msg());
                }
            }

            // Validação estrutural básica
            if (!isset($examData['questions']) || !is_array($examData['questions'])) {
                throw new \Exception('O JSON retornado não contém o array de questões esperado.');
            }

            // Create the Exam record (saved directly to DB)
            $exam = Exam::create([
                'user_id' => $request->user_id,
                'classroom_id' => $request->classroom_id,
                'title' => $request->title ?: ($examData['title'] ?? ('Prova Gerada: '.Str::limit($topicsString, 50))),
                'description' => 'Prova de '.$request->questions_count.' questões. Temas: '.$topicsString,
                'mime_type' => 'application/json',
                'content_json' => $examData,
            ]);

            // Log successful generation with tokens
            AiLog::create([
                'module' => 'ExamGeneration',
                'tokens_used' => $response->usageMetadata->totalTokenCount ?? 0,
                'request_payload' => [
                    'exam_id' => $exam->id,
                    'user_id' => $request->user_id,
                ],
            ]);
            // Update request status
            $request->update(['status' => 'completed']);

            return true;
        } catch (Throwable $e) {
            $request->update([
                'status' => 'error',
                'error_message' => 'Erro na geração: ' . $e->getMessage(),
            ]);

            $errorDetails = sprintf(
                "❌ Falha na Geração da Prova\n\n" .
                "📝 Motivo: %s\n" .
                "📁 Origem: %s (Linha: %d)\n\n" .
                "🔍 Trace Resumido:\n%s",
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine(),
                substr($e->getTraceAsString(), 0, 1500)
            );

            AiLog::create([
                'module' => 'ExamGeneration',
                'error_message' => $errorDetails,
                'request_payload' => [
                    'exam_generation_request_id' => $request->id,
                    'user_id' => $request->user_id,
                    'topics' => $request->topics,
                    'questions_count' => $request->questions_count,
                    'failed_ai_response' => $generatedText ?? 'Nenhum texto gerado ou falha antes da geração.',
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

    /**
     * Extrai a string JSON de um texto que pode conter markdown ou texto adicional.
     */
    private function extractJson(string $text): string
    {
        // Remove markdown tags (```json ... ```)
        $text = preg_replace('/```(?:json)?/i', '', $text);
        
        // Encontra o primeiro { e o último }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end >= $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    /**
     * Limpa problemas comuns em strings JSON geradas por IA (Syntax Error).
     */
    private function sanitizeJsonString(string $json): string
    {
        // Remove trailing commas (vírgulas sobrando antes de fechar chaves/colchetes)
        $json = preg_replace('/,\s*([\}\]])/', '$1', $json);
        
        // Substitui caracteres de controle literais (como quebras de linha não escapadas dentro de strings) por espaço.
        // O JSON parser padrão falha se houver quebras de linha reais dentro de valores string.
        // Isso também compacta o JSON em uma única linha, o que não afeta a validade estrutural.
        $json = preg_replace('/[\x00-\x1F]+/', ' ', $json);
        
        return trim($json);
    }
}
