<?php

namespace App\Services\Ai;

class ProductPhotoComposer
{
    public const SCENES = [
        'podium' => ['title' => 'Podium en pierre', 'file' => 'luxury_podium.jpg', 'x' => 50, 'y' => 55, 'scale' => 50, 'width' => 28, 'shadow' => 22, 'light' => 1],
        'parquet' => ['title' => 'Loft et parquet', 'file' => 'shoes_floor.jpg', 'x' => 54, 'y' => 81, 'scale' => 70, 'shadow' => 25, 'light' => -1],
        'spa' => ['title' => 'Salle de bain et pierre', 'file' => 'cosmetics_spa.jpg', 'x' => 45, 'y' => 77, 'scale' => 54, 'width' => 48, 'shadow' => 24, 'light' => -1],
        'marble' => ['title' => 'Marbre clair', 'file' => 'jewelry_marble.jpg', 'x' => 50, 'y' => 51, 'scale' => 57, 'anchor' => 'center', 'shadow' => 18, 'light' => 1],
        'dressing' => ['title' => 'Chambre et dressing', 'file' => 'apparel_rack.jpg', 'x' => 49, 'y' => 27, 'scale' => 62, 'width' => 40, 'anchor' => 'top', 'shadow' => 0],
    ];

    private const DRESSING_CROP = ['x' => 245, 'y' => 190, 'size' => 620];

