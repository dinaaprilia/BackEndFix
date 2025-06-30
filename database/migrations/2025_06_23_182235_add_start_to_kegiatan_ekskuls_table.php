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
    Schema::table('kegiatan_ekskuls', function (Blueprint $table) {
        $table->date('start')->nullable()->after('date');
    });
}

public function down(): void
{
    Schema::table('kegiatan_ekskuls', function (Blueprint $table) {
        $table->dropColumn('start');
    });
}

};
