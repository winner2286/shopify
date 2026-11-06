<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('type', 20)->default('percentage'); // percentage, fixed
            $table->unsignedInteger('value'); // Ex: 15 (pour 15%) ou 2000 (pour 2000 FCFA)
            $table->unsignedInteger('min_order_amount')->nullable(); // Panier minimum requis
            $table->unsignedInteger('max_uses')->nullable(); // Nombre maximum d'utilisations
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'code']);
            $table->index(['shop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
