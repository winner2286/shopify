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
        // Table des abonnements Push des terminaux mobiles et ordinateurs (PWA Web Push)
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('endpoint');
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->default('aesgcm');
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Table des alertes et du journal d'audit du système (Super-Admin Cockpit)
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // merchant_registered, shop_launched, order_created, api_warning, security_alert
            $table->string('severity')->default('info'); // info, success, warning, danger
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // metadata: shop_id, user_id, order_id, link, etc.
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_read']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('push_subscriptions');
    }
};
