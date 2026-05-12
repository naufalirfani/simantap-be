<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pegawai_id')->index();
            $table->uuid('subindikator_id')->index();
            $table->uuid('instrumen_id')->index();
            $table->string('bukti_dukung');
            $table->string('original_filename')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->string('status')->index();
            $table->date('tanggal_sk')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
            $table->foreign('subindikator_id')->references('id')->on('subindikators')->onDelete('cascade');
            $table->foreign('instrumen_id')->references('id')->on('instrumens')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_penilaian');
    }
};
