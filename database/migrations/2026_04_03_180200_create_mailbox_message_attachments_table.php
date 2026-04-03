<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_message_id')->constrained('mailbox_messages')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk', 50)->default('local');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('content_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_message_attachments');
    }
};