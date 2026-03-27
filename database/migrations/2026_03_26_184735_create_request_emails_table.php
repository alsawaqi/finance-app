<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_emails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();

            $table->string('direction')->default('outbound')->index(); // outbound, inbound
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->longText('body')->nullable();

            $table->string('provider_message_id')->nullable()->index();
            $table->string('thread_key')->nullable()->index();

            $table->string('delivery_status')->nullable()->index(); // queued, sent, failed, received, opened, bounced

            $table->string('from_email')->nullable();
            $table->json('to_emails_json')->nullable();
            $table->json('cc_emails_json')->nullable();
            $table->json('bcc_emails_json')->nullable();

            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();

            $table->timestamps();   

            $table->index(['finance_request_id', 'direction'], 'rem_req_direction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_emails');
    }
};