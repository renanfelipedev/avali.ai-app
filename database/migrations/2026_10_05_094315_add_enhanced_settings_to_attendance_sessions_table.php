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
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable()->after('duration_hours');
            $table->boolean('require_pin')->default(false)->after('radius_meters');
            $table->string('pin_code', 10)->nullable()->after('require_pin');
            $table->boolean('only_enrolled')->default(false)->after('pin_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'require_pin', 'pin_code', 'only_enrolled']);
        });
    }
};
