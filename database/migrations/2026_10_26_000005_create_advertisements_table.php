<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bibliothèque de publicités générées par l'IA (Module H : H-01 à H-05)
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30); // whatsapp, tiktok, sms, instagram
            $table->string('objective', 30); // nouveaute, promotion, destockage, rappel
            $table->string('tone', 30); // amical, premium, dynamique
            $table->json('generated_texts'); // 3 variantes rédigées (H-02)
            $table->json('generated_scripts')->nullable(); // Scripts vidéos TikTok / Reels (H-04)
            $table->json('visual_config')->nullable(); // Données visuel généré (H-03)
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'channel']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
