<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_agent_assignment_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('finance_request_agent_assignment_id');
            $table->unsignedBigInteger('finance_request_id');

            $table->string('document_type')->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->string('document_key')->index();
            $table->string('group_label')->nullable();
            $table->string('document_label')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('finance_request_agent_assignment_id', 'fraad_assignment_fk')
                ->references('id')
                ->on('finance_request_agent_assignments')
                ->cascadeOnDelete();

            $table->foreign('finance_request_id', 'fraad_request_fk')
                ->references('id')
                ->on('finance_requests')
                ->cascadeOnDelete();

            $table->index(['finance_request_id', 'document_type'], 'fraad_request_type_idx');
            $table->index(['finance_request_agent_assignment_id', 'sort_order'], 'fraad_assignment_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_agent_assignment_documents');
    }
};