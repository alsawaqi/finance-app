<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('request_questions')->cascadeOnDelete();

            $table->json('answer_value_json')->nullable();
            $table->text('answer_text')->nullable();

            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['finance_request_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_answers');
    }
};