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
            $table->string('type')->default('exam')->after('title');
        });

        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('feedback_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
