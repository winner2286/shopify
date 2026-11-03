<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_proactive_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type', 60); // revenue_drop, cod_cancellation_surge, stock_critical, sleeping_products, stalled_deliveries, aov_drift, sales_opportunity
            $table->string('severity', 20); // critical, warning, opportunity, info
            $table->string('title', 180);
            $table->text('explanation');
            $table->json('suggested_action')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'is_dismissed', 'is_resolved']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_proactive_insights');
    }
};
