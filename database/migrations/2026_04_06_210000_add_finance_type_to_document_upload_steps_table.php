<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_upload_steps', 'finance_type')) {
            Schema::table('document_upload_steps', function (Blueprint $table): void {
                $table->string('finance_type', 20)
                    ->default('all')
                    ->after('name')
                    ->index();
            });
        }

        DB::table('document_upload_steps')
            ->whereNull('finance_type')
            ->update(['finance_type' => 'all']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_upload_steps', 'finance_type')) {
            Schema::table('document_upload_steps', function (Blueprint $table): void {
                $table->dropIndex(['finance_type']);
                $table->dropColumn('finance_type');
            });
        }
    }
};
