<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'essentiel',
                'name' => 'Essentiel',
                'monthly_price' => null,
                'max_products' => 30,
                'storage_mb' => 1024,
                'ai_credits' => 60,
                'full_generations' => 1,
                'features' => ['Publicité IA : textes', 'Tableau de bord simple', 'Support email'],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'monthly_price' => null,
                'max_products' => 300,
                'storage_mb' => 5120,
                'ai_credits' => 300,
                'full_generations' => 1,
                'features' => ['Publicité IA : textes, visuels et scripts', 'Tableau de bord simple', 'Support email et WhatsApp'],
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'monthly_price' => null,
                'max_products' => 3000,
                'storage_mb' => 20480,
                'ai_credits' => 1000,
                'full_generations' => 3,
                'features' => ['Publicité IA : tout, quota élevé', 'Statistiques avancées (V2)', 'Support prioritaire'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
