<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syarat_suksesi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('jabatan_id');
            $table->json('syarat');
            $table->timestamps();

            $table->index('jabatan_id');
            $table->foreign('jabatan_id')->references('id')->on('peta_jabatan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syarat_suksesi');
    }
};
