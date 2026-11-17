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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 5)->default('XOF');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->text('logo_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('logo_data'));
    }
};
