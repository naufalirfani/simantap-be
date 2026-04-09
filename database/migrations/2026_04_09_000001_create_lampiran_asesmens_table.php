<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampiran_asesmen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id')->index();
            $table->string('nama_asesmen');
            $table->string('file_path');
            $table->timestamps();

            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_asesmen');
    }
};
