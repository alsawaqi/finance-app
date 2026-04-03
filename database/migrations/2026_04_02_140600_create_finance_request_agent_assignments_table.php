<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_agent_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('unassigned_at')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_request_id', 'is_active'], 'fraa_request_active_idx');
            $table->index(['agent_id', 'is_active'], 'fraa_agent_active_idx');
            $table->index(['bank_id', 'is_active'], 'fraa_bank_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_agent_assignments');
    }
};
