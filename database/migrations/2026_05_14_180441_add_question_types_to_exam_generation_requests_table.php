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
        Schema::table('exam_generation_requests', function (Blueprint $table) {
            $table->integer('objective_count')->default(0)->after('questions_count');
            $table->integer('discursive_count')->default(0)->after('objective_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_generation_requests', function (Blueprint $table) {
            $table->dropColumn(['objective_count', 'discursive_count']);
        });
    }
};
