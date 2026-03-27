<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_request_id')->constrained('finance_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('request_comments')->nullOnDelete();

            $table->text('comment_text');
            $table->string('visibility')->default('internal')->index(); // admin_only, internal, client_visible

            $table->timestamps();

            $table->index(['finance_request_id', 'visibility'], 'rc_req_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_comments');
    }
};