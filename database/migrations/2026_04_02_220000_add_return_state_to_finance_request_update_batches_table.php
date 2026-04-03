<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_update_batches', function (Blueprint $table) {
            $table->string('return_status')->nullable()->after('status');
            $table->string('return_workflow_stage')->nullable()->after('return_status');
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_update_batches', function (Blueprint $table) {
            $table->dropColumn(['return_status', 'return_workflow_stage']);
        });
    }
};
