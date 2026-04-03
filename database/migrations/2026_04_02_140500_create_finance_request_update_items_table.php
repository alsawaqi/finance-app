<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_update_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('update_batch_id')->constrained('finance_request_update_batches')->cascadeOnDelete();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('item_type')->index();
            $table->string('field_key')->nullable()->index();
            $table->foreignId('question_id')->nullable()->constrained('request_questions')->nullOnDelete();
            $table->string('related_model_type')->nullable();
            $table->unsignedBigInteger('related_model_id')->nullable();
            $table->string('label_en')->nullable();
            $table->string('label_ar')->nullable();
            $table->text('instruction_en')->nullable();
            $table->text('instruction_ar')->nullable();
            $table->string('editable_by')->default('client')->index();
            $table->string('status')->default('pending')->index();
            $table->boolean('is_required')->default(true)->index();
            $table->json('old_value_json')->nullable();
            $table->json('new_value_json')->nullable();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('fulfilled_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_request_id', 'status'], 'frui_request_status_idx');
            $table->index(['update_batch_id', 'status'], 'frui_batch_status_idx');
            $table->index(['related_model_type', 'related_model_id'], 'frui_related_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_update_items');
    }
};
