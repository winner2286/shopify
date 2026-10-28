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
     * Sert un fichier média du stockage public ou le régénère dynamiquement
     */
    public function serve(Request $request, string $path): BinaryFileResponse|Response
    {
        // Nettoyage de sécurité contre les traversées de répertoires
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        $diskPath = Storage::disk('public')->path($cleanPath);

        // 1. Si le fichier existe déjà physiquement sur disque, le servir immédiatement
        if (file_exists($diskPath) && filesize($diskPath) > 0) {
            return response()->file($diskPath, [
                'Cache-Control' => 'public, max-age=2592000',
            ]);
        }

        // 2. Recherche en base de données pour restauration persistante
        $productImage = ProductImage::where('path', $cleanPath)
            ->orWhere('thumbnail_path', $cleanPath)
            ->orWhere('path', 'like', '%' . basename($cleanPath))
            ->orWhere('thumbnail_path', 'like', '%' . basename($cleanPath))
            ->first();

        if ($productImage && ! empty($productImage->image_data)) {
            $binary = @base64_decode($productImage->image_data);
            if ($binary) {
                @mkdir(dirname($diskPath), 0755, true);
                @file_put_contents($diskPath, $binary);
                if (file_exists($diskPath) && filesize($diskPath) > 0) {
                    return response()->file($diskPath, [
                        'Cache-Control' => 'public, max-age=2592000',
                    ]);
                }
                // Si l'écriture sur disque est restreinte, servir directement depuis la mémoire
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($binary) ?: 'image/webp';
                return response($binary, 200, [
                    'Content-Type' => $mimeType,
                    'Cache-Control' => 'public, max-age=2592000',
                ]);
            }
        }

        // 3. Fichier manquant (ex. redémarrage Render sans persistance) : génération dynamique
        $product = $productImage?->product;
        if (! $product) {
            if (preg_match('/shops\/(\d+)/', $cleanPath, $m)) {
                $product = Product::where('shop_id', $m[1])->first();
            }
        }

        // Tenter de générer un fichier WebP réel via GD
        if ($this->generateWebPPlaceholder($product, $diskPath)) {
            return response()->file($diskPath, [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // Fallback vectoriel SVG pur
        $svg = $this->generateSvgPlaceholder($product);
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Endpoint API public pour générer une image de substitution
     */
    public function placeholder(Request $request): Response
    {
        $product = null;
        if ($request->filled('id')) {
            $product = Product::find($request->id);
        }

        $title = $request->input('title', $product?->title ?? 'Article Signature');
        $cat = $request->input('cat', $product?->categories?->first()?->name ?? 'Collection');
        $color = $request->input('color', $product?->shop?->brand_color ?? '#10b981');

        $svg = $this->buildSvgContent($title, $cat, $color);
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
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

            // Mention Boutiq
            $shopName = 'Boutiq - ' . ($product?->shop?->name ?: 'Commerce');
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
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="100%" height="100%">
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

    <!-- Signature Boutiq -->
    <text x="300" y="525" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="13" font-weight="500" fill="#94a3b8" letter-spacing="2" text-anchor="middle">
        BOUTIQ SIGNATURE
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
}
