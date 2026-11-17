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
        if (Schema::hasTable('public_media')) {
            if (! Schema::hasColumns('public_media', ['shop_id', 'path', 'data', 'size_bytes', 'created_at', 'updated_at'])) {
                throw new RuntimeException('La table public_media existante est incomplète.');
            }

            return;
        }
        Schema::create('public_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('path')->unique();
            $table->longText('data');
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_media');
    }
};
