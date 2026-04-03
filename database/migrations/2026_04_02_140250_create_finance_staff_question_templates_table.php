<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_staff_question_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->text('question_text_en');
            $table->text('question_text_ar')->nullable();
            $table->string('question_type')->default('text')->index();
            $table->json('options_json')->nullable();
            $table->string('placeholder_en')->nullable();
            $table->string('placeholder_ar')->nullable();
            $table->text('help_text_en')->nullable();
            $table->text('help_text_ar')->nullable();
            $table->text('validation_rules')->nullable();
            $table->boolean('is_required')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_staff_question_templates');
    }
};