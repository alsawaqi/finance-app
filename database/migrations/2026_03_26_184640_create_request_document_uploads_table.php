<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_document_uploads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('document_upload_step_id')->constrained('document_upload_steps')->cascadeOnDelete();

            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('status')->default('uploaded')->index(); // pending, uploaded, approved, rejected
            $table->text('rejection_reason')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('uploaded_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();

            $table->timestamps();

            $table->index(['finance_request_id', 'document_upload_step_id'], 'rdup_req_step_idx');
            $table->index(['finance_request_id', 'status'], 'rdup_req_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_document_uploads');
    }
};