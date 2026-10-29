<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:restore-defaults', function () {
    $this->info('Vérification et restauration persistante des médias Zanika...');
    $restoredCount = 0;

    $catalogMap = [
        'frite' => ['main' => 'images/catalog/frites.webp', 'thumb' => 'images/catalog/thumb_frites.webp'],
        'poisson' => ['main' => 'images/catalog/poisson.webp', 'thumb' => 'images/catalog/thumb_poisson.webp'],
        'fish' => ['main' => 'images/catalog/poisson.webp', 'thumb' => 'images/catalog/thumb_poisson.webp'],
        'burger' => ['main' => 'images/catalog/burger.webp', 'thumb' => 'images/catalog/thumb_burger.webp'],
        'snack' => ['main' => 'images/catalog/frites.webp', 'thumb' => 'images/catalog/thumb_frites.webp'],
    ];

    // 1. Pour toutes les images ayant déjà du image_data en base : restaurer sur disque
    $imagesWithData = \App\Models\ProductImage::whereNotNull('image_data')->where('image_data', '!=', '')->get();
    foreach ($imagesWithData as $img) {
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $img->thumbnail_path ?: $img->path), '/');
        $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($cleanPath);
        if (! file_exists($diskPath) || filesize($diskPath) === 0) {
            $bin = @base64_decode($img->image_data);
            if ($bin) {
                @mkdir(dirname($diskPath), 0755, true);
                @file_put_contents($diskPath, $bin);
                $restoredCount++;
            }
        }
        if ($img->path && $img->path !== $img->thumbnail_path) {
            $mainClean = ltrim(str_replace(['../', '..\\'], '', $img->path), '/');
            $mainDisk = \Illuminate\Support\Facades\Storage::disk('public')->path($mainClean);
            if (! file_exists($mainDisk) || filesize($mainDisk) === 0) {
                $bin = @base64_decode($img->image_data);
                if ($bin) {
                    @mkdir(dirname($mainDisk), 0755, true);
                    @file_put_contents($mainDisk, $bin);
                }
            }
        }
    }

    // 2. Pour les images sans image_data : associer un visuel catalogue réaliste et persister en base
    $imagesWithoutData = \App\Models\ProductImage::where(function ($q) {
        $q->whereNull('image_data')->orWhere('image_data', '');
    })->with('product.categories')->get();

    foreach ($imagesWithoutData as $img) {
        $prod = $img->product;
        $title = mb_strtolower($prod?->title ?? '');
        $cat = mb_strtolower($prod?->categories?->first()?->name ?? '');
        $matched = null;

        foreach ($catalogMap as $keyword => $files) {
            if (str_contains($title, $keyword) || str_contains($cat, $keyword) || str_contains($img->path, $keyword)) {
                $matched = $files;
                break;
            }
        }

        if (! $matched) {
            $matched = $catalogMap['frite'];
        }

        $sourceFile = public_path($matched['main']);
        $thumbFile = public_path($matched['thumb']);

        if (file_exists($sourceFile)) {
            $thumbBin = file_exists($thumbFile) ? file_get_contents($thumbFile) : file_get_contents($sourceFile);
            $img->image_data = base64_encode($thumbBin);
            $img->save();

            // Restauration sur disque
            $cleanPath = ltrim(str_replace(['../', '..\\'], '', $img->path), '/');
            $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($cleanPath);
            @mkdir(dirname($diskPath), 0755, true);
            @file_put_contents($diskPath, file_get_contents($sourceFile));

            if ($img->thumbnail_path) {
                $thumbClean = ltrim(str_replace(['../', '..\\'], '', $img->thumbnail_path), '/');
                $thumbDisk = \Illuminate\Support\Facades\Storage::disk('public')->path($thumbClean);
                @mkdir(dirname($thumbDisk), 0755, true);
                @file_put_contents($thumbDisk, $thumbBin);
            }

            $restoredCount++;
        }
    }

    // 3. Pour les produits n'ayant aucune image associée
    $productsWithoutImages = \App\Models\Product::doesntHave('images')->with('categories')->get();
    foreach ($productsWithoutImages as $prod) {
        $title = mb_strtolower($prod->title);
        $cat = mb_strtolower($prod->categories?->first()?->name ?? '');
        $matched = null;

        foreach ($catalogMap as $keyword => $files) {
            if (str_contains($title, $keyword) || str_contains($cat, $keyword)) {
                $matched = $files;
                break;
            }
        }

        if (! $matched) {
            $matched = $catalogMap['frite'];
        }

        $sourceFile = public_path($matched['main']);
        $thumbFile = public_path($matched['thumb']);

        if (file_exists($sourceFile)) {
            $thumbBin = file_exists($thumbFile) ? file_get_contents($thumbFile) : file_get_contents($sourceFile);
            $filename = 'img_' . \Illuminate\Support\Str::random(16) . '.webp';
            $thumbName = 'thumb_' . $filename;
            $relPath = "shops/{$prod->shop_id}/products/{$filename}";
            $relThumb = "shops/{$prod->shop_id}/products/{$thumbName}";

            $diskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($relPath);
            $thumbDisk = \Illuminate\Support\Facades\Storage::disk('public')->path($relThumb);
            @mkdir(dirname($diskPath), 0755, true);
            @file_put_contents($diskPath, file_get_contents($sourceFile));
            @file_put_contents($thumbDisk, $thumbBin);

            $prod->images()->create([
                'path' => $relPath,
                'thumbnail_path' => $relThumb,
                'sort_order' => 0,
                'size_bytes' => filesize($sourceFile),
                'image_data' => base64_encode($thumbBin),
            ]);
            $restoredCount++;
        }
    }

    $this->info("Restauration terminée : {$restoredCount} média(s) restauré(s) avec succès !");
})->purpose('Restaure et persiste les médias produits sur disque et en base de données');

Artisan::command('mail:test {to?}', function (?string $to = null) {
    $to = $to ?: config('mail.from.address');
    $this->info("--- Test d'envoi d'email Zanika ---");
    $this->info("Destinataire : {$to}");
    $this->line("Mailer : " . config('mail.default'));
    $this->line("Host : " . config('mail.mailers.smtp.host'));
    $this->line("Port : " . config('mail.mailers.smtp.port'));
    $this->line("Encryption : " . config('mail.mailers.smtp.encryption'));
    $this->line("Username : " . (config('mail.mailers.smtp.username') ?: '(aucun)'));
    $this->line("From : " . config('mail.from.name') . ' <' . config('mail.from.address') . '>');

    try {
        \Illuminate\Support\Facades\Mail::to($to)->send(
            new \App\Mail\VerificationCodeMail('Client Zanika', '482915', 10)
        );
        $this->info("✅ Succès ! L'email de test (code OTP) a été envoyé avec succès à {$to} !");
    } catch (\Throwable $e) {
        $this->error("❌ Échec de l'envoi de l'email :");
        $this->error($e->getMessage());
    }
})->purpose('Teste l\'envoi d\'un email OTP via la configuration SMTP active');

