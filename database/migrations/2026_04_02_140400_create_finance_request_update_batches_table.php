<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_update_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_role')->default('client')->index();
            $table->string('status')->default('open')->index();
            $table->text('reason_en')->nullable();
            $table->text('reason_ar')->nullable();
            $table->timestamp('opened_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_request_id', 'status'], 'frub_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_update_batches');
    }
};
