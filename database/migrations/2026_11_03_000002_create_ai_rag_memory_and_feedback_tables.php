<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Chunks de connaissances spécialisées de la plateforme Zanika (RAG)
        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60); // navigation_ui, catalog_rules, orders_cod, payments_feexpay, marketing_ads, africa_playbook, faq
            $table->string('title', 180);
            $table->string('slug', 180)->unique();
            $table->text('content');
            $table->json('keywords')->nullable(); // Mots-clés normalisés pour recherche ultra-rapide
            $table->string('target_routes', 255)->nullable(); // ex: "products.create", "orders.index"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('is_active');
        });

        // 2. Mémoire commerciale long terme par boutique (Strictement isolée par shop_id)
        Schema::create('ai_shop_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50); // merchant_preference, business_profile, commercial_goal, operational_habit, past_recommendation
            $table->string('memory_key', 100);
            $table->text('memory_value');
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->string('source', 40)->default('conversation_extraction'); // conversation_extraction, manual_input, system_detected
            $table->timestamp('last_recalled_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'memory_key']);
            $table->index(['shop_id', 'category']);
        });

        // 3. Retours et évaluations utilisateurs pour l'amélioration continue
        Schema::create('ai_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rating', 25); // helpful, incorrect, incomplete, irrelevant
            $table->text('comment')->nullable();
            $table->string('category', 60)->nullable(); // platform_knowledge, order_stock, marketing, conversation_tone, hallucination
            $table->boolean('admin_reviewed')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'rating']);
            $table->index(['admin_reviewed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedbacks');
        Schema::dropIfExists('ai_shop_memories');
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
