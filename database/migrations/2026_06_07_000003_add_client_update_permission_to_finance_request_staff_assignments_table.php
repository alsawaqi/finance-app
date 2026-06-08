<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_staff_assignments', function (Blueprint $table) {
            $table->boolean('can_request_client_updates')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_staff_assignments', function (Blueprint $table) {
            $table->dropColumn('can_request_client_updates');
        });
    }
};
