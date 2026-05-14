<?php

namespace App\Services;

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Blob;
use Gemini\Data\Part;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExamGradingService
{
    protected $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function evaluateSubmission(ExamEvaluation $evaluation, ExamSubmission $submission): bool
    {
        $this->verifyAiAccess();

        $submission->update(['status' => 'processing']);

        try {
            $prompt = $this->buildSystemPrompt($evaluation);

            // Load student file
            $studentFilePath = Storage::disk('public')->path($submission->student_file_path);
            $studentExtension = strtolower(pathinfo($studentFilePath, PATHINFO_EXTENSION));

            $parts = [$prompt];

            // Conditionally load answer key blob
            $hasAnswerKey = !empty($evaluation->answer_key_file_path);
            if ($hasAnswerKey) {
                $answerKeyPath = Storage::disk('public')->path($evaluation->answer_key_file_path);
                $parts[] = new Blob(
                    mimeType: $this->getMimeType($answerKeyPath),
                    data: base64_encode(file_get_contents($answerKeyPath))
                );
            }

            // Always add student submission last
            if (in_array($studentExtension, ['docx', 'txt'])) {
                $textContent = $this->extractText($studentFilePath, $studentExtension);
                $parts[] = "CONTEÚDO DA PROVA DO ALUNO (Documento B):\n\n" . $textContent;
            } else {
                $parts[] = new Blob(
                    mimeType: $this->getMimeType($studentFilePath),
                    data: base64_encode(file_get_contents($studentFilePath))
                );
            }

            // Use Fallback Service
            $response = $this->aiService->generateContent($parts);

            $text = trim($response->text());
            
            // Clean up possible markdown code block
            $text = str_replace(['```json', '```'], '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('O retorno do Gemini não foi um JSON válido: ' . json_last_error_msg() . " - Retorno: " . $text);
            }

            // A prompt expect an array with student results, we grab the first one
            $result = $data[0] ?? $data;

            $submission->update([
                'student_name' => $result['student_name'] ?? ($submission->student_name ?: 'Aluno Desconhecido'),
                'final_grade' => $result['final_grade'] ?? 0,
                'feedback_data' => $result['questions'] ?? [],
                'transcription' => $result['full_transcription'] ?? null,
                'status' => 'completed',
            ]);

            // Log successful grading with tokens
            \App\Models\AiLog::create([
                'module' => 'ExamGrading',
                'tokens_used' => $response->usageMetadata->totalTokenCount ?? 0,
                'request_payload' => [
                    'exam_submission_id' => $submission->id,
                    'exam_evaluation_id' => $evaluation->id,
                ],
            ]);

            return true;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $isTransient = str_contains(strtolower($errorMessage), 'timed out') || 
                          str_contains(strtolower($errorMessage), 'high demand') ||
                          str_contains(strtolower($errorMessage), 'too many requests') ||
                          str_contains(strtolower($errorMessage), 'quota exceeded') ||
                          str_contains(strtolower($errorMessage), 'rate limit') ||
                          str_contains(strtolower($errorMessage), 'service unavailable');

            // Log the error
            \App\Models\AiLog::create([
                'module' => 'ExamGrading',
                'error_message' => $errorMessage . "\n" . $e->getTraceAsString(),
                'request_payload' => [
                    'exam_submission_id' => $submission->id,
                    'exam_evaluation_id' => $evaluation->id,
                    'student_name' => $submission->student_name,
                    'is_transient' => $isTransient
                ],
            ]);

            if ($isTransient) {
                // Throwing the exception allows the Job to catch it and retry
                throw $e;
            }

            $submission->update([
                'status' => 'error',
                'error_message' => $errorMessage,
            ]);

            return false;
        }
    }

    private function extractText(string $filePath, string $extension): string
    {
        if ($extension === 'txt') {
            return file_get_contents($filePath);
        }

        if ($extension === 'docx') {
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                            foreach ($element->getElements() as $textElement) {
                                if (method_exists($textElement, 'getText')) {
                                    $text .= $textElement->getText();
                                }
                            }
                            $text .= "\n";
                        }
                    }
                }
                return $text;
            } catch (Throwable $e) {
                return "Erro ao extrair texto do DOCX: " . $e->getMessage();
            }
        }

        return "";
    }

    private function getMimeType(string $filePath): MimeType
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => MimeType::APPLICATION_PDF,
            'png' => MimeType::IMAGE_PNG,
            'jpg', 'jpeg' => MimeType::IMAGE_JPEG,
            'webp' => MimeType::IMAGE_WEBP,
            default => MimeType::APPLICATION_PDF, // Fallback
        };
    }

    private function verifyAiAccess(): void
    {
        $apiKey = config('gemini.api_key');
        if (empty($apiKey)) {
            throw new \Exception('Chave de API do Gemini não configurada. Verifique o arquivo .env');
        }
    }

    private function buildSystemPrompt(ExamEvaluation $evaluation): string
    {
        $promptReferencePath = base_path('.docs/prompts/system_grading_expert.md');
        $promptBase = file_exists($promptReferencePath) ? file_get_contents($promptReferencePath) : 'Atue como um Especialista em Avaliação Educacional.';

        $criteria = $evaluation->grading_criteria ?? 'Nenhum critério específico fornecido. Avalie de 0 a 10 por padrão.';
        $hasAnswerKey = !empty($evaluation->answer_key_file_path);

        $instruction = $hasAnswerKey 
            ? "IMPORTANTE: O Documento A (Gabarito) é o primeiro arquivo fornecido. O Documento B (Prova do Aluno) é o segundo arquivo fornecido."
            : "IMPORTANTE: Não há gabarito fornecido. Avalie a Prova do Aluno (único arquivo fornecido) utilizando seu conhecimento especialista sobre a disciplina, aplicando rigorosamente os critérios definidos.";

        return <<<PROMPT
$promptBase

CRITÉRIO DE PONTUAÇÃO ESPECÍFICO DESTA AVALIAÇÃO:
$criteria

$instruction
Extraia a nota e o feedback em formato JSON exatamente conforme as instruções.
Não inclua crases (```json) ou texto Markdown adicional na resposta, apenas o JSON válido.
PROMPT;
    }

}
