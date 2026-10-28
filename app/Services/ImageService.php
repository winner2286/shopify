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

        // Correction orientation EXIF (photos smartphone)
        $sourceImage = $this->autoRotate($sourceImage, $filePath);

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // 1. Image principale standardisée (max 1200x1200px)
        [$newWidth, $newHeight] = $this->calculateDimensions($origWidth, $origHeight, self::MAX_WIDTH, self::MAX_HEIGHT);
        $mainImage = imagecreatetruecolor($newWidth, $newHeight);
        $this->preserveTransparency($mainImage);
        imagecopyresampled($mainImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

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
        imagedestroy($mainImage);

        // 2. Miniature carrée optimisée (300x300 pour affichage fluide)
        $thumbFilename = 'thumb_' . $filename;
        $thumbFullPath = trim($directory, '/') . '/' . $thumbFilename;
        $thumbDiskPath = Storage::disk('public')->path($thumbFullPath);

        $thumbImage = $this->createSquareThumbnail($sourceImage, self::THUMB_SIZE);
        if (function_exists('imagewebp')) {
            imagewebp($thumbImage, $thumbDiskPath, 80);
        } else {
            imagejpeg($thumbImage, $thumbDiskPath, 80);
        }
        imagedestroy($thumbImage);

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
}
