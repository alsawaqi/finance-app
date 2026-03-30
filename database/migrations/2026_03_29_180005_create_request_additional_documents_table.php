<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_additional_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('disk')->nullable()->default('public');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('uploaded_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['finance_request_id', 'status'], 'rad_req_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_additional_documents');
    }
};
