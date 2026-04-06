<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_source', 30)->default('generated')->after('status')->index();
            $table->boolean('client_signature_skipped')->default(false)->after('contract_source');
            $table->boolean('requires_commercial_registration')->default(false)->after('client_signature_skipped');

            $table->string('admin_uploaded_contract_name')->nullable()->after('requires_commercial_registration');
            $table->string('admin_uploaded_contract_path')->nullable()->after('admin_uploaded_contract_name');
            $table->string('admin_uploaded_contract_mime_type')->nullable()->after('admin_uploaded_contract_path');
            $table->unsignedBigInteger('admin_uploaded_contract_size')->nullable()->after('admin_uploaded_contract_mime_type');
            $table->timestamp('admin_uploaded_contract_at')->nullable()->after('admin_uploaded_contract_size');

            $table->string('client_commercial_contract_name')->nullable()->after('admin_uploaded_contract_at');
            $table->string('client_commercial_contract_path')->nullable()->after('client_commercial_contract_name');
            $table->string('client_commercial_contract_mime_type')->nullable()->after('client_commercial_contract_path');
            $table->unsignedBigInteger('client_commercial_contract_size')->nullable()->after('client_commercial_contract_mime_type');
            $table->timestamp('client_commercial_uploaded_at')->nullable()->after('client_commercial_contract_size');

            $table->string('admin_commercial_contract_name')->nullable()->after('client_commercial_uploaded_at');
            $table->string('admin_commercial_contract_path')->nullable()->after('admin_commercial_contract_name');
            $table->string('admin_commercial_contract_mime_type')->nullable()->after('admin_commercial_contract_path');
            $table->unsignedBigInteger('admin_commercial_contract_size')->nullable()->after('admin_commercial_contract_mime_type');
            $table->timestamp('admin_commercial_uploaded_at')->nullable()->after('admin_commercial_contract_size');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['contract_source']);
            $table->dropColumn([
                'contract_source',
                'client_signature_skipped',
                'requires_commercial_registration',
                'admin_uploaded_contract_name',
                'admin_uploaded_contract_path',
                'admin_uploaded_contract_mime_type',
                'admin_uploaded_contract_size',
                'admin_uploaded_contract_at',
                'client_commercial_contract_name',
                'client_commercial_contract_path',
                'client_commercial_contract_mime_type',
                'client_commercial_contract_size',
                'client_commercial_uploaded_at',
                'admin_commercial_contract_name',
                'admin_commercial_contract_path',
                'admin_commercial_contract_mime_type',
                'admin_commercial_contract_size',
                'admin_commercial_uploaded_at',
            ]);
        });
    }
};
