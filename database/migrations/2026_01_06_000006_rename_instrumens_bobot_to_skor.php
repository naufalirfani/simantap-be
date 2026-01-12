<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameInstrumensBobotToSkor extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('instrumens') && Schema::hasColumn('instrumens', 'bobot')) {
            Schema::table('instrumens', function (Blueprint $table) {
                $table->renameColumn('bobot', 'skor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('instrumens') && Schema::hasColumn('instrumens', 'skor')) {
            Schema::table('instrumens', function (Blueprint $table) {
                $table->renameColumn('skor', 'bobot');
            });
        }
    }
}
