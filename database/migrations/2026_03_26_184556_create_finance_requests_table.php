<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // client
            $table->foreignId('primary_staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('draft')->index(); // draft, submitted, active, on_hold, rejected, completed, cancelled
            $table->string('workflow_stage')->default('questionnaire')->index();
            $table->string('priority')->default('normal')->index(); // low, normal, high, urgent

            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('latest_assignment_at')->nullable();
            $table->timestamp('latest_activity_at')->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['primary_staff_id', 'status']);
            $table->index(['status', 'workflow_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_requests');
    }
};