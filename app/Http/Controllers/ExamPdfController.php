<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExamPdfController extends Controller
{
    public function download(Request $request, Exam $exam)
    {
        if ($exam->user_id != auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        $isJson = false;
        $jsonData = null;
        $htmlContent = '';

        if (! empty($exam->content_json) || $exam->mime_type === 'application/json') {
            $isJson = true;
            $jsonData = $exam->parsed_json_data;

            if (! $jsonData) {
                abort(404, 'O conteúdo estruturado desta prova não pôde ser carregado.');
            }
        } else {
            if (empty($exam->file_path) || ! Storage::disk('public')->exists($exam->file_path)) {
                abort(404, 'Arquivo da prova não encontrado.');
            }
            $rawContent = Storage::disk('public')->get($exam->file_path);
            $htmlContent = Str::markdown($rawContent);
        }

        $printProfile = null;
        if ($request->filled('profile')) {
            $printProfile = auth()->user()->printProfiles()->find($request->profile);
        }

        $html = view('pdf.exam', [
            'exam' => $exam,
            'jsonData' => $jsonData,
            'htmlContent' => $htmlContent,
            'isJson' => $isJson,
            'printProfile' => $printProfile,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $safeTitle = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $exam->title);
        $fileName = "prova_{$safeTitle}_".$exam->created_at->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdfContent),
            $fileName
        );
    }
}
