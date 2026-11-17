<?php

namespace App\Console\Commands;

use App\Models\Advertisement;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Services\PublicMediaStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('media:backup-existing')]
#[Description('Sauvegarde en base les médias existants référencés par les boutiques, sans remplacer les fichiers manquants.')]
class BackupExistingMedia extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('public');
        $saved = 0;
        $missing = 0;
        $failed = 0;
        foreach (Shop::cursor() as $shop) {
            $paths = array_filter([$shop->logo_path, ...($shop->onboarding_data['uploaded_photos'] ?? [])]);
            $images = ProductImage::withoutGlobalScopes()->whereHas('product', fn ($query) => $query->withoutGlobalScopes()->where('shop_id', $shop->id))->get();
            foreach ($images as $image) {
                $paths[] = $image->path;
                $paths[] = $image->thumbnail_path;
            }
            foreach (Advertisement::withoutGlobalScopes()->where('shop_id', $shop->id)->cursor() as $ad) {
                $config = $ad->visual_config ?? [];
                array_walk_recursive($config, function ($value) use (&$paths, $shop) {
                    if (is_string($value)) {
                        $path = (string) parse_url($value, PHP_URL_PATH);
                        if (str_starts_with($path, '/storage/shops/'.$shop->id.'/')) {
                            $paths[] = substr($path, strlen('/storage/'));
                        }
                    }
                });
            }
            foreach (array_unique(array_filter($paths)) as $path) {
                if (! $shop->ownsPublicPath($path)) {
                    continue;
                }
                if (! $disk->exists($path)) {
                    $missing++;

                    continue;
                }
                try {
                    $binary = $disk->get($path);
                    app(PublicMediaStorage::class)->put($path, $binary);
                    if ($path === $shop->logo_path) {
                        $shop->update(['logo_data' => base64_encode($binary)]);
                    }
                    foreach ($images->where('path', $path) as $image) {
                        $image->update(['image_data' => base64_encode($binary)]);
                    }
                    $saved++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->warn('Sauvegarde média impossible pour la boutique #'.$shop->id.': '.$exception->getMessage());
                }
            }
        }
        $this->info("Médias sauvegardés : {$saved} ; fichiers absents : {$missing} ; échecs : {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
