<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catégories de produits (C-03)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 90);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'slug']);
            $table->index(['shop_id', 'display_order']);
        });

        // Produits (C-01, RG-10 prix en FCFA entiers)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->unsignedInteger('price'); // en FCFA (entier > 0)
            $table->unsignedInteger('compare_at_price')->nullable(); // prix barré
            $table->integer('stock')->default(0);
            $table->boolean('track_stock')->default(true); // true = quantité gérée, false = toujours disponible
            $table->unsignedSmallInteger('low_stock_threshold')->default(3); // Seuil d'alerte (par défaut 3 unités)
            $table->string('status', 20)->default('active'); // active | hidden | archived (RG-14)
            $table->string('sku', 50)->nullable(); // Référence interne facultative
            $table->json('attributes')->nullable(); // caractéristiques (taille, couleur...)
            $table->string('created_via', 20)->default('manual'); // manual | ai
            $table->unsignedTinyInteger('ai_confidence')->nullable(); // % confiance IA si généré
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'stock']);
        });

        // Association plusieurs produits <-> plusieurs catégories (1 à 3 catégories max, C-01)
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
        });

        // Images des produits (1 à 8 photos, C-01, C-06)
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // Chemin de stockage
            $table->string('thumbnail_path')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0); // 0 = photo principale
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // Mouvements et historique du stock (C-04)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_change'); // positif (+) ou négatif (-)
            $table->integer('new_stock');
            $table->string('reason', 30); // initial | manual_adjustment | order | cancellation
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
