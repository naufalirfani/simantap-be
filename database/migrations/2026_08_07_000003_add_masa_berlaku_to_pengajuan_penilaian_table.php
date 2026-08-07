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
        Schema::table('pengajuan_penilaian', function (Blueprint $table) {
            $table->date('masa_berlaku_mulai')->nullable()->after('tanggal_sk');
            $table->date('masa_berlaku_selesai')->nullable()->after('masa_berlaku_mulai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengajuan_penilaian', function (Blueprint $table) {
            $table->dropColumn(['masa_berlaku_mulai', 'masa_berlaku_selesai']);
        });
    }
};
