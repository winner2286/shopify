<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sessions de conversations IA de la boutique
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('context_summary')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'updated_at']);
        });

        // 2. Messages de la conversation (User, Assistant, Tool, System)
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 20); // user, assistant, system, tool
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id', 64)->nullable();
            $table->string('tool_name', 60)->nullable();
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->unsignedInteger('cost_fcfa')->default(0);
            $table->json('action_required')->nullable(); // Actions sensibles en attente d'approbation
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ai_conversation_id', 'created_at']);
        });

        // 3. Moteur de règles d'automatisation IA
        Schema::create('ai_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50); // low_stock, pending_cod_order, daily_summary, abandoned_cart
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('trigger_count')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_automations');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
