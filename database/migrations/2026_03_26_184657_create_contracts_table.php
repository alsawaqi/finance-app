<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();

            $table->unsignedInteger('version_no')->default(1);
            $table->longText('contract_content');
            $table->string('contract_pdf_path')->nullable();

            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at')->nullable()->index();

            $table->timestamp('admin_signed_at')->nullable();
            $table->foreignId('admin_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_signature_path')->nullable();

            $table->timestamp('client_signed_at')->nullable();
            $table->foreignId('client_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_signature_path')->nullable();

            $table->string('status')->default('generated')->index(); // generated, admin_signed, client_signed, fully_signed, superseded, voided
            $table->boolean('is_current')->default(true)->index();

            $table->timestamps();

            $table->index(['finance_request_id', 'version_no'], 'contracts_req_version_idx');
            $table->index(['finance_request_id', 'is_current'], 'contracts_req_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};