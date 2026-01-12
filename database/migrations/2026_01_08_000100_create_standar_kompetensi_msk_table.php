<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standar_kompetensi_msk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jenis_jabatan_id');
            $table->uuid('subindikator_id');
            $table->unsignedTinyInteger('standar');
            $table->timestamps();

            $table->index('jenis_jabatan_id');
            $table->index('subindikator_id');

            $table->foreign('jenis_jabatan_id')->references('id')->on('jenis_jabatan')->onDelete('cascade');
            $table->foreign('subindikator_id')->references('id')->on('subindikators')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standar_kompetensi_msk');
    }
};
