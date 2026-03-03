<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subindikators', function (Blueprint $table) {
            // Flag for subindikators that can be auto-synced from external APIs.
            // Currently supported: Tingkat Pendidikan Formal, Penilaian Kerja (SKP)
            $table->boolean('auto_sync')->default(false)->after('isactive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subindikators', function (Blueprint $table) {
            $table->dropColumn('auto_sync');
        });
    }
};
