<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penilaian_sync_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('penilaian_sync_sessions', 'total_api_calls')) {
                $table->unsignedInteger('total_api_calls')->nullable()->after('total_batches');
            }
            if (!Schema::hasColumn('penilaian_sync_sessions', 'completed_api_calls')) {
                $table->unsignedInteger('completed_api_calls')->default(0)->after('total_api_calls');
            }
            if (!Schema::hasColumn('penilaian_sync_sessions', 'last_api_name')) {
                $table->string('last_api_name')->nullable()->after('completed_api_calls');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_sync_sessions', function (Blueprint $table) {
            $table->dropColumn(['total_api_calls', 'completed_api_calls', 'last_api_name']);
        });
    }
};
