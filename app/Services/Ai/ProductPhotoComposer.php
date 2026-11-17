<?php

namespace App\Services\Ai;

class ProductPhotoComposer
{
    public const SCENES = [
        'podium' => ['title' => 'Podium en pierre', 'file' => 'luxury_podium.jpg', 'x' => 50, 'y' => 56, 'scale' => 48],
        'parquet' => ['title' => 'Loft et parquet', 'file' => 'shoes_floor.jpg', 'x' => 50, 'y' => 85, 'scale' => 70],
        'spa' => ['title' => 'Salle de bain et pierre', 'file' => 'cosmetics_spa.jpg', 'x' => 38, 'y' => 80, 'scale' => 50],
        'marble' => ['title' => 'Marbre clair', 'file' => 'jewelry_marble.jpg', 'x' => 50, 'y' => 58, 'scale' => 44],
        'dressing' => ['title' => 'Chambre et dressing', 'file' => 'apparel_rack.jpg', 'x' => 52, 'y' => 36, 'scale' => 32, 'top' => true, 'shadow' => 0],
    ];

    public function adjust(\GdImage $image, array $options): void
    {
        $brightness = (int) ($options['brightness'] ?? 0);
        $contrast = (int) ($options['contrast'] ?? 0);
        if ($brightness !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);
        }
        if ($contrast !== 0) {
            imagefilter($image, IMG_FILTER_CONTRAST, -$contrast);
        }
        if (! empty($options['sharpen'])) {
            imageconvolution($image, [[0, -.1, 0], [-.1, 1.4, -.1], [0, -.1, 0]], 1, 0);
        }
    }

    public function maskSubject(string $source, string $mask): string
    {
        $image = imagecreatefromstring($source);
        $alphaImage = imagecreatefromstring($mask);
        $scaled = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $alphaImage, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($alphaImage), imagesy($alphaImage));
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = max(($pixel >> 24) & 127, (imagecolorat($scaled, $x, $y) >> 24) & 127);
                imagesetpixel($image, $x, $y, ($alpha << 24) | ($pixel & 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($scaled);
        imagedestroy($alphaImage);

        return $binary;
    }

    public function compose(\GdImage $subject, string $template, array $options, ?string $background = null): \GdImage
    {
        $scene = self::SCENES[$template] ?? self::SCENES['parquet'];
        $backdrop = $background ? imagecreatefromstring($background) : imagecreatefromjpeg(public_path('images/ai_scenes/'.$scene['file']));
        $size = ProductStudioService::TARGET_SIZE;
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $ratio = max($size / imagesx($backdrop), $size / imagesy($backdrop));
        $width = (int) round(imagesx($backdrop) * $ratio);
        $height = (int) round(imagesy($backdrop) * $ratio);
        imagecopyresampled($canvas, $backdrop, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, imagesx($backdrop), imagesy($backdrop));
        imagedestroy($backdrop);

        $baseY = (int) round($size * ($options['position_y'] ?? $scene['y']) / 100);
        $extent = $size * ($options['scale'] ?? $scene['scale']) / 100;
        $topAnchor = ! $background && ! empty($scene['top']);
        $availableHeight = $topAnchor ? $size - $baseY - 20 : $baseY - 20;
        $ratio = min($extent / imagesx($subject), $extent / imagesy($subject), $availableHeight / imagesy($subject), ($size - 40) / imagesx($subject));
        $width = max(1, (int) round(imagesx($subject) * $ratio));
        $height = max(1, (int) round(imagesy($subject) * $ratio));
        $x = max(20, min($size - $width - 20, (int) round($size * ($options['position_x'] ?? $scene['x']) / 100 - $width / 2)));
        $y = $topAnchor ? $baseY : $baseY - $height;
        $opacity = (int) ($options['shadow'] ?? $scene['shadow'] ?? 22);
        if ($opacity > 0) {
            $shadow = imagecreatetruecolor(240, 60);
            imagealphablending($shadow, false);
            imagesavealpha($shadow, true);
            imagefill($shadow, 0, 0, imagecolorallocatealpha($shadow, 0, 0, 0, 127));
            for ($sy = 0; $sy < 60; $sy++) {
                for ($sx = 0; $sx < 240; $sx++) {
                    $distance = (($sx - 120) / 60) ** 2 + (($sy - 30) / 7) ** 2;
                    $alpha = 127 - (int) round($opacity * 1.27 * exp(-$distance / 2));
                    imagesetpixel($shadow, $sx, $sy, ($alpha << 24) | 0x0F0F14);
                }
            }
            imagecopyresampled($canvas, $shadow, $x, $y + $height - (int) ($width * .125), 0, 0, $width, max(1, (int) ($width * .25)), 240, 60);
            imagedestroy($shadow);
        }
        if ($topAnchor && in_array($options['category'] ?? '', [ProductStudioService::CATEGORY_CLOTHING_FLAT, ProductStudioService::CATEGORY_CLOTHING_HANGING], true)) {
            $center = $x + (int) round($width / 2);
            $metal = imagecolorallocate($canvas, 62, 57, 50);
            imagesetthickness($canvas, 3);
            imagearc($canvas, $center, $y - 15, 14, 20, 150, 360, $metal);
            imageline($canvas, $center + 7, $y - 15, $center + 7, $y + 18, $metal);
            imageline($canvas, $center + 7, $y + 18, $x + (int) ($width * .18), $y + (int) ($height * .2), $metal);
            imageline($canvas, $center + 7, $y + 18, $x + (int) ($width * .82), $y + (int) ($height * .2), $metal);
        }
        imagecopyresampled($canvas, $subject, $x, $y, 0, 0, $width, $height, imagesx($subject), imagesy($subject));

        return $canvas;
    }
}
