<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_staff_assignments', function (Blueprint $table) {
            $table->foreignId('unassigned_by')
                ->nullable()
                ->after('assigned_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_staff_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unassigned_by');
        });
    }
};
