<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrichissement de la table shops pour l'onboarding et les pages
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('status'); // 1: Activité, 2: Questionnaire, 3: Photos, 4: Génération, 5: Aperçu
            $table->json('onboarding_data')->nullable()->after('onboarding_step'); // Réponses brouillons
            $table->string('logo_path')->nullable()->after('theme');
            $table->string('brand_color', 10)->default('#6d3df2')->after('logo_path');
            $table->string('visual_style', 30)->default('moderne')->after('brand_color');
            $table->string('target_audience', 100)->nullable()->after('visual_style');
        });

        // Journal de consommation de l'IA (CDC Section 6.1 et J-04)
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // shop_init, product_copy, logo, visual, ad_text, ad_script
            $table->string('step', 40)->nullable(); // brand_profile, photo_analysis, categories, products, pages
            $table->string('model_used', 50)->default('gemini-2.5-flash');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('cost_estimated_fcfa')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('success'); // success | fallback | failed
            $table->text('input_summary')->nullable();
            $table->json('output_data')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'type']);
            $table->index(['shop_id', 'created_at']);
        });

        // Pages d'information de la boutique (B-08 : À propos, Contact, Livraison, CGV)
        Schema::create('shop_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // about, contact, shipping, terms
            $table->string('title', 120);
            $table->longText('content');
            $table->string('locale', 5)->default('fr');
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pages');
        Schema::dropIfExists('ai_generations');
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_step',
                'onboarding_data',
                'logo_path',
                'brand_color',
                'visual_style',
                'target_audience',
            ]);
        });
    }
};
