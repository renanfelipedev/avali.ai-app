<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExamPdfController extends Controller
{
    public function download(Request $request, Exam $exam)
    {
        if ($exam->user_id != auth()->id()) {
            abort(403);
        }

        if (!Storage::disk('public')->exists($exam->file_path)) {
            abort(404, 'Arquivo da prova não encontrado.');
        }

        $rawContent = Storage::disk('public')->get($exam->file_path);
        $isJson = $exam->mime_type === 'application/json';
        $jsonData = null;
        $htmlContent = '';

        if ($isJson) {
            $jsonData = json_decode($rawContent, true);
        } else {
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

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $safeTitle = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', ' '], '_', $exam->title);
        $fileName = "prova_{$safeTitle}_" . $exam->created_at->format('Y-m-d') . ".pdf";
        
        return response()->streamDownload(
            fn () => print($pdfContent),
            $fileName
        );
    }
}
