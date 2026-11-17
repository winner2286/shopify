<?php

use App\Services\Ai\RembgService;
use Illuminate\Contracts\Console\Kernel;

// Run in the production Docker image, including the actual CPU model and PHP bridge.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = imagecreatetruecolor(1200, 900);
imagefill($source, 0, 0, imagecolorallocate($source, 245, 245, 245));
imagefilledellipse($source, 600, 450, 550, 600, imagecolorallocate($source, 170, 20, 30));
ob_start();
imagepng($source);
$binary = (string) ob_get_clean();
imagedestroy($source);

$service = app(RembgService::class);
if (! $service->isConfigured()) {
    throw new RuntimeException('RemBG is not configured in the production image.');
}
$result = $service->removeBackground($binary);
$image = $result ? imagecreatefromstring($result) : false;
if (! $image || imagesx($image) !== 1200 || imagesy($image) !== 900) {
    throw new RuntimeException('The real RemBG subprocess did not return the expected PNG.');
}
$transparent = (imagecolorat($image, 0, 0) >> 24) & 127;
$opaque = (imagecolorat($image, 600, 450) >> 24) & 127;
if ($transparent < 110 || $opaque > 30) {
    throw new RuntimeException('The real model did not separate the test subject.');
}
imagedestroy($image);
echo json_encode(['rembg_runtime' => 'passed', 'dimensions' => '1200x900', 'backend' => 'CPUExecutionProvider']).PHP_EOL;
