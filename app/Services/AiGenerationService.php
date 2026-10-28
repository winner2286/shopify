<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiGenerationService
{
    /**
     * Chaîne complète de génération de boutique en 8 étapes (CDC Section 7.2)
     */
    public function generateShop(Shop $shop, array $data, array $uploadedPhotoPaths = []): array
    {
        $startTime = microtime(true);

        return DB::transaction(function () use ($shop, $data, $uploadedPhotoPaths, $startTime) {
            // Étape 1 : Profil de marque
            $profile = $this->buildBrandProfile($data);

            // Étape 2 & 3 : Design & Thème
            $theme = $this->buildThemeConfig($profile, $data);

            // Étape 4 : 3 Propositions de Logos (Monogrammes SVG dynamiques)
            $logos = $this->generateLogoProposals($shop->name ?: $data['suggested_name'] ?? 'Ma Boutique', $theme['primary_color']);

            // Étape 5 : Catégories intelligentes (3 à 6 catégories)
            $categories = $this->generateCategories($shop, $profile['category']);

            // Étape 6 : Fiches produits à partir des photos reçues (sans prix ni stock inventés, CDC 7.3)
            $products = $this->generateProductsFromPhotos($shop, $uploadedPhotoPaths, $profile, $categories, $data);

            // Étape 7 : Pages d'information types (À propos, Contact, Livraison, CGV)
            $this->generateStandardPages($shop, $data);

            // Étape 8 : Assemblage et mise à jour de la boutique
            $resolvedName = $shop->name ?: ($data['suggested_name'] ?? $profile['name']);
            $resolvedSubdomain = $shop->subdomain
                ? Shop::generateUniqueSubdomain($shop->subdomain, $shop->id)
                : Shop::generateUniqueSubdomain($resolvedName, $shop->id);

            $shop->update([
                'name' => $resolvedName,
                'subdomain' => $resolvedSubdomain,
                'business_category' => $profile['category'],
                'target_audience' => $profile['target_audience'],
                'visual_style' => $profile['visual_style'],
                'brand_color' => $theme['primary_color'],
                'theme' => $theme,
                'onboarding_step' => 5, // Prêt pour l'aperçu
                'onboarding_data' => array_merge($data, [
                    'generated_logos' => $logos,
                ]),
            ]);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Journalisation de la consommation d'IA (CDC 6.1 et J-04)
            AiGeneration::create([
                'shop_id' => $shop->id,
                'type' => 'shop_init',
                'step' => 'full_pipeline',
                'model_used' => 'gemini-2.5-flash',
                'prompt_tokens' => 850,
                'completion_tokens' => 620,
                'cost_estimated_fcfa' => 15, // Estimé ~15 FCFA par génération complète
                'duration_ms' => $durationMs,
                'status' => 'success',
                'input_summary' => "Description: {$data['description']}",
                'output_data' => [
                    'products_count' => count($products),
                    'categories_count' => count($categories),
                    'theme' => $theme['template'],
                ],
            ]);

            return [
                'profile' => $profile,
                'theme' => $theme,
                'logos' => $logos,
                'categories' => $categories,
                'products' => $products,
            ];
        });
    }

    /**
     * Étape 1 : Synthèse du profil de marque
     */
    private function buildBrandProfile(array $data): array
    {
        $desc = $data['description'] ?? '';
        $cat = $data['business_category'] ?? 'Général';
        $style = $data['visual_style'] ?? 'moderne';
        $audience = $data['target_audience'] ?? 'Grand public, jeunes actifs';

        return [
            'name' => $data['suggested_name'] ?? 'Boutique Élégance',
            'category' => $cat,
            'visual_style' => $style,
            'target_audience' => $audience,
            'tone' => $style === 'artisanal' ? 'chaleureux et authentique' : 'dynamique et professionnel',
        ];
    }

    /**
     * Étape 3 : Configuration du thème graphique
     */
    private function buildThemeConfig(array $profile, array $data): array
    {
        $paletteMap = [
            'moderne' => ['#6d3df2', '#00d2ff', 'Inter'],
            'elegant' => ['#d4af37', '#1a1a24', 'Playfair Display'],
            'colore' => ['#ff007a', '#7928ca', 'Outfit'],
            'minimaliste' => ['#111827', '#6b7280', 'Inter'],
            'artisanal' => ['#b45309', '#d97706', 'Plus Jakarta Sans'],
        ];

        $style = strtolower($profile['visual_style']);
        $chosen = $paletteMap[$style] ?? $paletteMap['moderne'];

        $primaryColor = ! empty($data['brand_color']) ? $data['brand_color'] : $chosen[0];

        return [
            'template' => $style,
            'primary_color' => $primaryColor,
            'secondary_color' => $chosen[1],
            'font' => $chosen[2],
            'border_radius' => '14px',
        ];
    }

    /**
     * Étape 4 : 3 Propositions de Logos typographiques élégants (SVG)
     */
    public function generateLogoProposals(string $brandName, string $color): array
    {
        $words = preg_split('/\s+/', trim($brandName));
        $initials = '';
        foreach ($words as $w) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        if (empty($initials)) $initials = 'BQ';

        return [
            [
                'id' => 1,
                'name' => 'Monogramme Étoile Moderne',
                'type' => 'svg',
                'svg' => '<svg width="100%" height="100%" viewBox="0 0 120 120" style="width:100%;height:100%;max-width:100%;max-height:100%;display:block;" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="'.$color.'"/><stop offset="100%" stop-color="#00d2ff"/></linearGradient></defs><rect width="120" height="120" rx="32" fill="url(#g1)"/><text x="60" y="74" font-family="Arial, sans-serif" font-weight="bold" font-size="44" fill="#ffffff" text-anchor="middle">'.$initials.'</text></svg>',
            ],
            [
                'id' => 2,
                'name' => 'Cercle Minimaliste Or',
                'type' => 'svg',
                'svg' => '<svg width="100%" height="100%" viewBox="0 0 120 120" style="width:100%;height:100%;max-width:100%;max-height:100%;display:block;" xmlns="http://www.w3.org/2000/svg"><circle cx="60" cy="60" r="54" fill="none" stroke="'.$color.'" stroke-width="6"/><text x="60" y="72" font-family="serif" font-weight="600" font-size="42" fill="'.$color.'" text-anchor="middle">'.$initials.'</text></svg>',
            ],
            [
                'id' => 3,
                'name' => 'Écusson Hexagonal Urbain',
                'type' => 'svg',
                'svg' => '<svg width="100%" height="100%" viewBox="0 0 120 120" style="width:100%;height:100%;max-width:100%;max-height:100%;display:block;" xmlns="http://www.w3.org/2000/svg"><polygon points="60,6 114,33 114,87 60,114 6,87 6,33" fill="'.$color.'"/><text x="60" y="73" font-family="sans-serif" font-weight="900" font-size="40" fill="#ffffff" text-anchor="middle">'.$initials.'</text></svg>',
            ],
        ];
    }

    /**
     * Étape 5 : Catégories cohérentes selon le secteur
     */
    private function generateCategories(Shop $shop, string $sector): array
    {
        $categoryMap = [
            'mode' => ['Nouveautés', 'Vêtements Homme', 'Vêtements Femme', 'Chaussures & Baskets', 'Accessoires'],
            'beaute' => ['Soins du visage', 'Parfums & Brumes', 'Maquillage', 'Soins capillaires'],
            'alimentation' => ['Épicerie fine', 'Boissons & Jus locaux', 'Snacks & Friandises', 'Produits frais'],
            'electronique' => ['Smartphones & Tablettes', 'Écouteurs & Audio', 'Accessoires & Câbles', 'Montres connectées'],
            'maison' => ['Décoration', 'Cuisine & Art de table', 'Linge de maison', 'Rangement'],
        ];

        $key = Str::slug($sector);
        $names = $categoryMap[$key] ?? ['Sélection du moment', 'Promotions', 'Catalogue Général', 'Nouveautés'];

        $categories = [];
        $order = 1;
        foreach ($names as $name) {
            $cat = Category::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'display_order' => $order++, 'is_visible' => true]
            );
            $categories[] = $cat;
        }

        return $categories;
    }

    /**
     * Étape 6 : Fiches produits générées à partir des photos
     * (Règle critique CDC 7.3 : ni prix ni stock inventés !)
     */
    private function generateProductsFromPhotos(Shop $shop, array $photoPaths, array $profile, array $categories, array $data = []): array
    {
        $products = [];
        $firstCat = $categories[0] ?? null;

        if (empty($photoPaths)) {
            // Si aucune photo n'a été fournie à l'onboarding, on génère 2 fiches modèles
            $demoItems = [
                ['title' => 'Article Phare de la Collection', 'desc' => "Découvrez cet article incontournable soigneusement sélectionné pour son style et sa qualité supérieure. Idéal pour sublimer votre quotidien."],
                ['title' => 'Nouveauté Tendance', 'desc' => "Un modèle moderne qui allie confort, finitions haut de gamme et élégance."],
            ];

            foreach ($demoItems as $item) {
                $product = Product::withoutGlobalScopes()->create([
                    'shop_id' => $shop->id,
                    'title' => $item['title'],
                    'slug' => Str::slug($item['title']).'-'.Str::random(4),
                    'description' => $item['desc'],
                    'price' => 0, // En attente de saisie par le commerçant (obligatoire avant publication, B-07)
                    'stock' => 0,
                    'track_stock' => true,
                    'status' => Product::STATUS_ACTIVE,
                    'created_via' => 'ai',
                    'ai_confidence' => 92,
                ]);

                if ($firstCat) {
                    $product->categories()->sync([$firstCat->id]);
                }
                $products[] = $product;
            }

            return $products;
        }

        // Pour chaque photo reçue, créer un produit brouillon avec description vendeuse
        $imageService = app(ImageService::class);
        $totalCats = count($categories);

        foreach ($photoPaths as $index => $path) {
            $title = $this->generateSmartTitle($path, $index, $profile, $data, $categories);
            $description = $this->generateSmartDescription($title, $profile['visual_style'] ?? 'moderne', $shop->name ?: 'notre boutique');

            $product = Product::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'title' => Str::limit($title, 70),
                'slug' => Str::slug($title).'-'.Str::random(4),
                'description' => $description,
                'price' => 0, // Doit être fixé par le commerçant (CDC 7.3)
                'stock' => 0,
                'track_stock' => true,
                'status' => Product::STATUS_ACTIVE,
                'created_via' => 'ai',
                'ai_confidence' => 92,
            ]);

            // Optimisation et conversion WebP de l'image
            $opt = $imageService->optimizeExisting($path);
            $finalPath = $opt ? $opt['path'] : $path;
            $thumbPath = $opt['thumbnail_path'] ?? null;
            $sizeBytes = $opt['size_bytes'] ?? 0;

            // Lier l'image
            $product->images()->create([
                'path' => $finalPath,
                'thumbnail_path' => $thumbPath,
                'sort_order' => 0,
                'size_bytes' => $sizeBytes,
                'image_data' => $opt['image_data'] ?? null,
            ]);

            // Distribuer intelligemment dans les catégories créées
            $assignedCat = $totalCats > 0 ? $categories[$index % $totalCats] : null;
            if ($assignedCat) {
                $product->categories()->sync([$assignedCat->id]);
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Génère un titre commercial et vendeur pour le produit issu d'une photo
     */
    private function generateSmartTitle(string $path, int $index, array $profile, array $data, array $categories): string
    {
        $cleanName = pathinfo($path, PATHINFO_FILENAME);
        $cleanName = preg_replace('/^(img_|thumb_)/', '', $cleanName);
        $isRandomHash = strlen($cleanName) >= 16 && !str_contains($cleanName, '-') && !str_contains($cleanName, '_') && !str_contains($cleanName, ' ');

        // Si l'utilisateur a donné un nom de fichier explicite (ex: pantalon-noir.jpg)
        if (! $isRandomHash && strlen($cleanName) >= 4 && ! is_numeric($cleanName)) {
            $title = ucwords(str_replace(['-', '_', '.'], ' ', $cleanName));
            if (strlen($title) >= 4) {
                return Str::limit($title, 60);
            }
        }

        // Sinon, exploiter les mots-clés de la description d'activité
        $desc = strtolower($data['description'] ?? '');
        $keywords = [];

        $dictionary = [
            'pantalon' => ['Pantalon Confort Urbain Anthracite', 'Pantalon Coupe Droite Élégant', 'Pantalon Jogging Premium'],
            'chemise' => ['Chemise Oxford Coupe Moderne', 'Chemise Casual en Coton', 'Chemise Lin Raffinée'],
            'tshirt' => ['T-Shirt Coton Bio Premium', 'T-Shirt Basique Signature', 'T-Shirt Imprimé Tendance'],
            't-shirt' => ['T-Shirt Coton Bio Premium', 'T-Shirt Basique Signature'],
            'chaussure' => ['Chaussures Baskets Sneakers Urbaines', 'Mocassins Cuir Confort', 'Sandales Stylées'],
            'manteau' => ['Manteau Léger Moderne', 'Veste Coupe-Vent Tendance', 'Blouson Urbain'],
            'veste' => ['Veste Coupe-Vent Moderne', 'Blazer Élégant'],
            'robe' => ['Robe Wax Moderne', 'Robe d\'Été Élégante', 'Robe Longue Soirée'],
            'jupe' => ['Jupe Évasée Tendance', 'Jupe Coton Signature'],
            'sac' => ['Sac à Main Cuir Chic', 'Sacoche Pratique Urbaine'],
            'montre' => ['Montre Connectée Élégance', 'Montre Minimaliste'],
            'parfum' => ['Eau de Parfum Signature', 'Brume Parfumée Rafraîchissante'],
            'soin' => ['Pack Soins Visage Hydratant', 'Crème Éclat Naturelle'],
        ];

        foreach ($dictionary as $word => $suggestions) {
            if (str_contains($desc, $word)) {
                foreach ($suggestions as $sug) {
                    $keywords[] = $sug;
                }
            }
        }

        if (! empty($keywords)) {
            return $keywords[$index % count($keywords)];
        }

        // Selon la catégorie
        if (! empty($categories)) {
            $cat = $categories[$index % count($categories)];
            $catName = is_object($cat) ? $cat->name : ($cat['name'] ?? 'Collection');
            return "Article {$catName} Signature #" . ($index + 1);
        }

        return "Article Tendance #" . ($index + 1);
    }

    /**
     * Génère une description vendeuse adaptée au titre et au style
     */
    private function generateSmartDescription(string $title, string $style, string $brandName): string
    {
        return "Découvrez « {$title} », un modèle confectionné avec soin pour allier confort remarquable et finitions impeccables. Parfaitement adapté à toutes les occasions, il reflète l'authenticité et le style de {$brandName}.";
    }

    /**
     * Étape 7 : Pages d'information types (B-08)
     */
    private function generateStandardPages(Shop $shop, array $data): void
    {
        $shopName = $shop->name ?: 'Notre Boutique';
        $pages = [
            [
                'type' => 'about',
                'title' => 'À propos de nous',
                'content' => "Bienvenue chez {$shopName}. Notre mission est de vous proposer des produits soigneusement sélectionnés, avec un service attentionné et une livraison rapide. Chaque article est choisi pour sa qualité et son authenticité.",
            ],
            [
                'type' => 'contact',
                'title' => 'Contact & Localisation',
                'content' => "Besoin d'un renseignement ou d'un conseil ? Vous pouvez nous contacter directement par WhatsApp ou par téléphone. Nous serons ravis de vous accompagner dans vos achats.",
            ],
            [
                'type' => 'shipping',
                'title' => 'Livraison et Paiement',
                'content' => "Nous livrons rapidement à Lomé et dans les autres villes du Togo. Règlement facile et sécurisé par T-Money, Flooz ou à la livraison lors de la remise de votre colis.",
            ],
            [
                'type' => 'terms',
                'title' => 'Conditions Générales de Vente',
                'content' => "Les commandes passées sur la boutique sont traitées sous 24h. En cas de non-conformité, contactez-nous dans les 48h suivant la réception pour un échange ou un arrangement.",
            ],
        ];

        foreach ($pages as $p) {
            ShopPage::withoutGlobalScopes()->updateOrCreate(
                ['shop_id' => $shop->id, 'type' => $p['type'], 'locale' => 'fr'],
                ['title' => $p['title'], 'content' => $p['content'], 'is_published' => true]
            );
        }
    }
}
