<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->json('intake_details_json')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->dropColumn('intake_details_json');
        });
    }
};
