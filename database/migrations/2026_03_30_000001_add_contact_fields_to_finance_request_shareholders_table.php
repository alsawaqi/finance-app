<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_shareholders', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_request_shareholders', 'phone_country_code')) {
                $table->string('phone_country_code', 10)->nullable()->after('shareholder_name');
            }

            if (! Schema::hasColumn('finance_request_shareholders', 'phone_number')) {
                $table->string('phone_number', 30)->nullable()->after('phone_country_code');
            }

            if (! Schema::hasColumn('finance_request_shareholders', 'id_number')) {
                $table->string('id_number', 100)->nullable()->after('phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_shareholders', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('finance_request_shareholders', 'phone_country_code')) {
                $drops[] = 'phone_country_code';
            }

            if (Schema::hasColumn('finance_request_shareholders', 'phone_number')) {
                $drops[] = 'phone_number';
            }

            if (Schema::hasColumn('finance_request_shareholders', 'id_number')) {
                $drops[] = 'id_number';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};