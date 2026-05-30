<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_upload_steps', function (Blueprint $table) {
            $table->unsignedInteger('max_file_size_kb')->nullable()->after('max_file_size_mb');
        });

        DB::table('document_upload_steps')
            ->whereNotNull('max_file_size_mb')
            ->update([
                'max_file_size_kb' => DB::raw('max_file_size_mb * 1024'),
            ]);
    }

    public function down(): void
    {
        Schema::table('document_upload_steps', function (Blueprint $table) {
            $table->dropColumn('max_file_size_kb');
        });
    }
};
