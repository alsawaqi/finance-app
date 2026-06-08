<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_emails', function (Blueprint $table) {
            $table->foreignId('email_template_id')
                ->nullable()
                ->after('sent_by')
                ->constrained('request_email_templates')
                ->nullOnDelete();
            $table->json('email_template_values_json')->nullable()->after('body');
            $table->json('email_template_snapshot_json')->nullable()->after('email_template_values_json');
        });
    }

    public function down(): void
    {
        Schema::table('request_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_template_id');
            $table->dropColumn([
                'email_template_values_json',
                'email_template_snapshot_json',
            ]);
        });
    }
};
