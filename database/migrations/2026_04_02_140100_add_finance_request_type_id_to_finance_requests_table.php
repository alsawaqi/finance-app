<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('finance_requests', 'finance_request_type_id')) {
            Schema::table('finance_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('finance_request_type_id')
                    ->nullable()
                    ->after('primary_staff_id');

                $table->index(
                    'finance_request_type_id',
                    'finance_requests_finance_request_type_id_idx'
                );

                $table->foreign(
                    'finance_request_type_id',
                    'finance_requests_finance_request_type_id_fk'
                )->references('id')
                    ->on('finance_request_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_requests', 'finance_request_type_id')) {
            Schema::table('finance_requests', function (Blueprint $table) {
                $table->dropForeign('finance_requests_finance_request_type_id_fk');
                $table->dropIndex('finance_requests_finance_request_type_id_idx');
                $table->dropColumn('finance_request_type_id');
            });
        }
    }
};
