<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_shareholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->string('shareholder_name');
            $table->string('id_file_name');
            $table->string('id_file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['finance_request_id', 'sort_order'], 'frs_req_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_request_shareholders');
    }
};
