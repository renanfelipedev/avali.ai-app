<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Gratuito (Starter)',
                'description' => 'Ideal para professores que estão começando e querem testar o poder da IA.',
                'price_cents' => 0,
                'features' => [
                    'monthly_exams_limit' => 5,
                    'monthly_submissions_limit' => 25,
                    'max_questions_per_exam' => 10,
                    'max_classrooms' => 2,
                    'max_students' => 50,
                    'allow_question_images' => false,
                    'allow_google_classroom' => false,
                    'allow_custom_print_profiles' => false,
                    'allow_docx_export' => false,
                    'allow_geofencing_attendance' => false,
                    'queue_priority' => 'default',
                    'ai_model' => 'gemini-3.5-flash-lite',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Professor Pro',
                'description' => 'Perfeito para professores com alta demanda de provas, tirinhas e correções com OCR.',
                'price_cents' => 3990, // R$ 39,90
                'features' => [
                    'monthly_exams_limit' => 40,
                    'monthly_submissions_limit' => 250,
                    'max_questions_per_exam' => 35,
                    'max_classrooms' => 10,
                    'max_students' => 400,
                    'allow_question_images' => true,
                    'allow_google_classroom' => false,
                    'allow_custom_print_profiles' => true,
                    'allow_docx_export' => true,
                    'allow_geofencing_attendance' => true,
                    'queue_priority' => 'high',
                    'ai_model' => 'gemini-3.8-flash',
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'school'],
            [
                'name' => 'Institucional (Escolas)',
                'description' => 'Solução definitiva para escolas, cursos e faculdades com Google Classroom integrado.',
                'price_cents' => 19900, // R$ 199,00
                'features' => [
                    'monthly_exams_limit' => -1, // Ilimitado
                    'monthly_submissions_limit' => 1500,
                    'max_questions_per_exam' => 50,
                    'max_classrooms' => -1, // Ilimitado
                    'max_students' => -1, // Ilimitado
                    'allow_question_images' => true,
                    'allow_google_classroom' => true,
                    'allow_custom_print_profiles' => true,
                    'allow_docx_export' => true,
                    'allow_geofencing_attendance' => true,
                    'queue_priority' => 'vip',
                    'ai_model' => 'gemini-3.8-flash',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ]
        );
    }
}
