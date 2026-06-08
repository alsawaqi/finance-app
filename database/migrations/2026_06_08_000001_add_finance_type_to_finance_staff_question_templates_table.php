<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('finance_staff_question_templates', 'finance_type')) {
            Schema::table('finance_staff_question_templates', function (Blueprint $table) {
                $table->string('finance_type', 20)
                    ->default('all')
                    ->after('question_type')
                    ->index();
            });
        }

        DB::table('finance_staff_question_templates')
            ->whereNull('finance_type')
            ->update(['finance_type' => 'all']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_staff_question_templates', 'finance_type')) {
            Schema::table('finance_staff_question_templates', function (Blueprint $table) {
                $table->dropIndex(['finance_type']);
                $table->dropColumn('finance_type');
            });
        }
    }
};
