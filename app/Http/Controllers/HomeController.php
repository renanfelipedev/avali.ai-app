<?php

namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Models\Exam;
use App\Models\ExamEvaluation;
use App\Models\User;

class HomeController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_exams' => Exam::count(),
                'total_evaluations' => ExamEvaluation::count(),
                'ai_interactions' => AiLog::count(),
                'recent_exams' => Exam::with('user')->latest()->take(5)->get(),
            ];
        } else {
            $stats = [
                'total_classrooms' => $user->classrooms()->count(),
                'total_students' => $user->students()->count(),
                'total_exams' => $user->exams()->count(),
                'total_evaluations' => $user->examEvaluations()->count(),
                'total_attendance' => $user->attendanceSessions()->count(),
                'recent_exams' => $user->exams()->latest()->take(5)->get(),
            ];
        }

        return view('home', compact('stats'));
    }
}
