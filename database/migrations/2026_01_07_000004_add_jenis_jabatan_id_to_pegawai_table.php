<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->uuid('jenis_jabatan_id')->nullable()->after('jenis_jabatan');
            $table->index('jenis_jabatan_id');
            $table->foreign('jenis_jabatan_id')->references('id')->on('jenis_jabatan')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropForeign(['jenis_jabatan_id']);
            $table->dropIndex(['jenis_jabatan_id']);
            $table->dropColumn('jenis_jabatan_id');
        });
    }
};
