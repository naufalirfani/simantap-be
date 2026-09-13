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
            $table->unsignedInteger('minimal_usia')->nullable()->after('sesuai_rumpun_jabatan');
            $table->unsignedInteger('maksimal_usia')->nullable()->after('minimal_usia');
            $table->string('pangkat_golongan', 10)->nullable()->after('maksimal_usia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('syarat_suksesi', function (Blueprint $table) {
            $table->dropColumn(['minimal_usia', 'maksimal_usia', 'pangkat_golongan']);
        });
    }
};
