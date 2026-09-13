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
        Schema::create('suksesor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('peta_jabatan_id')->unique();
            $table->uuid('pegawai_id')->unique();
            $table->timestamps();

            $table->foreign('peta_jabatan_id')
                ->references('id')
                ->on('peta_jabatan')
                ->onDelete('cascade');

            $table->foreign('pegawai_id')
                ->references('id')
                ->on('pegawai')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suksesor');
    }
};

