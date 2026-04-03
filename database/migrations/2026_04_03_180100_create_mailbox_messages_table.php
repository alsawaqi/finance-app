<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('folder_name', 100)->default('INBOX')->index();
            $table->string('provider_uid', 100);
            $table->string('message_id', 191)->nullable()->index();
            $table->string('in_reply_to', 191)->nullable();
            $table->text('references_header')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to_emails_json')->nullable();
            $table->json('cc_emails_json')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('has_attachments')->default(false)->index();
            $table->timestamps();

            $table->unique(['user_id', 'folder_name', 'provider_uid'], 'mb_msg_user_folder_uid_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_messages');
    }
};