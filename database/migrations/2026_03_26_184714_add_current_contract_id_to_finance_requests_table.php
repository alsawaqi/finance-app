<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->foreignId('current_contract_id')
                ->nullable()
                ->after('primary_staff_id')
                ->constrained('contracts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_contract_id');
        });
    }
};