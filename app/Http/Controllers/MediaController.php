<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
    /**
     * Entêtes HTTP avec support complet de CORS pour le Studio Vidéo et les Canvas
     */
    private function imageHeaders(int $maxAge = 2592000, ?string $contentType = null): array
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => "public, max-age={$maxAge}",
        ];
        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }
        return $headers;
    }

    /**
     * Sert un fichier média du stockage public ou le régénère dynamiquement
     */
    public function serve(Request $request, string $path): BinaryFileResponse|Response
    {
        // Nettoyage de sécurité contre les traversées de répertoires
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        $diskPath = Storage::disk('public')->path($cleanPath);

        // 1. Si le fichier existe déjà physiquement sur disque, le servir immédiatement
        if (file_exists($diskPath) && filesize($diskPath) > 0) {
            return response()->file($diskPath, $this->imageHeaders());
        }

        // 2. Recherche en base de données pour restauration persistante
        $basename = basename($cleanPath);
        $productImage = ProductImage::where('path', $cleanPath)
            ->orWhere('thumbnail_path', $cleanPath)
            ->orWhere('path', '/' . $cleanPath)
            ->orWhere('thumbnail_path', '/' . $cleanPath)
            ->orWhere('path', 'like', '%' . $basename)
            ->orWhere('thumbnail_path', 'like', '%' . $basename)
            ->first();

        if ($productImage && ! empty($productImage->image_data)) {
            $binary = @base64_decode($productImage->image_data);
            if ($binary) {
                @mkdir(dirname($diskPath), 0755, true);
                @file_put_contents($diskPath, $binary);
                if (file_exists($diskPath) && filesize($diskPath) > 0) {
                    return response()->file($diskPath, $this->imageHeaders());
                }
                // Si l'écriture sur disque est restreinte, servir directement depuis la mémoire
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($binary) ?: 'image/webp';
                return response($binary, 200, $this->imageHeaders(2592000, $mimeType));
            }
        }

        // 3. Fichier manquant : association avec le catalogue réaliste permanent
        $product = $productImage?->product;
        if (! $product) {
            if (preg_match('/shops\/(\d+)/', $cleanPath, $m)) {
                $product = Product::where('shop_id', $m[1])->first();
            }
        }

        $catalogPhoto = $this->resolveCatalogPhoto($product, $cleanPath);
        if ($catalogPhoto && file_exists($catalogPhoto)) {
            $content = file_get_contents($catalogPhoto);
            @mkdir(dirname($diskPath), 0755, true);
            @file_put_contents($diskPath, $content);

            // Persistance définitive dans PostgreSQL
            if ($productImage && empty($productImage->image_data)) {
                $productImage->update(['image_data' => base64_encode($content)]);
            }

            return response()->file($diskPath, $this->imageHeaders());
        }

        // 4. Génération dynamique WebP avec GD
        if ($this->generateWebPPlaceholder($product, $diskPath)) {
            if ($productImage && empty($productImage->image_data) && file_exists($diskPath)) {
                $productImage->update(['image_data' => base64_encode(file_get_contents($diskPath))]);
            }
            return response()->file($diskPath, $this->imageHeaders(86400));
        }

        // 5. Fallback vectoriel SVG pur
        $svg = $this->generateSvgPlaceholder($product);
        return response($svg, 200, $this->imageHeaders(86400, 'image/svg+xml'));
    }

    /**
     * Endpoint API public pour générer une image de substitution
     */
    public function placeholder(Request $request): BinaryFileResponse|Response
    {
        $product = null;
        if ($request->filled('id')) {
            $product = Product::find($request->id);
        }

        $title = $request->input('title', $product?->title ?? 'Article Signature');
        $cat = $request->input('cat', $product?->categories?->first()?->name ?? 'Collection');
        $color = $request->input('color', $product?->shop?->brand_color ?? '#10b981');

        // Vérifier si une photo réaliste correspond à l'article
        $catalogPhoto = $this->resolveCatalogPhoto($product, $title . ' ' . $cat);
        if ($catalogPhoto && file_exists($catalogPhoto)) {
            if ($product) {
                $primary = $product->primaryImage;
                $b64 = base64_encode(file_get_contents($catalogPhoto));
                if ($primary) {
                    if (empty($primary->image_data)) {
                        $primary->update(['image_data' => $b64]);
                    }
                } else {
                    $product->images()->create([
                        'path' => "shops/{$product->shop_id}/products/" . basename($catalogPhoto),
                        'thumbnail_path' => "shops/{$product->shop_id}/products/thumb_" . basename($catalogPhoto),
                        'sort_order' => 0,
                        'size_bytes' => filesize($catalogPhoto),
                        'image_data' => $b64,
                    ]);
                }
            }

            return response()->file($catalogPhoto, $this->imageHeaders(86400));
        }

        $svg = $this->buildSvgContent($title, $cat, $color);
        return response($svg, 200, $this->imageHeaders(86400, 'image/svg+xml'));
    }

    /**
     * Associe un produit à une photo haute définition du catalogue
     */
    private function resolveCatalogPhoto(?Product $product, string $hint = ''): ?string
    {
        $text = mb_strtolower(($product?->title ?? '') . ' ' . ($product?->categories?->first()?->name ?? '') . ' ' . $hint);
        $isThumb = str_contains($hint, 'thumb');

        if (str_contains($text, 'frite') || str_contains($text, 'snack')) {
            $file = $isThumb ? 'thumb_frites.webp' : 'frites.webp';
            $path = public_path("images/catalog/{$file}");
            if (file_exists($path)) return $path;
            return public_path('images/catalog/frites.webp');
        }

        if (str_contains($text, 'poisson') || str_contains($text, 'fish')) {
            $file = $isThumb ? 'thumb_poisson.webp' : 'poisson.webp';
            $path = public_path("images/catalog/{$file}");
            if (file_exists($path)) return $path;
            return public_path('images/catalog/poisson.webp');
        }

        if (str_contains($text, 'burger') || str_contains($text, 'sandwich') || str_contains($text, 'pain')) {
            $file = $isThumb ? 'thumb_burger.webp' : 'burger.webp';
            $path = public_path("images/catalog/{$file}");
            if (file_exists($path)) return $path;
            return public_path('images/catalog/burger.webp');
        }

        return null;
    }

    /**
     * Génère une image WebP réelle via l'extension GD
     */
    private function generateWebPPlaceholder(?Product $product, string $targetPath): bool
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
            return false;
        }

        try {
            $width = 600;
            $height = 600;
            $im = imagecreatetruecolor($width, $height);

            // Dégradé de fond sombre prestigieux
            $brandColorHex = $product?->shop?->brand_color ?: '#10b981';
            [$br, $bg, $bb] = $this->hexToRgb($brandColorHex);

            for ($y = 0; $y < $height; $y++) {
                $factor = $y / $height;
                $r = (int) (18 * (1 - $factor) + 10 * $factor);
                $g = (int) (18 * (1 - $factor) + 10 * $factor);
                $b = (int) (24 * (1 - $factor) + 14 * $factor);
                $color = imagecolorallocate($im, $r, $g, $b);
                imageline($im, 0, $y, $width, $y, $color);
            }

            // Halo lumineux de marque au centre
            imagealphablending($im, true);
            imagesavealpha($im, true);

            for ($radius = 240; $radius > 0; $radius -= 8) {
                $alpha = (int) (127 - (45 * (1 - $radius / 240)));
                $haloColor = imagecolorallocatealpha($im, $br, $bg, $bb, max(0, min(127, $alpha)));
                imagefilledellipse($im, 300, 270, $radius * 2, $radius * 2, $haloColor);
            }

            // Cadre arrondi central
            $cardBorder = imagecolorallocatealpha($im, 255, 255, 255, 100);
            $cardBg = imagecolorallocatealpha($im, 20, 20, 26, 30);
            imagefilledellipse($im, 300, 270, 220, 220, $cardBg);
            imageellipse($im, 300, 270, 220, 220, $cardBorder);

            // Étoile / icône au centre
            $starColor = imagecolorallocate($im, 255, 255, 255);
            $goldColor = imagecolorallocate($im, $br, $bg, $bb);

            // Dessin d'un sac / diamant stylisé
            imagefilledrectangle($im, 280, 250, 320, 290, $goldColor);
            imagefilledellipse($im, 300, 240, 24, 24, $starColor);

            // Textes avec polices intégrées
            $textWhite = imagecolorallocate($im, 255, 255, 255);
            $textSoft = imagecolorallocate($im, 203, 213, 225);

            $catName = mb_strtoupper($product?->categories?->first()?->name ?? 'BOUTIQUE EXCLUSIVE');
            $prodTitle = $product?->title ?? 'Article Signature';
            if (mb_strlen($prodTitle) > 30) {
                $prodTitle = mb_substr($prodTitle, 0, 27) . '...';
            }

            // Catégorie
            $catX = max(40, (int) (300 - (mb_strlen($catName) * 4.5)));
            imagestring($im, 3, $catX, 420, $catName, $goldColor);

            // Titre du produit
            $titleX = max(30, (int) (300 - (mb_strlen($prodTitle) * 4.5)));
            imagestring($im, 5, $titleX, 450, $prodTitle, $textWhite);

            // Mention Zanika
            $shopName = 'Zanika - ' . ($product?->shop?->name ?: 'Commerce');
            $shopX = max(40, (int) (300 - (mb_strlen($shopName) * 4)));
            imagestring($im, 2, $shopX, 490, $shopName, $textSoft);

            @mkdir(dirname($targetPath), 0755, true);
            imagewebp($im, $targetPath, 85);
            imagedestroy($im);

            return file_exists($targetPath) && filesize($targetPath) > 0;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Génère un SVG vectoriel haute définition
     */
    private function generateSvgPlaceholder(?Product $product): string
    {
        $title = $product?->title ?? 'Article Signature';
        $cat = $product?->categories?->first()?->name ?? 'Collection';
        $color = $product?->shop?->brand_color ?: '#10b981';

        return $this->buildSvgContent($title, $cat, $color);
    }

    private function buildSvgContent(string $title, string $category, string $color): string
    {
        $safeTitle = htmlspecialchars(mb_substr($title, 0, 34), ENT_QUOTES, 'UTF-8');
        $safeCat = htmlspecialchars(mb_strtoupper(mb_substr($category, 0, 24)), ENT_QUOTES, 'UTF-8');
        $initial = htmlspecialchars(mb_strtoupper(mb_substr($safeTitle, 0, 1)), ENT_QUOTES, 'UTF-8') ?: 'B';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
    <defs>
        <radialGradient id="halo" cx="50%" cy="40%" r="50%">
            <stop offset="0%" stop-color="{$color}" stop-opacity="0.35"/>
            <stop offset="100%" stop-color="#0a0a0f" stop-opacity="0"/>
        </radialGradient>
        <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#181822"/>
            <stop offset="100%" stop-color="#0b0b10"/>
        </linearGradient>
        <linearGradient id="brandGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="{$color}"/>
            <stop offset="100%" stop-color="#ffffff" stop-opacity="0.8"/>
        </linearGradient>
    </defs>

    <!-- Fond sombre dégradé -->
    <rect width="600" height="600" fill="url(#bgGrad)"/>
    <circle cx="300" cy="240" r="260" fill="url(#halo)"/>

    <!-- Grille subtile d'arrière-plan -->
    <g stroke="rgba(255,255,255,0.03)" stroke-width="1">
        <line x1="0" y1="150" x2="600" y2="150"/>
        <line x1="0" y1="300" x2="600" y2="300"/>
        <line x1="0" y1="450" x2="600" y2="450"/>
        <line x1="150" y1="0" x2="150" y2="600"/>
        <line x1="300" y1="0" x2="300" y2="600"/>
        <line x1="450" y1="0" x2="450" y2="600"/>
    </g>

    <!-- Médaillon central d'icône -->
    <g transform="translate(300, 240)">
        <circle r="90" fill="rgba(255,255,255,0.04)" stroke="{$color}" stroke-width="2" stroke-opacity="0.4"/>
        <circle r="76" fill="rgba(0,0,0,0.4)"/>
        
        <!-- Lettre initiale / Monogramme élégant -->
        <text y="28" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="82" font-weight="800" fill="url(#brandGrad)" text-anchor="middle">
            {$initial}
        </text>
    </g>

    <!-- Badge Catégorie -->
    <g transform="translate(300, 420)">
        <rect x="-110" y="-16" width="220" height="32" rx="16" fill="rgba(255,255,255,0.06)" stroke="{$color}" stroke-width="1" stroke-opacity="0.6"/>
        <text y="5" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="12" font-weight="700" fill="{$color}" letter-spacing="1.5" text-anchor="middle">
            {$safeCat}
        </text>
    </g>

    <!-- Titre du Produit -->
    <text x="300" y="480" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="22" font-weight="700" fill="#ffffff" text-anchor="middle">
        {$safeTitle}
    </text>

    <!-- Signature Zanika -->
    <text x="300" y="525" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="13" font-weight="500" fill="#94a3b8" letter-spacing="2" text-anchor="middle">
        ZANIKA SIGNATURE
    </text>
</svg>
SVG;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return [16, 185, 129];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convertit un nombre entier en toutes lettres en français (ex: 1000 -> "mille", 15500 -> "quinze mille cinq cents")
     */
    public function frenchNumberToWords(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }

        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);
            $formatted = $fmt->format($n);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $this->fallbackFrenchSpellout($n);
    }

    /**
     * Repli pur PHP pour la transcription des nombres en français
     */
    private function fallbackFrenchSpellout(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }
        if ($n < 0) {
            return 'moins ' . $this->fallbackFrenchSpellout(-$n);
        }

        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        if ($n < 20) {
            return $units[$n];
        }
        if ($n < 100) {
            $t = intdiv($n, 10);
            $u = $n % 10;
            if ($t === 7) {
                return $u === 1 ? 'soixante-et-onze' : 'soixante-' . $units[10 + $u];
            }
            if ($t === 9) {
                return 'quatre-vingt-' . $units[10 + $u];
            }
            if ($t === 8 && $u === 0) {
                return 'quatre-vingts';
            }
            if ($u === 0) {
                return $tens[$t];
            }
            if ($u === 1 && $t < 8) {
                return $tens[$t] . '-et-un';
            }

            return $tens[$t] . '-' . $units[$u];
        }
        if ($n < 1000) {
            $h = intdiv($n, 100);
            $rem = $n % 100;
            $prefix = $h === 1 ? 'cent' : $units[$h] . ' cent' . ($rem === 0 ? 's' : '');

            return $rem === 0 ? $prefix : $prefix . ' ' . $this->fallbackFrenchSpellout($rem);
        }
        if ($n < 1000000) {
            $th = intdiv($n, 1000);
            $rem = $n % 1000;
            $prefix = $th === 1 ? 'mille' : $this->fallbackFrenchSpellout($th) . ' mille';

            return $rem === 0 ? $prefix : $prefix . ' ' . $this->fallbackFrenchSpellout($rem);
        }
        $mil = intdiv($n, 1000000);
        $rem = $n % 1000000;
        $prefix = $mil === 1 ? 'un million' : $this->fallbackFrenchSpellout($mil) . ' millions';

        return $rem === 0 ? $prefix : $prefix . ' ' . $this->fallbackFrenchSpellout($rem);
    }

    /**
     * Normalise un texte pour la synthèse vocale :
     * - Remplace les prix "1 000 FCFA" par "mille francs" (ne lit JAMAIS "un zéro zéro zéro")
     * - Remplace "-20%" par "moins 20 pour cent"
     * - Évite que les nombres avec séparateurs de milliers soient découpés chiffre par chiffre
     */
    public function normalizeSpeechText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // 1. Pourcentages
        $text = preg_replace('/-(\d+)\s*%/u', 'moins $1 pour cent', $text);
        $text = preg_replace('/(\d+)\s*%/u', '$1 pour cent', $text);

        // 2. Prix avec devise explicite (ex: 1 000 FCFA, 15000 CFA, 2 500 F, 500 francs, etc.)
        $currencyPattern = '/\b(\d[\d\s\xc2\xa0\x{202f}]*)\s*(FCFA|F\s*CFA|CFA|XOF|XAF|francs?|F)\b/iu';
        $text = preg_replace_callback($currencyPattern, function ($m) {
            $cleanDigits = preg_replace('/[\s\xc2\xa0\x{202f}]+/u', '', $m[1]);
            if (! is_numeric($cleanDigits)) {
                return $m[0];
            }
            $val = (int) $cleanDigits;
            $words = $this->frenchNumberToWords($val);

            // Formulation orale naturelle pour la voix-off publicitaire
            return $words . ' francs';
        }, $text);

        // 3. Nombres isolés formatés avec séparateurs de milliers (ex: 1 000, 15 000, 100 000)
        // Les numéros de téléphone (groupés par 2 ex: 90 12 34 56) ne sont PAS matchés
        $thousandsPattern = '/\b(\d{1,3}(?:[\s\xc2\xa0\x{202f}]\d{3})+)\b/u';
        $text = preg_replace_callback($thousandsPattern, function ($m) {
            $cleanDigits = preg_replace('/[\s\xc2\xa0\x{202f}]+/u', '', $m[1]);
            if (! is_numeric($cleanDigits)) {
                return $m[0];
            }

            return $this->frenchNumberToWords((int) $cleanDigits);
        }, $text);

        // 4. Sigles monétaires orphelins (ex: "prix en FCFA")
        $text = preg_replace('/(?<!francs\s)\b(?:FCFA|F\s*CFA)\b/iu', 'francs', $text);

        return trim($text);
    }

    /**
     * Synthèse vocale IA en français avec mise en cache disque (/api/tts)
     */
    public function tts(Request $request): Response
    {
        $rawText = trim((string) $request->input('text', ''));
        if (empty($rawText)) {
            return response('', 400);
        }

        $cleanText = $this->normalizeSpeechText(mb_substr(strip_tags($rawText), 0, 250));

        // Répertoire de cache pour les fichiers audio de voix-off
        $cacheDir = storage_path('app/tts');
        if (! file_exists($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/' . md5('fr_' . $cleanText) . '.mp3';

        if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
            $mp3 = file_get_contents($cacheFile);
        } else {
            $mp3 = $this->fetchGoogleTts($cleanText);

            if ($mp3 && strlen($mp3) > 300) {
                @file_put_contents($cacheFile, $mp3);
            } else {
                return response('', 502);
            }
        }

        return response($mp3, 200, $this->imageHeaders(31536000, 'audio/mpeg'));
    }

    /**
     * Pré-génère et met en cache un segment audio pour une latence nulle
     */
    public function warmTtsCache(string $text): bool
    {
        $rawText = trim($text);
        if (empty($rawText)) {
            return false;
        }

        $cleanText = $this->normalizeSpeechText(mb_substr(strip_tags($rawText), 0, 250));

        $cacheDir = storage_path('app/tts');
        if (! file_exists($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/' . md5('fr_' . $cleanText) . '.mp3';

        if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
            return true;
        }

        $mp3 = $this->fetchGoogleTts($cleanText);
        if ($mp3 && strlen($mp3) > 300) {
            @file_put_contents($cacheFile, $mp3);
            return true;
        }

        return false;
    }

    /**
     * Récupère le flux audio MP3 depuis Google Translate TTS avec plusieurs endpoints de secours
     */
    private function fetchGoogleTts(string $cleanText): ?string
    {
        $encoded = urlencode($cleanText);
        $candidates = [
            "https://translate.google.com/translate_tts?ie=UTF-8&tl=fr&client=tw-ob&q={$encoded}",
            "https://translate.googleapis.com/translate_tts?client=gtx&ie=UTF-8&tl=fr&q={$encoded}",
            "https://translate.google.com/translate_tts?ie=UTF-8&tl=fr-FR&client=tw-ob&q={$encoded}",
        ];

        foreach ($candidates as $url) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0\r\nReferer: https://translate.google.com/\r\n",
                        'timeout' => 5,
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
                $mp3 = @file_get_contents($url, false, $context);
                if ($mp3 && strlen($mp3) > 300) {
                    return $mp3;
                }
            } catch (\Throwable $e) {
                // Essayer candidat suivant
            }
        }

        return null;
    }
}

