<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE MATERIALIZED VIEW statistik AS
            SELECT 'total_pegawai' AS key, COUNT(*)::bigint AS value FROM pegawai
            UNION ALL
            SELECT 'total_struktural' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jenis_jabatan = 'Struktural'
            UNION ALL
            SELECT 'total_fungsional' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jenis_jabatan = 'Fungsional'
            UNION ALL
            SELECT 'total_pelaksana' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jenis_jabatan = 'Pelaksana'
            UNION ALL
            SELECT 'total_laki_laki' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'jenisKelamin' = 'M'
            UNION ALL
            SELECT 'total_perempuan' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'jenisKelamin' = 'F'
            UNION ALL
            SELECT 'total_jabatan_pimpinan_tinggi_madya' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'eselonLevel' = '1'
            UNION ALL
            SELECT 'total_jabatan_pimpinan_tinggi_pratama' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'eselonLevel' = '2'
            UNION ALL
            SELECT 'total_jabatan_administrator' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'eselonLevel' = '3'
            UNION ALL
            SELECT 'total_jabatan_pengawas' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE json->>'eselonLevel' = '4'
            UNION ALL
            SELECT 'total_fungsional_utama' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Ahli Utama'
            UNION ALL
            SELECT 'total_fungsional_madya' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Ahli Madya'
            UNION ALL
            SELECT 'total_fungsional_muda' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Ahli Muda'
            UNION ALL
            SELECT 'total_fungsional_pertama' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Ahli Pertama'
            UNION ALL
            SELECT 'total_fungsional_penyelia' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Penyelia'
            UNION ALL
            SELECT 'total_fungsional_mahir' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Mahir'
            UNION ALL
            SELECT 'total_fungsional_terampil' AS key, COUNT(*)::bigint AS value FROM pegawai WHERE jabatan_name ~ 'Terampil'
        ");

        // Create index on key column for faster lookups
        DB::statement('CREATE UNIQUE INDEX statistik_key_idx ON statistik (key)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS statistik');
    }
};
