<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_staff_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_staff_question_template_id')
                ->nullable()
                ->after('finance_request_id');

            $table->string('question_code')->nullable()->after('assigned_to');
            $table->string('question_type')->default('text')->after('question_text_ar');
            $table->json('options_json')->nullable()->after('question_type');
            $table->string('placeholder_en')->nullable()->after('options_json');
            $table->string('placeholder_ar')->nullable()->after('placeholder_en');
            $table->text('help_text_en')->nullable()->after('placeholder_ar');
            $table->text('help_text_ar')->nullable()->after('help_text_en');
            $table->text('validation_rules')->nullable()->after('help_text_ar');

            $table->unique(
                ['finance_request_id', 'finance_staff_question_template_id'],
                'frsq_request_template_unique'
            );

            $table->foreign(
                'finance_staff_question_template_id',
                'frsq_template_fk'
            )->references('id')
                ->on('finance_staff_question_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_staff_questions', function (Blueprint $table) {
            $table->dropForeign('frsq_template_fk');
            $table->dropUnique('frsq_request_template_unique');
            $table->dropColumn('finance_staff_question_template_id');

            $table->dropColumn([
                'question_code',
                'question_type',
                'options_json',
                'placeholder_en',
                'placeholder_ar',
                'help_text_en',
                'help_text_ar',
                'validation_rules',
            ]);
        });
    }
};