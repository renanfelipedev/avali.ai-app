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
    protected AiService $aiService;
    protected string $module = 'ExamGrading';

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function evaluateSubmission(ExamEvaluation $evaluation, ExamSubmission $submission): bool
    {
        $this->verifyAiAccess();

        $submission->update([
            'status' => 'processing',
            'status_message' => 'Iniciando correção...'
        ]);        try {
            $submission->update(['status_message' => 'Lendo conteúdo do arquivo...']);
            $prompt = $this->buildSystemPrompt($evaluation);

            $parts = [$prompt];

            // Answer Key
            if (!empty($evaluation->answer_key_file_path)) {
                $parts[] = $this->createBlobFromPath($evaluation->answer_key_file_path);
            }

            // Student Submission
            $parts[] = $this->prepareStudentPart($submission->student_file_path);

            $submission->update(['status_message' => 'Consultando Inteligência Artificial...']);
            $response = $this->aiService->generateContent($parts);

            $data = $this->parseJsonResponse($response->text());
            $result = $data[0] ?? $data;

            $submission->update(['status_message' => 'Finalizando resultados...']);

            $submission->update([
                'student_name'   => $result['student_name'] ?? ($submission->student_name ?: 'Aluno Desconhecido'),
                'final_grade'    => $result['final_grade'] ?? 0,
                'feedback_data'  => $result['questions'] ?? [],
                'transcription'  => $result['full_transcription'] ?? null,
                'status'         => 'completed',
                'status_message' => 'Concluído com sucesso',
            ]);

            $this->logInteraction($submission->id, $evaluation->id, [
                'tokens_used' => $response->usageMetadata->totalTokenCount ?? 0,
            ]);

            return true;
        } catch (Throwable $e) {
            return $this->handleGradingError($e, $submission, $evaluation);
        }
    }

    private function createBlobFromPath(string $path): Blob
    {
        $fullPath = Storage::disk('public')->path($path);
        return new Blob(
            mimeType: $this->getMimeType($fullPath),
            data: base64_encode(file_get_contents($fullPath))
        );
    }

    private function prepareStudentPart(string $filePath): Blob|string
    {
        $fullPath = Storage::disk('public')->path($filePath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['docx', 'txt'])) {
            return "CONTEÚDO DA PROVA DO ALUNO (Documento B):\n\n" . $this->extractText($fullPath, $extension);
        }

        return $this->createBlobFromPath($filePath);
    }

    private function parseJsonResponse(string $text): array
    {
        // Clean markdown and control characters
        $text = str_replace(['```json', '```'], '', $text);
        if (preg_match('/(\[.*\]|\{.*\})/s', $text, $matches)) {
            $text = $matches[1];
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('O retorno do Gemini não foi um JSON válido: ' . json_last_error_msg());
        }

        return $data;
    }

    private function handleGradingError(Throwable $e, ExamSubmission $submission, ExamEvaluation $evaluation): bool
    {
        $errorMessage = $e->getMessage();
        $isTransient = $this->isTransientError($errorMessage);

        $this->logInteraction($submission->id, $evaluation->id, [
            'error_message' => $errorMessage . "\n" . $e->getTraceAsString(),
            'is_transient'  => $isTransient,
        ], true);

        if ($isTransient) {
            throw $e;
        }

        $submission->update([
            'status'         => 'error',
            'status_message' => 'Erro na correção',
            'error_message'  => $errorMessage,
        ]);

        return false;
    }

    private function isTransientError(string $message): bool
    {
        $message = strtolower($message);
        $transientKeywords = ['timed out', 'high demand', 'too many requests', 'quota exceeded', 'rate limit', 'service unavailable'];
        
        foreach ($transientKeywords as $keyword) {
            if (str_contains($message, $keyword)) return true;
        }

        return false;
    }

    private function logInteraction(int $submissionId, int $evaluationId, array $data, bool $isError = false): void
    {
        $payload = [
            'module'          => $this->module,
            'request_payload' => array_merge([
                'exam_submission_id' => $submissionId,
                'exam_evaluation_id' => $evaluationId,
            ], $data['request_payload'] ?? []),
        ];

        if ($isError) {
            $payload['error_message'] = $data['error_message'] ?? 'Unknown error';
        } else {
            $payload['tokens_used'] = $data['tokens_used'] ?? 0;
        }

        \App\Models\AiLog::create($payload);
    }
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
