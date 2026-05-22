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
        Schema::table('exams', function (Blueprint $table) {
            $table->json('content_json')->nullable()->after('description');
            $table->string('file_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
            $table->unsignedBigInteger('file_size')->nullable()->change();
        });

        // Migrate existing JSON files to the database
        $exams = \Illuminate\Support\Facades\DB::table('exams')->get();
        foreach ($exams as $exam) {
            if ($exam->mime_type === 'application/json' && !empty($exam->file_path)) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($exam->file_path)) {
                    $rawContent = \Illuminate\Support\Facades\Storage::disk('public')->get($exam->file_path);
                    $json = json_decode($rawContent, true);
                    if (is_array($json)) {
                        \Illuminate\Support\Facades\DB::table('exams')->where('id', $exam->id)->update([
                            'content_json' => json_encode($json)
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('content_json');
            // Reverting to non-nullable might fail if there are null values, 
            // but we add it for the sake of reversing the migration schema
            $table->string('file_path')->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
            $table->unsignedBigInteger('file_size')->nullable(false)->change();
        });
    }
};
