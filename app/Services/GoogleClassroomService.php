<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Google\Service\Classroom;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleClassroomService
{
    /**
     * Get an authenticated Google Client for a given user.
     * Handles automatic token refresh if needed.
     */
    public function getClientForUser(User $user): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));

        $token = [
            'access_token' => $user->google_token,
            'expires_in' => $user->google_token_expires_at ? max(0, $user->google_token_expires_at->getTimestamp() - time()) : 0,
            'created' => $user->updated_at ? $user->updated_at->getTimestamp() : time(),
        ];

        $client->setAccessToken($token);

        // Check if token is expired or close to expiring
        if ($client->isAccessTokenExpired() && $user->google_refresh_token) {
            try {
                Log::info('Renovando Google Access Token para usuário', ['user_id' => $user->id]);
                $newTokens = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);

                if (isset($newTokens['access_token'])) {
                    $expiresIn = $newTokens['expires_in'] ?? 3600;
                    $user->update([
                        'google_token' => $newTokens['access_token'],
                        'google_token_expires_at' => now()->addSeconds($expiresIn),
                    ]);

                    if (isset($newTokens['refresh_token'])) {
                        $user->update([
                            'google_refresh_token' => $newTokens['refresh_token'],
                        ]);
                    }

                    $client->setAccessToken($newTokens);
                } else {
                    Log::error('Erro ao renovar token do Google: retorno inválido', ['response' => $newTokens]);
                }
            } catch (\Exception $e) {
                Log::error('Exceção ao renovar token do Google', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $client;
    }

    /**
     * List active courses taught by the teacher.
     */
    public function listCourses(User $user): array
    {
        try {
            $client = $this->getClientForUser($user);
            $service = new Classroom($client);

            $response = $service->courses->listCourses([
                'teacherId' => 'me',
                'courseStates' => 'ACTIVE',
            ]);

            $courses = [];
            if ($response->getCourses()) {
                foreach ($response->getCourses() as $course) {
                    $courses[] = [
                        'id' => $course->getId(),
                        'name' => $course->getName(),
                        'section' => $course->getSection() ?? '',
                    ];
                }
            }

            return $courses;
        } catch (\Exception $e) {
            Log::error('Erro ao listar cursos do Google Classroom', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * List assignments (courseWork) for a given course.
     */
    public function listCourseWork(User $user, string $courseId): array
    {
        try {
            $client = $this->getClientForUser($user);
            $service = new Classroom($client);

            $response = $service->courses_courseWork->listCoursesCourseWork($courseId);

            $courseWorks = [];
            if ($response->getCourseWork()) {
                foreach ($response->getCourseWork() as $cw) {
                    $courseWorks[] = [
                        'id' => $cw->getId(),
                        'title' => $cw->getTitle(),
                        'description' => $cw->getDescription() ?? '',
                    ];
                }
            }

            return $courseWorks;
        } catch (\Exception $e) {
            Log::error('Erro ao listar tarefas do Google Classroom', [
                'course_id' => $courseId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * List student submissions for a coursework.
     */
    public function listSubmissions(User $user, string $courseId, string $courseWorkId): array
    {
        try {
            $client = $this->getClientForUser($user);
            $service = new Classroom($client);

            $response = $service->courses_courseWork_studentSubmissions->listCoursesCourseWorkStudentSubmissions($courseId, $courseWorkId);

            $submissions = [];
            if ($response->getStudentSubmissions()) {
                foreach ($response->getStudentSubmissions() as $sub) {
                    // Extract attachments (if any)
                    $attachments = [];
                    $materials = $sub->getAssignmentSubmission()?->getAttachments() ?? [];
                    foreach ($materials as $material) {
                        if ($material->getDriveFile()) {
                            $attachments[] = [
                                'id' => $material->getDriveFile()->getId(),
                                'title' => $material->getDriveFile()->getTitle(),
                                'url' => $material->getDriveFile()->getAlternateLink(),
                            ];
                        }
                    }

                    // Get student profile to extract their name
                    $studentName = $this->getStudentName($service, $courseId, $sub->getUserId());

                    $submissions[] = [
                        'id' => $sub->getId(),
                        'user_id' => $sub->getUserId(),
                        'student_name' => $studentName,
                        'state' => $sub->getState(),
                        'attachments' => $attachments,
                    ];
                }
            }

            return $submissions;
        } catch (\Exception $e) {
            Log::error('Erro ao listar entregas do Google Classroom', [
                'course_id' => $courseId,
                'coursework_id' => $courseWorkId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Retrieve student name using classroom courses_students or userProfiles service.
     */
    protected function getStudentName(Classroom $service, string $courseId, string $studentUserId): string
    {
        try {
            // First try getting from courses_students (requires classroom.rosters scope)
            $student = $service->courses_students->get($courseId, $studentUserId);

            return $student->getProfile()?->getName()?->getFullName() ?? 'Estudante Sem Nome';
        } catch (\Exception $e) {
            try {
                // Fallback to userProfiles
                $profile = $service->userProfiles->get($studentUserId);

                return $profile->getName()?->getFullName() ?? "ID: {$studentUserId}";
            } catch (\Exception $ex) {
                return "Estudante {$studentUserId}";
            }
        }
    }

    /**
     * Push grade and private feedback comment back to Google Classroom coursework.
     */
    public function updateSubmissionGrade(User $user, string $courseId, string $courseWorkId, string $submissionId, float $grade, string $feedbackText): bool
    {
        try {
            $client = $this->getClientForUser($user);
            $service = new Classroom($client);

            // Fetch submission
            $submission = $service->courses_courseWork_studentSubmissions->get($courseId, $courseWorkId, $submissionId);

            $submission->setAssignedGrade($grade);
            $submission->setDraftGrade($grade);

            // Update grade in Google Classroom
            $service->courses_courseWork_studentSubmissions->patch(
                $courseId,
                $courseWorkId,
                $submissionId,
                $submission,
                ['updateMask' => 'assignedGrade,draftGrade']
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar nota no Google Classroom', [
                'course_id' => $courseId,
                'coursework_id' => $courseWorkId,
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Download a Google Drive file by ID.
     */
    public function downloadDriveFile(User $user, string $fileId): ?string
    {
        try {
            $client = $this->getClientForUser($user);
            $driveService = new Drive($client);

            // Get file metadata to check name and extension
            $file = $driveService->files->get($fileId, ['fields' => 'name,fileExtension']);
            $filename = $file->getName();
            $extension = $file->getFileExtension() ?? pathinfo($filename, PATHINFO_EXTENSION);

            // Clean filename and ensure it has the correct extension
            $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
            $localFilename = uniqid().'_'.$safeName;
            if ($extension && ! str_ends_with($localFilename, '.'.$extension)) {
                $localFilename .= '.'.$extension;
            }
            $localPath = 'evaluations/submissions/'.$localFilename;

            // Fetch actual file content via media alt parameter
            $response = $driveService->files->get($fileId, ['alt' => 'media']);
            $content = $response->getBody()->getContents();

            // Store inside local public disk
            Storage::disk('public')->put($localPath, $content);

            return $localPath;
        } catch (\Exception $e) {
            Log::error('Erro ao baixar arquivo do Google Drive', [
                'file_id' => $fileId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
