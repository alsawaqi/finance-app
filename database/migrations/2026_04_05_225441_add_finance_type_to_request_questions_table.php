<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('request_questions', 'finance_type')) {
            Schema::table('request_questions', function (Blueprint $table) {
                $table->string('finance_type', 20)
                    ->default('all')
                    ->after('question_type')
                    ->index();
            });
        }

        DB::table('request_questions')
            ->whereNull('finance_type')
            ->update(['finance_type' => 'all']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('request_questions', 'finance_type')) {
            Schema::table('request_questions', function (Blueprint $table) {
                $table->dropIndex(['finance_type']);
                $table->dropColumn('finance_type');
            });
        }
    }
};
