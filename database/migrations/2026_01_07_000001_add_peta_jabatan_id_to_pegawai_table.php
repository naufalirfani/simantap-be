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
            // use UUID type to match peta_jabatan.id (uuid)
            $table->uuid('peta_jabatan_id')->nullable()->default(null)->index();

            try {
                $table->foreign('peta_jabatan_id')->references('id')->on('peta_jabatan')->nullOnDelete();
            } catch (\Exception $e) {
                // ignore FK creation errors (some environments may restrict FK alterations)
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            // drop foreign if exists
            try {
                $table->dropForeign(['peta_jabatan_id']);
            } catch (\Exception $e) {
                // ignore
            }

            $table->dropIndex(['peta_jabatan_id']);
            $table->dropColumn('peta_jabatan_id');
        });
    }
};
