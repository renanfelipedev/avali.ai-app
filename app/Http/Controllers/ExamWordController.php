<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class ExamWordController extends Controller
{
    public function download(Request $request, Exam $exam)
    {
        if ($exam->user_id != auth()->id()) {
            abort(403);
        }

        $printProfile = null;
        if ($request->filled('profile')) {
            $printProfile = auth()->user()->printProfiles()->find($request->profile);
        }

        if (! $printProfile || ! $printProfile->template_path || ! Storage::disk('public')->exists($printProfile->template_path)) {
            abort(404, 'Modelo Word não encontrado neste perfil de impressão.');
        }

        $jsonData = $exam->parsed_json_data;
        if (! $jsonData) {
            abort(404, 'O conteúdo estruturado desta prova não pôde ser carregado.');
        }

        $templatePath = Storage::disk('public')->path($printProfile->template_path);

        try {
            $templateProcessor = new TemplateProcessor($templatePath);
        } catch (\Exception $e) {
            abort(500, 'Não foi possível ler o arquivo modelo Word enviado.');
        }

        $textContent = '';

        if (isset($jsonData['objective_questions']) && count($jsonData['objective_questions']) > 0) {
            $textContent .= "Questões Objetivas\n\n";

            foreach ($jsonData['objective_questions'] as $index => $q) {
                $number = $q['number'] ?? ($index + 1);
                $textContent .= $number.'. '.($q['text'] ?? '')."\n";

                if (isset($q['options']) && is_array($q['options'])) {
                    $letterIndex = 0;
                    foreach ($q['options'] as $key => $option) {
                        $letter = chr(65 + $letterIndex);
                        $textContent .= '    '.$letter.') '.$option."\n";
                        $letterIndex++;
                    }
                }
                $textContent .= "\n";
            }
        }

        if (isset($jsonData['discursive_questions']) && count($jsonData['discursive_questions']) > 0) {
            $textContent .= "Questões Discursivas\n\n";

            foreach ($jsonData['discursive_questions'] as $index => $q) {
                $number = $q['number'] ?? ($index + 1);
                $textContent .= $number.'. '.($q['text'] ?? '')."\n";

                // Linhas para o aluno responder
                for ($i = 0; $i < 5; $i++) {
                    $textContent .= "_________________________________________________________________________________\n";
                }
                $textContent .= "\n";
            }
        }

        // Remove a última quebra de linha
        $textContent = trim($textContent);

        // Substitui a tag ${prova} pelo conteúdo formatado nativamente
        $templateProcessor->setValue('prova', $textContent);

        $safeTitle = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $exam->title);
        $fileName = "prova_{$safeTitle}_".$exam->created_at->format('Y-m-d').'.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');
        $templateProcessor->saveAs($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
