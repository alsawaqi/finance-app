<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('smtp_username')->nullable()->after('email');
            $table->text('smtp_password')->nullable()->after('password');
            $table->string('smtp_sender_name')->nullable()->after('name');
            $table->boolean('smtp_enabled')->default(false)->after('is_active');
            $table->timestamp('smtp_verified_at')->nullable()->after('smtp_enabled');
            $table->text('smtp_last_error')->nullable()->after('smtp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_username',
                'smtp_password',
                'smtp_sender_name',
                'smtp_enabled',
                'smtp_verified_at',
                'smtp_last_error',
            ]);
        });
    }
};
