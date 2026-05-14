<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Exam;
use App\Models\ExamEvaluation;
use App\Models\AiLog;
use Illuminate\Support\Facades\Cache;
use Gemini\Laravel\Facades\Gemini;

class HomeController extends Controller
{
    public function __invoke()
    {
        $stats = [];

        if (auth()->user()->isAdmin()) {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_exams' => Exam::count(),
                'total_evaluations' => ExamEvaluation::count(),
                'ai_interactions' => AiLog::count(),
                'recent_exams' => Exam::with('user')->latest()->take(5)->get(),
            ];
        }

        return view('home', compact('stats'));
    }
}
