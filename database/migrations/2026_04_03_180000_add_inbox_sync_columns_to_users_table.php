<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'inbox_last_synced_at')) {
                $table->timestamp('inbox_last_synced_at')->nullable()->after('smtp_last_error');
            }

            if (!Schema::hasColumn('users', 'inbox_last_error')) {
                $table->text('inbox_last_error')->nullable()->after('inbox_last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('users', 'inbox_last_synced_at')) {
                $drops[] = 'inbox_last_synced_at';
            }

            if (Schema::hasColumn('users', 'inbox_last_error')) {
                $drops[] = 'inbox_last_error';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};