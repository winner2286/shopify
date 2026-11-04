<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public const MAX_WIDTH = 1200;
    public const MAX_HEIGHT = 1200;
    public const WEBP_QUALITY = 82;
    public const THUMB_SIZE = 300;

    /**
     * Traite, redimensionne et convertit une image uploadée en WebP optimisé
     * Retourne ['path' => ..., 'thumbnail_path' => ..., 'size_bytes' => ...]
     */
    public function processAndStore(UploadedFile $file, string $directory): array
    {
        $realPath = $file->getRealPath();
        $mime = $file->getClientMimeType() ?: $file->getMimeType();

        return $this->processFromPath($realPath, $directory, $mime, $file->getSize());
    }

    /**
     * Traite une image depuis un chemin de fichier local
     */
    public function processFromPath(string $filePath, string $directory, ?string $mime = null, int $originalSize = 0): array
    {
        if (! file_exists($filePath)) {
            return [
                'path' => '',
                'thumbnail_path' => null,
                'size_bytes' => 0,
            ];
        }

        if (! function_exists('imagecreatetruecolor')) {
            // Fallback si l'extension GD est désactivée
            $filename = 'img_' . Str::random(20) . '.' . (pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg');
            $fullPath = trim($directory, '/') . '/' . $filename;
            $content = file_get_contents($filePath);
            Storage::disk('public')->put($fullPath, $content);
            return [
                'path' => $fullPath,
                'thumbnail_path' => null,
                'size_bytes' => filesize($filePath),
                'image_data' => base64_encode($content),
            ];
        }

        $sourceImage = $this->createImageFromFile($filePath, $mime);

        if (! $sourceImage) {
            $filename = 'img_' . Str::random(20) . '.' . (pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg');
            $fullPath = trim($directory, '/') . '/' . $filename;
            $content = file_get_contents($filePath);
            Storage::disk('public')->put($fullPath, $content);
            return [
                'path' => $fullPath,
                'thumbnail_path' => null,
                'size_bytes' => filesize($filePath),
                'image_data' => base64_encode($content),
            ];
        }

        // 1. Correction orientation EXIF (photos smartphone)
        $sourceImage = $this->autoRotate($sourceImage, $filePath);

        // 2. Retouche automatique IA & Studio : luminosité intelligente, contraste et netteté
        $sourceImage = $this->enhancePhotoQuality($sourceImage);

        // 3. Cadrage e-commerce studio carré (1:1) propre : centre le produit et évite les coupures
        $targetSize = min(self::MAX_WIDTH, self::MAX_HEIGHT);
        $mainImage = $this->formatStudioSquare($sourceImage, $targetSize);

        $filename = 'img_' . Str::random(20) . '.webp';
        $fullPath = trim($directory, '/') . '/' . $filename;
        $diskPath = Storage::disk('public')->path($fullPath);

        $dir = dirname($diskPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (function_exists('imagewebp')) {
            imagewebp($mainImage, $diskPath, self::WEBP_QUALITY);
        } else {
            $filename = 'img_' . Str::random(20) . '.jpg';
            $fullPath = trim($directory, '/') . '/' . $filename;
            $diskPath = Storage::disk('public')->path($fullPath);
            imagejpeg($mainImage, $diskPath, 85);
        }

        // 4. Miniature carrée optimisée (300x300 pour affichage fluide)
        $thumbFilename = 'thumb_' . $filename;
        $thumbFullPath = trim($directory, '/') . '/' . $thumbFilename;
        $thumbDiskPath = Storage::disk('public')->path($thumbFullPath);

        $thumbImage = $this->createSquareThumbnail($mainImage, self::THUMB_SIZE);
        if (function_exists('imagewebp')) {
            imagewebp($thumbImage, $thumbDiskPath, 80);
        } else {
            imagejpeg($thumbImage, $thumbDiskPath, 80);
        }
        imagedestroy($thumbImage);

        imagedestroy($mainImage);
        imagedestroy($sourceImage);

        $fileSize = file_exists($diskPath) ? filesize($diskPath) : $originalSize;
        $savedFile = file_exists($thumbDiskPath) ? $thumbDiskPath : (file_exists($diskPath) ? $diskPath : null);
        $imageData = $savedFile ? base64_encode(file_get_contents($savedFile)) : null;

        return [
            'path' => $fullPath,
            'thumbnail_path' => $thumbFullPath,
            'size_bytes' => $fileSize,
            'image_data' => $imageData,
        ];
    }

    /**
     * Redimensionne et optimise une image déjà existante sur le disque public
     */
    public function optimizeExisting(string $relativePath): ?array
    {
        $diskPath = Storage::disk('public')->path($relativePath);
        if (! file_exists($diskPath)) {
            return null;
        }

        $directory = dirname($relativePath);
        return $this->processFromPath($diskPath, $directory);
    }

    private function createImageFromFile(string $path, ?string $mime = null)
    {
        if (empty($mime) && function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
        }

        switch (strtolower((string) $mime)) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            case 'image/gif':
                return @imagecreatefromgif($path);
            default:
                // Tentative détection via signature
                $img = @imagecreatefromjpeg($path);
                if ($img) return $img;
                $img = @imagecreatefrompng($path);
                if ($img) return $img;
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($path);
                    if ($img) return $img;
                }
                return null;
        }
    }

    private function autoRotate($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);
            if (! empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $rotated = imagerotate($image, 180, 0);
                        imagedestroy($image);
                        return $rotated;
                    case 6:
                        $rotated = imagerotate($image, -90, 0);
                        imagedestroy($image);
                        return $rotated;
                    case 8:
                        $rotated = imagerotate($image, 90, 0);
                        imagedestroy($image);
                        return $rotated;
                }
            }
        } catch (\Throwable $e) {
            // Ignorer si exif non lisible
        }

        return $image;
    }

    private function calculateDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function createSquareThumbnail($source, int $size)
    {
        $w = imagesx($source);
        $h = imagesy($source);

        $thumb = imagecreatetruecolor($size, $size);
        $this->preserveTransparency($thumb);

        // Découpage centré du carré
        if ($w > $h) {
            $srcX = (int) round(($w - $h) / 2);
            $srcY = 0;
            $srcW = $h;
            $srcH = $h;
        } else {
            $srcX = 0;
            $srcY = (int) round(($h - $w) / 2);
            $srcW = $w;
            $srcH = $w;
        }

        imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, $size, $size, $srcW, $srcH);

        return $thumb;
    }

    private function preserveTransparency($image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }

    /**
     * Améliore automatiquement la luminosité, le contraste et la netteté
     * pour rendre les photos de produits bien présentables et professionnelles.
     */
    public function enhancePhotoQuality($image)
    {
        if (! is_resource($image) && ! ($image instanceof \GdImage)) {
            return $image;
        }

        try {
            // 1. Analyse intelligente de la luminosité moyenne
            $avgLuminance = $this->calculateAverageLuminance($image);

            if (function_exists('imagefilter')) {
                // Si la photo est sombre (prise en intérieur, boutique mal éclairée, ombres)
                if ($avgLuminance < 118) {
                    $boost = (int) round(min(22, max(6, (120 - $avgLuminance) * 0.35)));
                    @imagefilter($image, IMG_FILTER_BRIGHTNESS, $boost);
                } elseif ($avgLuminance > 225) {
                    // Si légèrement surexposée / reflet trop fort
                    @imagefilter($image, IMG_FILTER_BRIGHTNESS, -6);
                }

                // Amélioration du contraste (en GD, valeur négative = augmentation du contraste)
                // -8 donne un contraste chaleureux et vibrant sans brûler les couleurs
                @imagefilter($image, IMG_FILTER_CONTRAST, -8);
            }

            // 2. Renforcement de la netteté (élimine le micro-flou des photos smartphone prises à main levée)
            if (function_exists('imageconvolution')) {
                $sharpenMatrix = [
                    [-0.15, -0.20, -0.15],
                    [-0.20,  2.40, -0.20],
                    [-0.15, -0.20, -0.15],
                ];
                @imageconvolution($image, $sharpenMatrix, 1.0, 0);
            }
        } catch (\Throwable $e) {
            // Fail-safe : ne jamais bloquer l'upload en cas d'erreur de filtre
        }

        return $image;
    }

    /**
     * Calcule la luminance perçue moyenne d'une image (0 = noir, 255 = blanc)
     */
    private function calculateAverageLuminance($image): float
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= 0 || $h <= 0) return 128.0;

        // Échantillonnage rapide sur une miniature 30x30
        $sampleW = min(30, $w);
        $sampleH = min(30, $h);
        $sample = imagecreatetruecolor($sampleW, $sampleH);
        imagecopyresampled($sample, $image, 0, 0, 0, 0, $sampleW, $sampleH, $w, $h);

        $totalLuminance = 0;
        $pixelCount = $sampleW * $sampleH;

        for ($x = 0; $x < $sampleW; $x++) {
            for ($y = 0; $y < $sampleH; $y++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                // Formule standard de luminance perçue ITU-R BT.601
                $totalLuminance += (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
            }
        }

        imagedestroy($sample);

        return $pixelCount > 0 ? ($totalLuminance / $pixelCount) : 128.0;
    }

    /**
     * Cadre la photo dans un carré Studio E-Commerce parfait (1:1)
     * Évite que les vêtements ou chaussures en format portrait (3:4 ou 9:16) soient tronqués.
     */
    public function formatStudioSquare($source, int $targetSize = 1000)
    {
        $origW = imagesx($source);
        $origH = imagesy($source);

        $canvas = imagecreatetruecolor($targetSize, $targetSize);

        // Détection de transparence
        $hasAlpha = $this->hasTransparency($source);

        if ($hasAlpha) {
            $this->preserveTransparency($canvas);
        } else {
            // Fond studio blanc épuré pour e-commerce professionnel
            $studioBg = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetSize, $targetSize, $studioBg);
        }

        // Marge de respiration studio (4% de chaque côté pour une présentation aérée)
        $usableSize = (int) round($targetSize * 0.94);
        $padding = (int) round(($targetSize - $usableSize) / 2);

        // Calcul des dimensions proportionnelles pour entrer dans l'espace utile
        $ratio = min($usableSize / $origW, $usableSize / $origH);
        $newW = max(1, (int) round($origW * $ratio));
        $newH = max(1, (int) round($origH * $ratio));

        // Centrage parfait
        $dstX = $padding + (int) round(($usableSize - $newW) / 2);
        $dstY = $padding + (int) round(($usableSize - $newH) / 2);

        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $newW, $newH, $origW, $origH);

        return $canvas;
    }

    /**
     * Rogne automatiquement les marges transparentes autour de l'objet pour un cadrage parfait.
     */
    public function cropTransparent($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w; $maxX = 0;
        $minY = $h; $maxY = 0;

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
                if ($alpha < 120) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }

        if ($minX > $maxX || $minY > $maxY) {
            return $img;
        }

        $pad = 6;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $cropW = min($w - $minX, ($maxX - $minX + 1) + ($pad * 2));
        $cropH = min($h - $minY, ($maxY - $minY + 1) + ($pad * 2));

        $cropped = imagecrop($img, ['x' => $minX, 'y' => $minY, 'width' => $cropW, 'height' => $cropH]);
        return $cropped ?: $img;
    }

    /**
     * Compose un packshot studio e-commerce professionnel à partir d'un PNG détouré.
     * Conserve 100% l'objet réel sans aucune déformation ou hallucination.
     */
    public function buildStudioPackshot($cutoutGd, string $style = 'white', int $targetSize = 1000)
    {
        $cutoutGd = $this->cropTransparent($cutoutGd);

        $origW = imagesx($cutoutGd);
        $origH = imagesy($cutoutGd);

        $usableSize = (int) round($targetSize * 0.84);
        $padding = (int) round(($targetSize - $usableSize) / 2);

        $ratio = min($usableSize / $origW, $usableSize / $origH);
        $newW = max(1, (int) round($origW * $ratio));
        $newH = max(1, (int) round($origH * $ratio));

        $dstX = (int) round(($targetSize - $newW) / 2);
        $dstY = (int) round(($targetSize - $newH) / 2);

        $canvas = imagecreatetruecolor($targetSize, $targetSize);

        $shadowY = $dstY + $newH + 2;

        if ($style === 'white') {
            // Fond blanc pur studio marketplace standard (Amazon, Zara, Shopify)
            $bg = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetSize, $targetSize, $bg);

            // Double ombre de contact réaliste
            $shadowCore = imagecolorallocatealpha($canvas, 30, 30, 35, 105);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY, (int) round($newW * 0.74), 18, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 50, 50, 55, 118);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY + 4, (int) round($newW * 0.88), 32, $shadowSoft);
        } elseif ($style === 'ambient_dark') {
            // Fond studio sombre graphite luxueux
            for ($y = 0; $y < $targetSize; $y++) {
                $ratioY = $y / $targetSize;
                $r = (int) round(28 - ($ratioY * 12));
                $g = (int) round(28 - ($ratioY * 12));
                $b = (int) round(32 - ($ratioY * 14));
                $col = imagecolorallocate($canvas, $r, $g, $b);
                imageline($canvas, 0, $y, $targetSize, $y, $col);
            }
            $shadowCore = imagecolorallocatealpha($canvas, 5, 5, 5, 85);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY, (int) round($newW * 0.74), 18, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 10, 10, 10, 110);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY + 4, (int) round($newW * 0.88), 32, $shadowSoft);
        } else {
            // Studio Ambiance Doux Ivoire / Perle épuré
            for ($y = 0; $y < $targetSize; $y++) {
                $ratioY = $y / $targetSize;
                $r = (int) round(250 - ($ratioY * 12));
                $g = (int) round(248 - ($ratioY * 14));
                $b = (int) round(246 - ($ratioY * 16));
                $col = imagecolorallocate($canvas, $r, $g, $b);
                imageline($canvas, 0, $y, $targetSize, $y, $col);
            }
            $shadowCore = imagecolorallocatealpha($canvas, 30, 25, 25, 105);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY, (int) round($newW * 0.74), 18, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 50, 45, 45, 118);
            imagefilledellipse($canvas, (int) round($targetSize / 2), $shadowY + 4, (int) round($newW * 0.88), 32, $shadowSoft);
        }

        // Incrustation de l'objet réel non déformé
        imagecopyresampled($canvas, $cutoutGd, $dstX, $dstY, 0, 0, $newW, $newH, $origW, $origH);

        return $canvas;
    }

    /**
     * Compose une mise en scène lifestyle photoréaliste adaptée à la catégorie du produit
     * (Vêtements sur portant dans un loft, Cosmétiques sur marbre/spa, Chaussures sur parquet, Bijoux sur marbre).
     * Conserve 100% le produit réel avec ombres et intégration naturelle sans altération.
     */
    public function buildLifestyleScene($cutoutGd, string $category = 'general', int $targetSize = 1000)
    {
        $cutoutGd = $this->cropTransparent($cutoutGd);
        $cW = imagesx($cutoutGd);
        $cH = imagesy($cutoutGd);

        $cat = strtolower($category);
        $sceneFile = 'luxury_podium.jpg';
        $style = 'general';
        $title = 'Ambiance Podium Luxe';

        $isShoes = str_contains($cat, 'shoe') || str_contains($cat, 'chaussure') || str_contains($cat, 'talon')
            || str_contains($cat, 'escarpin') || str_contains($cat, 'mule') || str_contains($cat, 'sandale')
            || str_contains($cat, 'sneaker') || str_contains($cat, 'basket') || str_contains($cat, 'botte')
            || str_contains($cat, 'claquette') || str_contains($cat, 'mocassin');

        $isCosmetics = str_contains($cat, 'cosmet') || str_contains($cat, 'beaute') || str_contains($cat, 'parfum')
            || str_contains($cat, 'creme') || str_contains($cat, 'spray') || str_contains($cat, 'soin')
            || str_contains($cat, 'hygiene') || str_contains($cat, 'serum') || str_contains($cat, 'desodorisant');

        $isJewelry = str_contains($cat, 'jewelry') || str_contains($cat, 'bijou') || str_contains($cat, 'montre')
            || str_contains($cat, 'watch') || str_contains($cat, 'collier') || str_contains($cat, 'bague')
            || str_contains($cat, 'bracelet');

        $isHangingApparel = str_contains($cat, 'clothing_hanging') || str_contains($cat, 'apparel_hanging')
            || str_contains($cat, 'hanging') || str_contains($cat, 'cintre')
            || str_contains($cat, 'veste') || str_contains($cat, 'manteau') || str_contains($cat, 'robe')
            || str_contains($cat, 'chemise') || str_contains($cat, 'blazer') || str_contains($cat, 'trench')
            || str_contains($cat, 'costume');

        if ($isShoes) {
            $sceneFile = 'shoes_floor.jpg';
            $style = 'shoes';
            $title = 'Shooting Parquet Chêne';
        } elseif ($isCosmetics) {
            $sceneFile = 'cosmetics_spa.jpg';
            $style = 'cosmetics';
            $title = 'Ambiance Bain & Spa Travertin';
        } elseif ($isJewelry) {
            $sceneFile = 'jewelry_marble.jpg';
            $style = 'jewelry';
            $title = 'Plateau Marbre Carrara';
        } elseif ($isHangingApparel) {
            $sceneFile = 'apparel_rack.jpg';
            $style = 'apparel';
            $title = 'Mise en scène Dressing Loft';
        } else {
            $sceneFile = 'luxury_podium.jpg';
            $style = 'general';
            $title = 'Ambiance Podium Showroom';
        }

        $scenePath = public_path('images/ai_scenes/' . $sceneFile);
        if (! file_exists($scenePath)) {
            return [
                'canvas' => $this->buildStudioPackshot($cutoutGd, 'ambient_light', $targetSize),
                'title' => 'Studio Ambiance Douce',
            ];
        }

        $bg = @imagecreatefromjpeg($scenePath);
        if (! $bg) {
            return [
                'canvas' => $this->buildStudioPackshot($cutoutGd, 'ambient_light', $targetSize),
                'title' => 'Studio Ambiance Douce',
            ];
        }

        $bgW = imagesx($bg);
        $bgH = imagesy($bg);

        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        imagecopyresampled($canvas, $bg, 0, 0, 0, 0, $targetSize, $targetSize, $bgW, $bgH);
        imagedestroy($bg);

        if ($style === 'apparel') {
            // Portant dans loft contemporain (Vêtements suspendus uniquement)
            $targetW = (int) round($targetSize * 0.32);
            $targetH = (int) round($cH * ($targetW / $cW));
            if ($targetH > (int) round($targetSize * 0.52)) {
                $targetH = (int) round($targetSize * 0.52);
                $targetW = (int) round($cW * ($targetH / $cH));
            }
            $dstX = (int) round($targetSize * 0.38);
            $dstY = (int) round($targetSize * 0.315);

            $shadowCore = imagecolorallocatealpha($canvas, 60, 45, 30, 95);
            imagefilledellipse($canvas, $dstX + (int)($targetW / 2) + 20, (int) round($targetSize * 0.83), (int)($targetW * 0.85), 32, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 80, 60, 40, 115);
            imagefilledellipse($canvas, $dstX + (int)($targetW / 2) + 25, (int) round($targetSize * 0.835), (int)($targetW * 1.05), 52, $shadowSoft);

            imagecopyresampled($canvas, $cutoutGd, $dstX, $dstY, 0, 0, $targetW, $targetH, $cW, $cH);
        } elseif ($style === 'cosmetics') {
            // Comptoir spa travertin
            $prod = $cutoutGd;
            if ($cW > ($cH * 1.5)) {
                $rotated = imagerotate($cutoutGd, 270, imagecolorallocatealpha($cutoutGd, 0, 0, 0, 127));
                imagealphablending($rotated, false);
                imagesavealpha($rotated, true);
                $prod = $this->cropTransparent($rotated);
                $cW = imagesx($prod);
                $cH = imagesy($prod);
            }

            $targetH = (int) round($targetSize * 0.48);
            $targetW = (int) round($cW * ($targetH / $cH));
            $dstX = (int) round($targetSize * 0.42);
            $dstY = (int) round($targetSize * 0.24);
            $bottomY = $dstY + $targetH;

            $shadowCore = imagecolorallocatealpha($canvas, 60, 50, 40, 95);
            imagefilledellipse($canvas, $dstX + (int)($targetW / 2) + 12, $bottomY - 4, (int)($targetW * 0.88), 18, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 90, 75, 60, 115);
            imagefilledellipse($canvas, $dstX + (int)($targetW / 2) + 24, $bottomY + 2, (int)($targetW * 1.1), 32, $shadowSoft);

            imagecopyresampled($canvas, $prod, $dstX, $dstY, 0, 0, $targetW, $targetH, $cW, $cH);
        } elseif ($style === 'shoes') {
            // Chaussures posées naturellement sur le parquet chêne avec ombres de contact
            $targetW = (int) round($targetSize * 0.56);
            $targetH = (int) round($cH * ($targetW / $cW));
            if ($targetH > (int) round($targetSize * 0.50)) {
                $targetH = (int) round($targetSize * 0.50);
                $targetW = (int) round($cW * ($targetH / $cH));
            }
            $dstX = (int) round(($targetSize - $targetW) / 2) + 15;
            $dstY = (int) round($targetSize * 0.44);
            $bottomY = $dstY + $targetH;

            // Ombre de contact au sol nette et réaliste
            $shadowCore = imagecolorallocatealpha($canvas, 40, 28, 18, 95);
            imagefilledellipse($canvas, $dstX + (int)($targetW * 0.50), $bottomY - 3, (int)($targetW * 0.82), 20, $shadowCore);

            // Ombre diffuse d'ambiance projetée vers la droite selon la lumière de la fenêtre
            $shadowSoft = imagecolorallocatealpha($canvas, 70, 52, 32, 115);
            imagefilledellipse($canvas, $dstX + (int)($targetW * 0.54) + 12, $bottomY + 2, (int)($targetW * 0.96), 36, $shadowSoft);

            imagecopyresampled($canvas, $cutoutGd, $dstX, $dstY, 0, 0, $targetW, $targetH, $cW, $cH);
        } elseif ($style === 'jewelry') {
            $targetW = (int) round($targetSize * 0.42);
            $targetH = (int) round($cH * ($targetW / $cW));
            if ($targetH > (int) round($targetSize * 0.40)) {
                $targetH = (int) round($targetSize * 0.40);
                $targetW = (int) round($cW * ($targetH / $cH));
            }
            $dstX = (int) round(($targetSize - $targetW) / 2);
            $dstY = (int) round($targetSize * 0.48) - (int)($targetH / 2);

            $shadowCore = imagecolorallocatealpha($canvas, 30, 30, 35, 100);
            imagefilledellipse($canvas, (int)($targetSize / 2), $dstY + $targetH - 2, (int)($targetW * 0.8), 20, $shadowCore);

            imagecopyresampled($canvas, $cutoutGd, $dstX, $dstY, 0, 0, $targetW, $targetH, $cW, $cH);
        } else {
            $targetH = (int) round($targetSize * 0.45);
            $targetW = (int) round($cW * ($targetH / $cH));
            if ($targetW > (int) round($targetSize * 0.55)) {
                $targetW = (int) round($targetSize * 0.55);
                $targetH = (int) round($cH * ($targetW / $cW));
            }
            $dstX = (int) round(($targetSize - $targetW) / 2);
            // Calage parfait sur le sommet du cylindre en pierre (y = 534)
            $dstY = 534 - $targetH + 4;

            $shadowCore = imagecolorallocatealpha($canvas, 45, 40, 35, 95);
            imagefilledellipse($canvas, (int)($targetSize / 2), 534, (int)($targetW * 0.85), 20, $shadowCore);
            $shadowSoft = imagecolorallocatealpha($canvas, 70, 60, 50, 115);
            imagefilledellipse($canvas, (int)($targetSize / 2), 537, (int)($targetW * 1.05), 34, $shadowSoft);

            imagecopyresampled($canvas, $cutoutGd, $dstX, $dstY, 0, 0, $targetW, $targetH, $cW, $cH);
        }

        return [
            'canvas' => $canvas,
            'title' => $title,
        ];
    }

    private function hasTransparency($image): bool
    {
        if (! function_exists('imagecolorat')) return false;
        $w = imagesx($image);
        $h = imagesy($image);
        $points = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        foreach ($points as [$x, $y]) {
            if ($x >= 0 && $y >= 0 && $x < $w && $y < $h) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha > 10) return true;
            }
        }
        return false;
    }
}
