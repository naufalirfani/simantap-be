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
        Schema::table('pegawai', function (Blueprint $table) {
            // Cache for riwayat diklat struktural from rw-diklat API
            $table->json('riwayat_diklat')->nullable()->after('riwayat_pengembangan_kompetensi');
            // Cache for riwayat sertifikasi from rw-sertifikasi API
            $table->json('riwayat_sertifikasi')->nullable()->after('riwayat_diklat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['riwayat_diklat', 'riwayat_sertifikasi']);
        });
    }
};
