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
        Schema::table('syarat_suksesi', function (Blueprint $table) {
            $table->boolean('gunakan_kompetensi_teknis')->default(false)->after('syarat');
            $table->boolean('sesuai_rumpun_jabatan')->default(false)->after('gunakan_kompetensi_teknis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('syarat_suksesi', function (Blueprint $table) {
            $table->dropColumn(['gunakan_kompetensi_teknis', 'sesuai_rumpun_jabatan']);
        });
    }
};

