<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_questions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->text('question_text');
            $table->string('question_type')->index(); // text, textarea, select, radio, checkbox, number, date, email, phone, currency
            $table->json('options_json')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->text('validation_rules')->nullable();
            $table->boolean('is_required')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_questions');
    }
};