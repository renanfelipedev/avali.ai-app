<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('student_name')->nullable();
            $table->string('student_file_path');
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->json('feedback_data')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, error
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_submissions');
    }
};
