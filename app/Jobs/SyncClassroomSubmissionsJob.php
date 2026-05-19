<?php

namespace App\Jobs;

use App\Models\ExamEvaluation;
use App\Models\ExamSubmission;
use App\Services\GoogleClassroomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncClassroomSubmissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ExamEvaluation $evaluation;

    /**
     * Create a new job instance.
     */
    public function __construct(ExamEvaluation $evaluation)
    {
        $this->evaluation = $evaluation;
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleClassroomService $classroomService): void
    {
        $user = $this->evaluation->user;

        if (! $user || ! $this->evaluation->google_course_id || ! $this->evaluation->google_coursework_id) {
            Log::warning('SyncClassroomSubmissionsJob abortado: dados insuficientes do Classroom ou usuário.', [
                'evaluation_id' => $this->evaluation->id,
            ]);
            $this->evaluation->update(['status' => 'failed']);

            return;
        }

        Log::info('Iniciando importação de entregas do Google Classroom para avaliação', [
            'evaluation_id' => $this->evaluation->id,
            'course_id' => $this->evaluation->google_course_id,
            'coursework_id' => $this->evaluation->google_coursework_id,
        ]);

        try {
            // Retrieve submissions from classroom coursework
            $submissions = $classroomService->listSubmissions(
                $user,
                $this->evaluation->google_course_id,
                $this->evaluation->google_coursework_id
            );

            if (empty($submissions)) {
                Log::info('Nenhuma entrega encontrada no Classroom para a atividade.', [
                    'evaluation_id' => $this->evaluation->id,
                ]);
                $this->evaluation->update(['status' => 'processing']); // switch to processing anyway

                return;
            }

            $dispatchedCount = 0;

            foreach ($submissions as $sub) {
                // Check if already imported
                $exists = ExamSubmission::where('exam_evaluation_id', $this->evaluation->id)
                    ->where('google_submission_id', $sub['id'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                // If student has attachments, download the first valid one
                if (! empty($sub['attachments'])) {
                    $attachment = $sub['attachments'][0]; // standard: first attachment
                    $fileId = $attachment['id'];

                    Log::info('Baixando anexo do aluno do Drive', [
                        'student' => $sub['student_name'],
                        'file_id' => $fileId,
                    ]);

                    $localPath = $classroomService->downloadDriveFile($user, $fileId);

                    if ($localPath) {
                        $submission = ExamSubmission::create([
                            'exam_evaluation_id' => $this->evaluation->id,
                            'student_name' => $sub['student_name'] ?? 'Estudante Sem Nome',
                            'student_file_path' => $localPath,
                            'status' => 'pending',
                            'google_submission_id' => $sub['id'],
                        ]);

                        EvaluateSubmissionJob::dispatch($this->evaluation, $submission);
                        $dispatchedCount++;
                    } else {
                        Log::error('Falha ao baixar anexo da entrega do aluno', [
                            'student' => $sub['student_name'],
                            'submission_id' => $sub['id'],
                        ]);
                    }
                } else {
                    Log::info('Entrega sem anexos, pulando.', [
                        'student' => $sub['student_name'],
                        'submission_id' => $sub['id'],
                    ]);
                }
            }

            // Set evaluation to processing, so the UI updates
            $this->evaluation->update(['status' => 'processing']);

            Log::info("Importação concluída. {$dispatchedCount} entregas enfileiradas para correção.", [
                'evaluation_id' => $this->evaluation->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro geral ao sincronizar entregas do Classroom: '.$e->getMessage(), [
                'evaluation_id' => $this->evaluation->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->evaluation->update(['status' => 'failed']);
        }
    }
}
