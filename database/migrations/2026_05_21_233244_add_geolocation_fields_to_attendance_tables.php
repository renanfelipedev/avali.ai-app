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
            $table->boolean('require_geolocation')->default(false)->after('is_active');
            $table->decimal('latitude', 10, 8)->nullable()->after('require_geolocation');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->integer('radius_meters')->nullable()->after('longitude');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->integer('distance_meters')->nullable()->after('user_agent');
            $table->boolean('is_valid_location')->nullable()->after('distance_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['require_geolocation', 'latitude', 'longitude', 'radius_meters']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['distance_meters', 'is_valid_location']);
        });
    }
};
