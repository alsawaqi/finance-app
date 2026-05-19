<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_staff_questions', function (Blueprint $table) {
            $table->foreignId('answered_by')
                ->nullable()
                ->after('answer_text')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_staff_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answered_by');
        });
    }
};
