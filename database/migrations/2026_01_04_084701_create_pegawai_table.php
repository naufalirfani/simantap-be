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
        Schema::create('pegawai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pegawai_id')->unique();
            $table->string('nip')->index();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('unit_organisasi_name')->nullable();
            $table->string('jabatan_name')->nullable();
            $table->string('jenis_jabatan')->nullable();
            $table->string('golongan')->nullable();
            $table->jsonb('json')->nullable();
            $table->text('avatar')->nullable(); // base64 image
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
