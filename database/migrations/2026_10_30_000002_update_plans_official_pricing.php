<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Essentiel - 10 000 FCFA
        DB::table('plans')->updateOrInsert(
            ['slug' => 'essentiel'],
            [
                'name' => 'Essentiel',
                'monthly_price' => 10000,
                'max_products' => 50,
                'storage_mb' => 2048,
                'ai_credits' => 80,
                'full_generations' => 1,
                'features' => json_encode([
                    'Publicité IA : textes & accroches simples',
                    'Tableau de bord de vente simple',
                    'Support standard par email',
                ]),
                'active' => true,
                'updated_at' => now(),
            ]
        );

        // 2. Pro - 15 000 FCFA (Recommandé)
        DB::table('plans')->updateOrInsert(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'monthly_price' => 15000,
                'max_products' => 300,
                'storage_mb' => 8192,
                'ai_credits' => 350,
                'full_generations' => 2,
                'features' => json_encode([
                    'Publicité IA : textes, visuels & scripts TikTok',
                    'Codes promo & soldes programmées',
                    'Alertes rupture de stock (WhatsApp/SMS)',
                    'Statistiques complètes & top ventes',
                    'Support prioritaire par WhatsApp',
                ]),
                'active' => true,
                'updated_at' => now(),
            ]
        );

        // 3. Premium - 30 000 FCFA
        DB::table('plans')->updateOrInsert(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'monthly_price' => 30000,
                'max_products' => 2500,
                'storage_mb' => 25600,
                'ai_credits' => 1200,
                'full_generations' => 5,
                'features' => json_encode([
                    'Campagnes IA sponsorisées complètes',
                    'Codes promo illimités & ventes privées',
                    'Gestion multi-collaborateurs (jusqu\'à 3 accès)',
                    'Export comptable des commandes (Excel / PDF)',
                    'Connexion de nom de domaine personnalisé',
                    'Chargé de compte dédié & Support VIP 7j/7',
                ]),
                'active' => true,
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
