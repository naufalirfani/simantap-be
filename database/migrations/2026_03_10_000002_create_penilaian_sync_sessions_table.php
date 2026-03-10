<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_sync_sessions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('dispatched_at');
            $table->unsignedInteger('total_nips')->nullable();
            $table->unsignedInteger('total_batches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_sync_sessions');
    }
};
