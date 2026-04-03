<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_staff_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question_text_en');
            $table->text('question_text_ar')->nullable();
            $table->longText('answer_text')->nullable();
            $table->string('status')->default('pending')->index();
            $table->boolean('is_required')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('metadata_json')->nullable();
            $table->timestamp('asked_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_request_id', 'status'], 'frsq_request_status_idx');
            $table->index(['assigned_to', 'status'], 'frsq_assigned_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_staff_questions');
    }
};
