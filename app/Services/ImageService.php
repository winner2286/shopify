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
