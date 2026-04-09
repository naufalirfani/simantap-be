<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampiran_asesmen', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('nama_asesmen');
            $table->bigInteger('file_size')->nullable()->after('file_path');
            $table->string('file_type')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('lampiran_asesmen', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'file_size', 'file_type']);
        });
    }
};
