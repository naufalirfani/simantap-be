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
        if (Schema::hasTable('peta_jabatan') && Schema::hasColumn('peta_jabatan', 'nama_pejabat')) {
            Schema::table('peta_jabatan', function (Blueprint $table) {
                $table->renameColumn('nama_pejabat', 'pejabat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('peta_jabatan') && Schema::hasColumn('peta_jabatan', 'pejabat')) {
            Schema::table('peta_jabatan', function (Blueprint $table) {
                $table->renameColumn('pejabat', 'nama_pejabat');
            });
        }
    }
};
