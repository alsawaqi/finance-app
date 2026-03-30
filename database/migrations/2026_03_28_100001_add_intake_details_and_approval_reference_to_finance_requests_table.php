<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->string('approval_reference_number', 50)->nullable()->unique()->after('reference_number');
        
        });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->dropUnique(['approval_reference_number']);
            $table->dropColumn(['approval_reference_number']);
        });
    }
};
