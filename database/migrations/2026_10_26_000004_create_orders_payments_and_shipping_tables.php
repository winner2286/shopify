<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Zones et tarifs de livraison (Module G - G-01)
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100); // ex: Lomé Intra-muros, Grand Lomé, Intérieur du Togo
            $table->unsignedInteger('price')->default(0); // Tarif en FCFA
            $table->unsignedInteger('free_shipping_threshold')->nullable(); // Gratuit au-dessus de X FCFA
            $table->string('delivery_estimate', 80)->default('Livré en 24h');
            $table->boolean('is_pickup')->default(false); // Option retrait en boutique
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();

            $table->index(['shop_id', 'is_active']);
        });

        // 2. Fichier clients (Module E - E-04) - strictement isolé par boutique (RG-02)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30);
            $table->string('email', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedInteger('total_orders_count')->default(0);
            $table->unsignedInteger('total_spent_fcfa')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'phone']);
            $table->index(['shop_id', 'email']);
        });

        // 3. Commandes clients (Module E & D-05)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 32)->unique(); // ex: CMD-20261026-0001
            
            // Coordonnées client au moment de l'achat
            $table->string('customer_name', 100);
            $table->string('customer_phone', 30);
            $table->string('customer_email', 100)->nullable();

            // Livraison
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete();
            $table->string('delivery_zone_name', 100);
            $table->text('delivery_address');
            $table->text('delivery_notes')->nullable();

            // Montants en FCFA (RG-10)
            $table->unsignedInteger('subtotal'); // Total des articles
            $table->unsignedInteger('shipping_cost')->default(0); // Frais de port
            $table->unsignedInteger('total'); // Montant total final

            // Paiement
            $table->string('payment_method', 30)->default('cash_on_delivery'); // cash_on_delivery, tmoney, flooz, card
            $table->string('payment_status', 30)->default('pending'); // pending, paid, failed, refunded
            $table->timestamp('paid_at')->nullable();

            // Statut de la commande (CDC Section 6)
            $table->string('status', 30)->default('pending'); // pending, confirmed, shipped, delivered, cancelled
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'created_at']);
        });

        // 4. Lignes de commandes (articles achetés)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_title', 150);
            $table->unsignedInteger('unit_price'); // en FCFA
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('total_price'); // quantity * unit_price
            $table->timestamps();
        });

        // 5. Historique d'audit des statuts de commandes (CDC E-02)
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('author_type', 30)->default('merchant'); // merchant, customer, system
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('comment', 255)->nullable();
            $table->timestamps();
        });

        // 6. Transactions et paiements (Module F - F-01 à F-04)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_reference', 64)->unique(); // PAY-20261026-XXXX
            $table->string('method', 30); // cash_on_delivery, tmoney, flooz, card
            $table->unsignedInteger('amount'); // en FCFA
            $table->string('currency', 5)->default('XOF');
            $table->string('status', 30)->default('pending'); // pending, paid, failed, refunded
            $table->string('operator_reference', 100)->nullable(); // Transaction ID T-Money / Flooz
            $table->string('payer_phone', 30)->nullable();
            $table->timestamp('expires_at')->nullable(); // Expiration à 15 min pour Mobile Money (CDC F-01)
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('shipping_zones');
    }
};
