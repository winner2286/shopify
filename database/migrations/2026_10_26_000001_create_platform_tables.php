<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Formules d'abonnement (configurables par l'admin, J-06)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('name', 60);
            $table->unsignedInteger('monthly_price')->nullable(); // FCFA, null = à fixer
            $table->unsignedInteger('max_products');
            $table->unsignedInteger('storage_mb');
            $table->unsignedInteger('ai_credits');
            $table->unsignedTinyInteger('full_generations')->default(1);
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Boutiques (une par commerçant en V1, RG-01)
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('subdomain', 30)->nullable()->unique();
            $table->string('status', 20)->default('draft'); // draft | published | paused | suspended
            $table->string('locale', 5)->default('fr');
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->string('business_category', 40)->nullable();
            $table->json('theme')->nullable();
            $table->json('contact')->nullable();
            $table->timestamps();
        });

        // Abonnements (RG-05)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 20)->default('trial'); // trial | active | grace | suspended | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        // Codes à usage unique (A-01 : 10 min, 5 tentatives)
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20)->default('register');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('plans');
    }
};
