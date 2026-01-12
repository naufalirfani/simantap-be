<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaians', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('pegawai_id');
            $table->json('penilaian');
            $table->timestamps();

            $table->index('pegawai_id');
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaians');
    }
};
