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
        Schema::table('exam_evaluations', function (Blueprint $table) {
            $table->string('google_course_id')->nullable()->after('status');
            $table->string('google_coursework_id')->nullable()->after('google_course_id');
        });

        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->string('google_submission_id')->nullable()->after('status_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table) {
            $table->dropColumn(['google_course_id', 'google_coursework_id']);
        });

        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->dropColumn('google_submission_id');
        });
    }
};
