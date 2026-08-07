<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penilaian_sync_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('penilaian_sync_sessions', 'status')) {
                $table->string('status')->default('running')->after('last_api_name');
            }
            if (!Schema::hasColumn('penilaian_sync_sessions', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_sync_sessions', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
