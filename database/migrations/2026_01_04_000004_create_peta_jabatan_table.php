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
        Schema::create('peta_jabatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('nama_jabatan');
            $table->string('slug')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->integer('level')->default(0);
            $table->integer('order_index')->default(0);
            $table->integer('bezetting')->default(0);
            $table->integer('kebutuhan_pegawai')->default(0);
            $table->boolean('is_pusat')->default(false);
            $table->string('jenis_jabatan')->nullable();
            $table->uuid('jabatan_id')->nullable();
            $table->string('kelas_jabatan')->nullable();
            $table->json('nama_pejabat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peta_jabatan');
    }
};