    /** @param array{x:int,y:int,width:int,height:int}|null $frame */
    public function cropSource(\GdImage $source, array $options, ?array &$frame = null): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $frame = ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height];
        $left = (int) round($width * ($options['crop_left'] ?? 0) / 100);
        $top = (int) round($height * ($options['crop_top'] ?? 0) / 100);
        $right = (int) round($width * ($options['crop_right'] ?? 0) / 100);
        $bottom = (int) round($height * ($options['crop_bottom'] ?? 0) / 100);

        if ($left + $top + $right + $bottom === 0 && ! empty($options['auto_crop'])) {
            $top = $this->darkBorderDepth($source, false);
            $bottom = $this->darkBorderDepth($source, true);
        }

        if ($left + $top + $right + $bottom === 0 || $left + $right >= $width || $top + $bottom >= $height) {
            return $source;
        }

        $cropFrame = ['x' => $left, 'y' => $top, 'width' => $width - $left - $right, 'height' => $height - $top - $bottom];
        $crop = imagecrop($source, $cropFrame);
        if ($crop === false) {
            return $source;
        }
        $frame = $cropFrame;
        imagealphablending($crop, false);
        imagesavealpha($crop, true);

        return $crop;
    }

    private function darkBorderDepth(\GdImage $source, bool $fromBottom): int
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $minimum = max(5, (int) round($height * .015));
        $edgeSample = max($minimum, (int) round($height * .035));
        $limit = (int) round($height * .30);
        $confirmation = max(3, (int) round($height * .012));
        $darkEdgeRows = 0;
        $photoRows = 0;
        for ($offset = 0; $offset <= $limit; $offset++) {
            $y = $fromBottom ? $height - 1 - $offset : $offset;
            $dark = 0;
            $photo = 0;
            for ($sample = 1; $sample <= 39; $sample++) {
                $pixel = imagecolorsforindex($source, imagecolorat($source, (int) ($width * $sample / 40), $y));
                $high = max($pixel['red'], $pixel['green'], $pixel['blue']);
                $low = min($pixel['red'], $pixel['green'], $pixel['blue']);
                if ($pixel['alpha'] === 0 && $high <= 46 && $high - $low <= 30) {
                    $dark++;
                }
                if ($pixel['alpha'] === 0 && ($high >= 88 || $high - $low >= 31)) {
                    $photo++;
                }
            }
            if ($offset < $edgeSample && $dark >= 33) {
                $darkEdgeRows++;
            }
            if ($offset >= $edgeSample && $darkEdgeRows < $edgeSample * .65) {
                return 0;
            }
            $photoRows = $photo >= 30 ? $photoRows + 1 : 0;
            if ($photoRows === $confirmation) {
                $firstPhotoRow = $offset - $photoRows + 1;

                return $firstPhotoRow >= $minimum && $darkEdgeRows >= min($firstPhotoRow, $edgeSample) * .65 ? $firstPhotoRow : 0;
            }
        }

        return 0;
    }

    public function frameRetouch(\GdImage $source, array $options): \GdImage
    {
        $this->adjust($source, $options);
        $size = ProductStudioService::TARGET_SIZE;
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min($size / $width, $size / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, 0xF7F5F1);
        imagecopyresampled($canvas, $source, (int) (($size - $newWidth) / 2), (int) (($size - $newHeight) / 2), 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    public function adjust(\GdImage $image, array $options): void
    {
        if (! empty($options['auto_enhance'])) {
            $this->improveExposure($image);
        }
        $brightness = (int) ($options['brightness'] ?? 0);
        $contrast = (int) ($options['contrast'] ?? 0);
        if ($brightness !== 0) {
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);
        }
        if ($contrast !== 0) {
            imagefilter($image, IMG_FILTER_CONTRAST, -$contrast);
        }
        if ($options['sharpen'] ?? $options['auto_enhance'] ?? false) {
            $sharpness = empty($options['sharpen']) ? .06 : .1;
            imageconvolution($image, [[0, -$sharpness, 0], [-$sharpness, 1 + $sharpness * 4, -$sharpness], [0, -$sharpness, 0]], 1, 0);
        }
    }

    private function improveExposure(\GdImage $image): void
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        $width = imagesx($image);
        $height = imagesy($image);
        $luminance = [];
        $step = max(1, (int) round(max($width, $height) / 50));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $pixel = imagecolorat($image, $x, $y);
                if ((($pixel >> 24) & 127) < 18) {
                    $luminance[] = $this->luminance($pixel);
                }
            }
        }
        if ($luminance === []) {
            return;
        }
        sort($luminance, SORT_NUMERIC);
        $last = count($luminance) - 1;
        $median = $luminance[(int) ($last * .5)];
        $range = $luminance[(int) ($last * .93)] - $luminance[(int) ($last * .07)];
        $exposure = max(-11, min(22, (116 - $median) * .29));
        $contrast = max(.025, min(.10, (180 - $range) / 1000));
        $contrastMidpoint = max(75, min(128, $median + 24));
        $tone = [];
        for ($level = 0; $level < 256; $level++) {
            $normalized = $level / 255;
            $tone[$level] = (int) round(($exposure + ($level - $contrastMidpoint) * $contrast) * 4 * $normalized * (1 - $normalized));
        }
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                if ((($pixel >> 24) & 127) === 127) {
                    continue;
                }
                imagesetpixel($image, $x, $y, $this->shiftLuminance($pixel, $tone[$this->luminance($pixel)]));
            }
        }
    }

    private function shiftLuminance(int $pixel, int $shift): int
    {
        $red = ($pixel >> 16) & 255;
        $green = ($pixel >> 8) & 255;
        $blue = $pixel & 255;
        $shift = max(-min($red, $green, $blue), min(255 - max($red, $green, $blue), $shift));

        return ($pixel & 0x7F000000) | (($red + $shift) << 16) | (($green + $shift) << 8) | ($blue + $shift);
    }

    private function luminance(int $pixel): int
    {
        return (int) ((54 * (($pixel >> 16) & 255) + 183 * (($pixel >> 8) & 255) + 19 * ($pixel & 255)) / 256);
    }

    public function maskSubject(string $source, string $mask, bool $refineEdges = false): string
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
                $maskAlpha = (imagecolorat($scaled, $x, $y) >> 24) & 127;
                $alpha = max(($pixel >> 24) & 127, $maskAlpha);
                if ($refineEdges && $maskAlpha >= 30 && $maskAlpha <= 105) {
                    $pixel = $this->refineEdgePixel($image, $scaled, $x, $y, $pixel, $maskAlpha);
                }
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

    private function refineEdgePixel(\GdImage $image, \GdImage $mask, int $x, int $y, int $pixel, int $alpha): int
    {
        foreach ([[0, -1], [-1, 0], [1, 0], [0, 1], [0, -2], [-2, 0], [2, 0], [0, 2]] as [$dx, $dy]) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            if ($nx < 0 || $ny < 0 || $nx >= imagesx($mask) || $ny >= imagesy($mask) || ((imagecolorat($mask, $nx, $ny) >> 24) & 127) > 12) {
                continue;
            }
            $inside = imagecolorat($image, $nx, $ny);
            $weight = $alpha / 254;
            $rgb = 0;
            foreach ([16, 8, 0] as $shift) {
                $channel = ($pixel >> $shift) & 255;
                $rgb |= ((int) round($channel + ((($inside >> $shift) & 255) - $channel) * $weight)) << $shift;
            }

            return $rgb;
        }

        return $pixel;
    }

    public function compose(\GdImage $subject, string $template, array $options, ?string $background = null): \GdImage
    {
        $scene = $this->scene($template, $background !== null);
        $backdrop = $background !== null ? imagecreatefromstring($background) : imagecreatefromjpeg(public_path('images/ai_scenes/'.$scene['file']));
        $size = ProductStudioService::TARGET_SIZE;
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, 0xFFFFFF);
        $crop = $background === null && $template === 'dressing' ? self::DRESSING_CROP : null;
        $square = $crop['size'] ?? min(imagesx($backdrop), imagesy($backdrop));
        $left = $crop['x'] ?? (int) ((imagesx($backdrop) - $square) / 2);
        $top = $crop['y'] ?? (int) ((imagesy($backdrop) - $square) / 2);
        imagecopyresampled($canvas, $backdrop, 0, 0, $left, $top, $size, $size, $square, $square);
        imagedestroy($backdrop);

        ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height] = $this->placement($subject, $template, $options, $background !== null);
        $anchor = $options['anchor'] ?? $scene['anchor'] ?? 'bottom';
        $opacity = (int) ($options['shadow'] ?? $scene['shadow'] ?? 22);
        $light = $scene['light'] ?? -1;
        if ($opacity > 0 && $anchor === 'bottom') {
            $center = $x + (int) round($width / 2);
            $base = $y + $height - 3;
            $this->drawSoftShadow($canvas, $center - (int) ($width * .07 * $light), $base + 4, (int) ($width * 1.22), max(24, (int) ($width * .24)), $opacity * .55);
            $this->drawSoftShadow($canvas, $center, $base, (int) ($width * .90), max(12, (int) ($width * .12)), $opacity * .85);
        } elseif ($opacity > 0) {
            $hanging = $anchor === 'top';
            $this->drawSurfaceShadow($canvas, $subject, $x, $y, $width, $height, $hanging ? (int) ($opacity * .65) : $opacity, $light, $hanging ? 14 : 5);
        }
        if ($crop !== null && $anchor === 'top') {
            $this->attachToRail($canvas, $subject, $x, $y, $width, $height);
        }
        if (! empty($options['auto_enhance'])) {
            $layer = imagecreatetruecolor($width, $height);
            imagealphablending($layer, false);
            imagecopyresampled($layer, $subject, 0, 0, 0, 0, $width, $height, imagesx($subject), imagesy($subject));
            $this->relight($layer, $canvas, $x, $y, $scene['light'] ?? null);
            imagecopy($canvas, $layer, $x, $y, 0, 0, $width, $height);
            imagedestroy($layer);
        } else {
            imagecopyresampled($canvas, $subject, $x, $y, 0, 0, $width, $height, imagesx($subject), imagesy($subject));
        }

        return $canvas;
    }

    /** @return array{x:int,y:int,width:int,height:int} */
    public function placement(\GdImage $subject, string $template, array $options, bool $customBackdrop = false): array
    {
        $scene = $this->scene($template, $customBackdrop);
        $size = ProductStudioService::TARGET_SIZE;
        $margin = 20;
        $anchor = $options['anchor'] ?? $scene['anchor'] ?? 'bottom';
        $anchorY = (int) round($size * ($options['position_y'] ?? $scene['y']) / 100);
        $anchorY = max($margin + 1, min($size - $margin - 1, $anchorY));
        $extent = $size * ($options['scale'] ?? $scene['scale']) / 100;
        $maxWidth = isset($options['scale']) ? $size - 2 * $margin : $size * ($scene['width'] ?? 96) / 100;
        $availableHeight = match ($anchor) {
            'top' => $size - $anchorY - $margin,
            'center' => 2 * (min($anchorY, $size - $anchorY) - $margin),
            default => $anchorY - $margin,
        };
        $ratio = min($extent / max(imagesx($subject), imagesy($subject)), $availableHeight / imagesy($subject), $maxWidth / imagesx($subject));
        $width = max(1, (int) round(imagesx($subject) * $ratio));
        $height = max(1, (int) round(imagesy($subject) * $ratio));
        $centerX = $size * ($options['position_x'] ?? $scene['x']) / 100;
        $x = max($margin, min($size - $width - $margin, (int) round($centerX - $width / 2)));
        $y = match ($anchor) {
            'top' => $anchorY,
            'center' => $anchorY - (int) round($height / 2),
            default => $anchorY - $height,
        };

        return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
    }

    private function scene(string $template, bool $customBackdrop): array
    {
        return $customBackdrop ? ['x' => 50, 'y' => 81, 'scale' => 66] : (self::SCENES[$template] ?? self::SCENES['parquet']);
    }

    private function relight(\GdImage $layer, \GdImage $backdrop, int $x, int $y, ?int $knownLight): void
    {
        $width = imagesx($layer);
        $height = imagesy($layer);
        $left = max(0, $x - 26);
        $right = min(imagesx($backdrop) - 1, $x + $width + 26);
        $difference = 0;
        foreach ([.25, .5, .75] as $offset) {
            $sample = $y + (int) ($offset * $height);
            $difference += $this->luminance(imagecolorat($backdrop, $right, $sample)) - $this->luminance(imagecolorat($backdrop, $left, $sample));
        }
        $direction = $knownLight !== null ? $knownLight * 8 : max(-10, min(10, $difference / 20));
        for ($column = 0; $column < $width; $column++) {
            $directionalLight = ($column / max(1, $width - 1) * 2 - 1) * $direction;
            for ($row = 0; $row < $height; $row++) {
                $pixel = imagecolorat($layer, $column, $row);
                if ((($pixel >> 24) & 127) === 127) {
                    continue;
                }
                $level = $this->luminance($pixel) / 255;
                $shift = (int) round($directionalLight * 4 * $level * (1 - $level));
                imagesetpixel($layer, $column, $row, $this->shiftLuminance($pixel, $shift));
            }
        }
    }

    private function drawSoftShadow(\GdImage $canvas, int $x, int $y, int $width, int $height, float $opacity): void
    {
        $shadow = imagecreatetruecolor(160, 48);
        imagealphablending($shadow, false);
        for ($row = 0; $row < 48; $row++) {
            for ($column = 0; $column < 160; $column++) {
                $distance = (($column - 80) / 36) ** 2 + (($row - 24) / 7) ** 2;
                $alpha = 127 - (int) round($opacity * 1.27 * exp(-$distance / 2));
                imagesetpixel($shadow, $column, $row, ($alpha << 24) | 0x211C19);
            }
        }
        imagecopyresampled($canvas, $shadow, $x - (int) ($width / 2), $y - (int) ($height / 2), 0, 0, max(1, $width), max(1, $height), 160, 48);
        imagedestroy($shadow);
    }

    private function drawSurfaceShadow(\GdImage $canvas, \GdImage $subject, int $x, int $y, int $width, int $height, int $opacity, int $light, int $offset): void
    {
        $sample = imagecreatetruecolor(100, 100);
        imagealphablending($sample, false);
        imagecopyresampled($sample, $subject, 0, 0, 0, 0, 100, 100, imagesx($subject), imagesy($subject));
        $shadow = imagecreatetruecolor(104, 104);
        imagefill($shadow, 0, 0, 0x000000);
        for ($row = 0; $row < 100; $row++) {
            for ($column = 0; $column < 100; $column++) {
                $gray = 2 * (127 - ((imagecolorat($sample, $column, $row) >> 24) & 127));
                imagesetpixel($shadow, $column + 2, $row + 2, $gray * 0x010101);
            }
        }
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
        imagealphablending($shadow, false);
        for ($row = 0; $row < 104; $row++) {
            for ($column = 0; $column < 104; $column++) {
                $alpha = 127 - (int) round((imagecolorat($shadow, $column, $row) & 255) * $opacity / 200);
                imagesetpixel($shadow, $column, $row, ($alpha << 24) | 0x211C19);
            }
        }
        imagecopyresampled($canvas, $shadow, $x - (int) ($width * .02) - $offset * $light, $y - (int) ($height * .02) + (int) ($offset * 1.2), 0, 0, (int) round($width * 1.04), (int) round($height * 1.04), 104, 104);
        imagedestroy($shadow);
        imagedestroy($sample);
    }

    private function attachToRail(\GdImage $canvas, \GdImage $subject, int $x, int $y, int $width, int $height): void
    {
        $sampleWidth = 100;
        $sampleHeight = 36;
        $topEdge = imagecreatetruecolor($sampleWidth, $sampleHeight);
        imagealphablending($topEdge, false);
        imagecopyresampled($topEdge, $subject, 0, 0, 0, 0, $sampleWidth, $sampleHeight, imagesx($subject), max(1, (int) (imagesy($subject) * .26)));
        $firstVisible = [];
        for ($column = 0; $column < $sampleWidth; $column++) {
            for ($row = 0; $row < $sampleHeight; $row++) {
                if (((imagecolorat($topEdge, $column, $row) >> 24) & 127) < 48) {
                    $firstVisible[$column] = $row;
                    break;
                }
            }
        }
        imagedestroy($topEdge);

        imageantialias($canvas, true);
        $start = null;
        for ($column = 0; $column <= $sampleWidth; $column++) {
            if (isset($firstVisible[$column])) {
                $start ??= $column;

                continue;
            }
            if ($start === null || $column - $start < 8) {
                $start = null;

                continue;
            }
            $groupWidth = $column - $start;
            $middle = (int) (($start + $column - 1) / 2);
            $clipLeft = $start + (int) round($groupWidth * .22);
            $clipRight = $column - 1 - (int) round($groupWidth * .22);
            $start = null;
            $attachmentX = $x + (int) round($width * ($middle + .5) / $sampleWidth);
            $attachmentY = $y + (int) round($height * .26 * $firstVisible[$middle] / $sampleHeight);
            $photoX = self::DRESSING_CROP['x'] + $attachmentX * self::DRESSING_CROP['size'] / ProductStudioService::TARGET_SIZE;
            $railY = (int) round((310 + ($photoX - 430) * .15 - self::DRESSING_CROP['y']) * ProductStudioService::TARGET_SIZE / self::DRESSING_CROP['size']);
            if ($photoX < 431 || $photoX > 674 || $attachmentY - $railY < 4 || $attachmentY - $railY > 125) {
                continue;
            }
            imagearc($canvas, $attachmentX - 4, $railY, 9, 12, 165, 370, 0x36312D);
            imageline($canvas, $attachmentX, $railY - 1, $attachmentX, $attachmentY + 4, 0x36312D);
            imageline($canvas, $attachmentX + 1, $railY, $attachmentX + 1, $attachmentY + 4, 0x504842);
            $upperEdge = [$firstVisible[$clipLeft], $firstVisible[$middle], $firstVisible[$clipRight]];
            if ($width < $height * .70 && max($upperEdge) - min($upperEdge) <= 3) {
                $barY = $y + (int) round($height * .26 * min($upperEdge) / $sampleHeight) - 5;
                $leftClipX = $x + (int) round($width * ($clipLeft + .5) / $sampleWidth);
                $rightClipX = $x + (int) round($width * ($clipRight + .5) / $sampleWidth);
                imagefilledrectangle($canvas, $leftClipX, $barY, $rightClipX, $barY + 3, 0x3C3530);
                foreach ([$clipLeft => $leftClipX, $clipRight => $rightClipX] as $index => $clipX) {
                    $clipY = $y + (int) round($height * .26 * $firstVisible[$index] / $sampleHeight);
                    imagefilledrectangle($canvas, $clipX - 3, $barY - 2, $clipX + 3, $clipY + 3, 0x5F5750);
                    imageline($canvas, $clipX - 2, $barY - 1, $clipX - 2, $clipY, 0x82786D);
                }
            }
        }
        imageantialias($canvas, false);
    }
}
