<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_email_agents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_email_id')->constrained('request_emails')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['request_email_id', 'agent_id']);
            $table->index(['request_email_id', 'agent_id'], 'rema_req_email_agent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_email_agents');
    }
};