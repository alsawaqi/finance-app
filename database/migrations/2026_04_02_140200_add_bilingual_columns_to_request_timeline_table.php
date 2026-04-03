<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_timeline', function (Blueprint $table) {
            $table->string('event_title_en')->nullable()->after('event_title');
            $table->string('event_title_ar')->nullable()->after('event_title_en');
            $table->text('event_description_en')->nullable()->after('event_description');
            $table->text('event_description_ar')->nullable()->after('event_description_en');
        });
    }

    public function down(): void
    {
        Schema::table('request_timeline', function (Blueprint $table) {
            $table->dropColumn([
                'event_title_en',
                'event_title_ar',
                'event_description_en',
                'event_description_ar',
            ]);
        });
    }
};
