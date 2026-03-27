<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_staff_assignments', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
        
            $table->string('assignment_role')->nullable()->index();
            $table->text('notes')->nullable();
        
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
        
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('unassigned_at')->nullable()->index();
        
            $table->timestamps();
        
            $table->index(['finance_request_id', 'is_active'], 'frsa_req_active_idx');
            $table->index(['staff_id', 'is_active'], 'frsa_staff_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_staff_assignments');
    }
};