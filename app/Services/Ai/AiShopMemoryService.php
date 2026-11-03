<?php

namespace App\Services\Ai;

use App\Models\AiShopMemory;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiShopMemoryService
{
    /**
     * Récupère l'ensemble des mémoires actives pour une boutique donnée.
     */
    public function getMemoriesForShop(Shop $shop): Collection
    {
        return AiShopMemory::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->orderBy('category')
            ->get();
    }

    /**
     * Formate les mémoires long terme de la boutique pour le prompt système de l'IA.
     */
    public function formatForPrompt(Shop $shop): string
    {
        $memories = $this->getMemoriesForShop($shop);

        if ($memories->isEmpty()) {
            return "Mémoire de la boutique : Première collaboration. Aucun fait historique enregistré pour l'instant.";
        }

        $lines = ["--- MÉMOIRE LONG TERME DE LA BOUTIQUE (DONNÉES STRICTEMENT CONFIDENTIELLES) ---"];
        $lines[] = "Tu as déjà collaboré avec ce commerçant et tu te souviens des faits suivants le concernant :";

        $categoriesLabels = [
            AiShopMemory::CAT_PROFILE => 'Profil & Niche Métier',
            AiShopMemory::CAT_PREFERENCE => 'Préférences de Communication & Gestion',
            AiShopMemory::CAT_GOAL => 'Objectifs Commerciaux & Cibles Chiffrées',
            AiShopMemory::CAT_HABIT => 'Habitudes Opérationnelles & Logistique',
            AiShopMemory::CAT_RECOMMENDATION => 'Dernières Stratégies Recommandées',
        ];

        $grouped = $memories->groupBy('category');

        foreach ($grouped as $cat => $items) {
            $catTitle = $categoriesLabels[$cat] ?? ucfirst($cat);
            $lines[] = "• {$catTitle} :";
            foreach ($items as $m) {
                $lines[] = "   - {$m->memory_key} : {$m->memory_value}";
                $m->updateQuietly(['last_recalled_at' => now()]);
            }
        }

        $lines[] = "Prends en compte ces faits pour personnaliser tes réponses sans avoir à lui redemander ces informations.";
        $lines[] = "--- FIN DE LA MÉMOIRE BOUTIQUE ---";

        return implode("\n", $lines);
    }

    /**
     * Enregistre ou met à jour un souvenir commercial pour la boutique.
     */
    public function storeMemory(
        Shop $shop,
        string $category,
        string $key,
        string $value,
        float $confidence = 1.0,
        string $source = 'system'
    ): AiShopMemory {
        return AiShopMemory::withoutGlobalScopes()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'memory_key' => Str::slug($key, '_'),
            ],
            [
                'category' => $category,
                'memory_value' => trim($value),
                'confidence' => $confidence,
                'source' => $source,
                'last_recalled_at' => now(),
            ]
        );
    }

    /**
     * Analyse un tour de conversation pour extraire automatiquement les préférences ou faits déclarés.
     */
    public function extractAndSaveFacts(Shop $shop, string $userMessage, string $assistantResponse): void
    {
        $msg = mb_strtolower($userMessage);

        // 1. Détection de la ville ou zone prioritaire
        $cities = [
            'lomé' => 'Lomé (Togo)',
            'lome' => 'Lomé (Togo)',
            'kara' => 'Kara (Togo)',
            'cotonou' => 'Cotonou (Bénin)',
            'calavi' => 'Abomey-Calavi (Bénin)',
            'porto-novo' => 'Porto-Novo (Bénin)',
            'abidjan' => 'Abidjan (Côte d\'Ivoire)',
            'yamoussoukro' => 'Yamoussoukro (Côte d\'Ivoire)',
            'dakar' => 'Dakar (Sénégal)',
            'ouagadougou' => 'Ouagadougou (Burkina Faso)',
        ];

        foreach ($cities as $needle => $label) {
            if (str_contains($msg, $needle) && (str_contains($msg, 'je livre') || str_contains($msg, 'mes clients') || str_contains($msg, 'boutique') || str_contains($msg, 'situé') || str_contains($msg, 'zone'))) {
                $this->storeMemory(
                    $shop,
                    AiShopMemory::CAT_HABIT,
                    'zone_principale',
                    "Zone de chalandise prioritaire : {$label}",
                    0.95,
                    'conversation_extraction'
                );
                break;
            }
        }

        // 2. Détection du canal de vente privilégié
        if (str_contains($msg, 'whatsapp') && (str_contains($msg, 'je vends') || str_contains($msg, 'mes commandes') || str_contains($msg, 'mes clients'))) {
            $this->storeMemory(
                $shop,
                AiShopMemory::CAT_HABIT,
                'canal_privilegie',
                "Utilise fortement WhatsApp pour conclure les ventes et échanger avec les clients",
                0.90,
                'conversation_extraction'
            );
        }

        // 3. Détection d'un objectif de chiffre d'affaires
        if (preg_match('/(\d+[\s\d]*)\s*(fcfa|f cfa|francs?)/i', $msg, $matches)) {
            $amount = preg_replace('/\s+/', '', $matches[1]);
            if ((int) $amount >= 10000 && (str_contains($msg, 'objectif') || str_contains($msg, 'viser') || str_contains($msg, 'atteindre') || str_contains($msg, 'gagner'))) {
                $this->storeMemory(
                    $shop,
                    AiShopMemory::CAT_GOAL,
                    'objectif_financier',
                    "Objectif commercial déclaré : " . number_format((int) $amount, 0, ',', ' ') . " FCFA",
                    0.85,
                    'conversation_extraction'
                );
            }
        }

        // 4. Détection du secteur d'activité ou niche spécifique
        $niches = [
            'chaussure' => 'Chaussures & Sneakers',
            'vetement' => 'Mode & Prêt-à-porter',
            'robe' => 'Mode Féminine & Robes',
            'perruque' => 'Mèches & Perruques',
            'cosmetique' => 'Beauté & Cosmétiques',
            'savon' => 'Soins corporels & Savons artisanaux',
            'telephone' => 'Smartphones & High-tech',
            'bijoux' => 'Bijoux & Accessoires',
            'montre' => 'Montres & Horlogerie',
            'nourriture' => 'Alimentation & Épicerie fine',
        ];

        foreach ($niches as $k => $label) {
            if (str_contains($msg, $k) && (str_contains($msg, 'je vends') || str_contains($msg, 'mes produits') || str_contains($msg, 'spécialisé') || str_contains($msg, 'boutique de'))) {
                $this->storeMemory(
                    $shop,
                    AiShopMemory::CAT_PROFILE,
                    'niche_produits',
                    "Niche commerciale spécialisée : {$label}",
                    0.90,
                    'conversation_extraction'
                );
                break;
            }
        }
    }
}
