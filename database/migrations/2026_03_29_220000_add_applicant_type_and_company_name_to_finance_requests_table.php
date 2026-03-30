<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_requests', 'applicant_type')) {
                $table->string('applicant_type', 30)
                    ->default('individual')
                    ->after('current_contract_id')
                    ->index();
            }

            if (! Schema::hasColumn('finance_requests', 'company_name')) {
                $table->string('company_name')
                    ->nullable()
                    ->after('applicant_type')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('finance_requests', 'company_name')) {
                $table->dropColumn('company_name');
            }

            if (Schema::hasColumn('finance_requests', 'applicant_type')) {
                $table->dropColumn('applicant_type');
            }
        });
    }
};
