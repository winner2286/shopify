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
    $mailer = config('mail.default');
    $this->line("Mailer actif : " . $mailer);
    if ($mailer === 'google_webhook') {
        $webhookUrl = config('mail.mailers.google_webhook.endpoint') ?: config('mail.mailers.google_webhook.url');
        $this->line("Webhook URL : " . ($webhookUrl ? 'Configurée (HTTPS port 443)' : '(NON configurée)'));
    } else {
        $this->line("Host : " . config('mail.mailers.smtp.host'));
        $this->line("Port : " . config('mail.mailers.smtp.port'));
        $this->line("Encryption : " . config('mail.mailers.smtp.encryption'));
        $this->line("Username : " . (config('mail.mailers.smtp.username') ?: '(aucun)'));
    }
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

Artisan::command('admin:list', function () {
    $this->info("--- Liste des Utilisateurs & Administrateurs Zanika ---");
    $users = \App\Models\User::with('shop')->get();
    if ($users->isEmpty()) {
        $this->warn("Aucun utilisateur trouvé.");
        return;
    }

    $rows = $users->map(fn ($u) => [
        'ID' => $u->id,
        'Nom' => $u->name,
        'Email' => $u->email,
        'Rôle' => $u->role === \App\Models\User::ROLE_ADMIN ? '⭐ SUPER-ADMIN' : 'Marchand',
        'Boutique' => $u->shop ? $u->shop->name . ' (' . $u->shop->subdomain . ')' : '(aucune)',
        'Vérifié' => $u->isVerified() ? 'Oui' : 'Non',
    ]);

    $this->table(['ID', 'Nom', 'Email', 'Rôle', 'Boutique', 'Vérifié'], $rows);
})->purpose('Affiche la liste de tous les utilisateurs et leur rôle sur Zanika');

Artisan::command('admin:promote {email}', function (string $email) {
    $user = \App\Models\User::where('email', trim($email))->first();
    if (! $user) {
        $this->error("Utilisateur introuvable avec l'email : {$email}");
        return 1;
    }

    $user->update([
        'role' => \App\Models\User::ROLE_ADMIN,
        'email_verified_at' => $user->email_verified_at ?: now(),
        'terms_accepted_at' => $user->terms_accepted_at ?: now(),
    ]);

    $this->info("✅ Succès : L'utilisateur {$user->name} ({$user->email}) a été promu SUPER-ADMINISTRATEUR !");
    return 0;
})->purpose('Promeut un utilisateur existant au rang de Super-Administrateur');

Artisan::command('admin:create {email} {password} {name?}', function (string $email, string $password, ?string $name = null) {
    $name = $name ?: 'Super Administrateur';
    $user = \App\Models\User::updateOrCreate(
        ['email' => trim($email)],
        [
            'name' => $name,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => \App\Models\User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'country' => 'TG',
        ]
    );

    $this->info("✅ Compte Super-Administrateur prêt !");
    $this->line("Email : {$user->email}");
    $this->line("Rôle  : {$user->role}");
    $this->line("Accès direct : " . url('/admin'));
    return 0;
})->purpose('Crée ou met à jour un compte Super-Administrateur avec mot de passe');

Artisan::command('admin:maintenance {action=status : on, off ou status} {--message= : Message d\'information} {--time= : Durée estimée}', function (string $action = 'status') {
    $action = strtolower($action);
    if ($action === 'on' || $action === 'down') {
        $secret = 'zanika-admin-bypass';
        $message = (string) ($this->option('message') ?: 'Nous effectuons actuellement une amélioration technique planifiée de la plateforme pour optimiser la rapidité et la fiabilité de vos boutiques.');
        $time = (string) $this->option('time');

        Artisan::call('down', [
            '--secret' => $secret,
            '--render' => 'errors.503',
        ]);

        $payload = [
            'active' => true,
            'message' => $message,
            'estimated_time' => $time,
            'activated_at' => now()->format('d/m/Y à H:i'),
            'activated_by' => 'Console CLI',
            'secret' => $secret,
        ];
        @file_put_contents(storage_path('framework/maintenance_info.json'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->warn("🔴 La plateforme Zanika est maintenant en MODE MAINTENANCE.");
        $this->line("Message affiché : {$message}");
        $this->info("Lien secret de contournement : " . url('/' . $secret));
        return 0;
    } elseif ($action === 'off' || $action === 'up') {
        Artisan::call('up');
        if (file_exists(storage_path('framework/maintenance_info.json'))) {
            @unlink(storage_path('framework/maintenance_info.json'));
        }
        $this->info("🟢 La plateforme Zanika est à nouveau EN LIGNE (Accès public rétabli).");
        return 0;
    } else {
        $isDown = app()->isDownForMaintenance();
        if ($isDown) {
            $this->warn("🔴 Statut : MODE MAINTENANCE ACTIF");
            $infoFile = storage_path('framework/maintenance_info.json');
            if (file_exists($infoFile)) {
                $info = json_decode(file_get_contents($infoFile), true) ?: [];
                $this->line("Activé le : " . ($info['activated_at'] ?? 'inconnu'));
                $this->line("Message   : " . ($info['message'] ?? ''));
                $this->info("Bypass    : " . url('/' . ($info['secret'] ?? 'zanika-admin-bypass')));
            }
        } else {
            $this->info("🟢 Statut : EN LIGNE (Fonctionnement normal)");
        }
        return 0;
    }
})->purpose('Contrôle ou affiche le statut du mode maintenance de la plateforme');

