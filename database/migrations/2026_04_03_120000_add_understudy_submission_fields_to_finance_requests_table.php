<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->string('understudy_status')->nullable()->after('workflow_stage');
            $table->text('understudy_note')->nullable()->after('understudy_status');
            $table->foreignId('understudy_submitted_by')->nullable()->after('understudy_note')->constrained('users')->nullOnDelete();
            $table->timestamp('understudy_submitted_at')->nullable()->after('understudy_submitted_by');
            $table->foreignId('understudy_reviewed_by')->nullable()->after('understudy_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('understudy_reviewed_at')->nullable()->after('understudy_reviewed_by');
            $table->text('understudy_review_note')->nullable()->after('understudy_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('understudy_submitted_by');
            $table->dropConstrainedForeignId('understudy_reviewed_by');
            $table->dropColumn([
                'understudy_status',
                'understudy_note',
                'understudy_submitted_at',
                'understudy_reviewed_at',
                'understudy_review_note',
            ]);
        });
    }
};
