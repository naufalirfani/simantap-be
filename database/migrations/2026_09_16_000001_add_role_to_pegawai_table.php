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
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('role')->nullable()->after('golongan');
        });

        // Backfill role from json->>'role' or json->>'statusPegawai' if available
        DB::table('pegawai')->whereNull('role')->update([
            'role' => DB::raw("COALESCE(json->>'role', json->>'statusPegawai')"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

