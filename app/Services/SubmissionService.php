<?php

namespace App\Services;

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Jobs\EvaluateSubmissionJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmissionService
{
    protected array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'doc', 'txt'];
    protected PdfConverterService $pdfConverter;

    public function __construct(PdfConverterService $pdfConverter)
    {
        $this->pdfConverter = $pdfConverter;
    }

    /**
     * Process a collection of uploaded files or ZIPs.
     */
    public function processUploads(ExamEvaluation $evaluation, array $uploads): void
    {
        $evaluation->update(['status' => 'processing']);

        foreach ($uploads as $file) {
            try {
                $extension = strtolower($file->getClientOriginalExtension());

                if ($extension === 'zip') {
                    $this->processZip($evaluation, $file);
                } elseif (in_array($extension, $this->allowedExtensions)) {
                    $this->createAndDispatch($evaluation, $file);
                }
            } catch (\Throwable $e) {
                Log::error('Erro ao processar um arquivo individual: ' . $e->getMessage(), [
                    'evaluation_id' => $evaluation->id,
                    'file' => $file->getClientOriginalName()
                ]);
                // Continua para o próximo arquivo...
            }
        }
    }

    /**
     * Process a ZIP file containing multiple submissions.
     */
    protected function processZip(ExamEvaluation $evaluation, UploadedFile $zipFile): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipFile->getRealPath()) === TRUE) {
            $tempDir = storage_path('app/public/evaluations/temp_' . uniqid());
            $zip->extractTo($tempDir);
            $zip->close();
            
            $files = File::allFiles($tempDir);
            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $this->allowedExtensions)) {
                    $originalFilename = $file->getFilename();
                    $newFilename = uniqid() . '_' . $originalFilename;
                    $newPath = 'evaluations/submissions/' . $newFilename;
                    
                    // Use stream for memory efficiency
                    $source = fopen($file->getRealPath(), 'r');
                    Storage::disk('public')->putStream($newPath, $source);
                    fclose($source);
                    
                    $submission = ExamSubmission::create([
                        'exam_evaluation_id' => $evaluation->id,
                        'student_name' => $this->extractNameFromFilename($originalFilename),
                        'student_file_path' => $newPath, // Save original, convert later
                        'status' => 'pending',
                    ]);
                    
                    EvaluateSubmissionJob::dispatch($evaluation, $submission);
                }
            }
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Create a single submission and dispatch its job.
     */
    protected function createAndDispatch(ExamEvaluation $evaluation, UploadedFile $file): void
    {
        $originalFilename = $file->getClientOriginalName();
        $path = $file->store('evaluations/submissions', 'public');

        $submission = ExamSubmission::create([
            'exam_evaluation_id' => $evaluation->id,
            'student_name' => $this->extractNameFromFilename($originalFilename),
            'student_file_path' => $path, // Save original, convert later
            'status' => 'pending',
        ]);

        EvaluateSubmissionJob::dispatch($evaluation, $submission);
    }

    /**
     * Extract a potential student name from a filename.
     */
    public function extractNameFromFilename(string $filename): ?string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $remove = ['prova', 'avaliacao', 'questionario', 'trabalho', 'exercicio', 'bimestre', 'semestre', 'final', 'doc', 'pdf', 'docx', 'txt'];
        
        $name = str_replace(['_', '-', '.', '(', ')', '[', ']', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ' ', $name);
        
        foreach ($remove as $word) {
            $name = preg_replace('/\b' . $word . '\b/i', '', $name);
        }

        $name = trim(ucwords(strtolower(preg_replace('/\s+/', ' ', $name))));

        return $name ?: null;
    }
}
