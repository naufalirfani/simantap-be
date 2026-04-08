<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_asesmen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_asesmen')->index();
            $table->uuid('pegawai_id');
            $table->json('data_asesmen');
            $table->timestamp('created_at')->useCurrent();

            $table->index('pegawai_id');
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_asesmen');
    }
};
