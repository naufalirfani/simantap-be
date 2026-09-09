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
            $table->string('institusi_penyelenggara')->nullable()->after('masa_berlaku_selesai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengajuan_penilaian', function (Blueprint $table) {
            $table->dropColumn('institusi_penyelenggara');
        });
    }
};
