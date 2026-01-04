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
        Schema::create('instrumens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('instrumen');
            $table->decimal('bobot', 5, 2)->default(0.00);
            $table->uuid('subindikator_id');
            $table->timestamps();

            $table->foreign('subindikator_id')
                  ->references('id')
                  ->on('subindikators')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrumens');
    }
};
