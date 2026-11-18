<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Advertisement;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\VideoAdGeneratorService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullPlatformEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makePngBinary(int $width = 300, int $height = 300, bool $transparent = false): string
    {
        $im = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            $bg = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefilledrectangle($im, 0, 0, $width, $height, $bg);
        } else {
            $bg = imagecolorallocate($im, 245, 242, 238);
            imagefilledrectangle($im, 0, 0, $width, $height, $bg);
        }

        $accent = imagecolorallocate($im, 158, 27, 50);
        imagefilledellipse($im, (int) ($width / 2), (int) ($height / 2), (int) ($width * 0.55), (int) ($height * 0.55), $accent);

        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return (string) $bin;
    }

    public function test_complete_new_merchant_and_customer_lifecycle_from_registration_to_order_and_ads(): void
    {
        Storage::fake('public');
        Mail::fake();

        config([
            'services.photoroom.key' => 'pr_e2e_test_key',
            'services.groq.key' => '',
            'services.openrouter.key' => '',
            'services.gemini.key' => '',
        ]);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($this->makePngBinary(300, 300, true), 200, ['Content-Type' => 'image/png']),
            'https://image-api.photoroom.com/v2/edit' => Http::response($this->makePngBinary(1000, 1000, false), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->seed(PlanSeeder::class);

        // =========================================================================
        // 1. VITRINE SAAS / TARIFS & SANTE API
        // =========================================================================
        $this->get('/')
            ->assertOk()
            ->assertSee('Essentiel')
            ->assertSee('Pro')
            ->assertSee('Premium')
            ->assertSee('7 500')
            ->assertSee('<dt>Crédits Photoroom par mois</dt><dd>25</dd>', false)
            ->assertSee('<dt>Crédits Photoroom par mois</dt><dd>60</dd>', false)
            ->assertSee('<dt>Crédits Photoroom par mois</dt><dd>180</dd>', false)
            ->assertDontSee('Facturation Annuelle');

        $this->get('/api/keep-alive')
            ->assertOk()
            ->assertJson(['status' => 'alive']);

        // =========================================================================
        // 2. INSCRIPTION NOUVEAU COMMERÇANT & VALIDATION OTP
        // =========================================================================
        $this->get('/inscription')->assertOk();

        $this->post('/inscription', [
            'name' => 'Kossi Agbeko',
            'email' => 'kossi.e2e@example.test',
            'password' => 'Zanika2026!',
            'password_confirmation' => 'Zanika2026!',
            'country' => 'TG',
            'terms' => '1',
        ])->assertRedirect('/verification');

        $otpCode = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$otpCode) {
            $otpCode = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $otpCode);

        $this->get('/verification')->assertOk();
        $this->post('/verification', ['code' => $otpCode])
            ->assertRedirect('/onboarding/activite');

        $merchant = User::where('email', 'kossi.e2e@example.test')->firstOrFail();
        $this->assertNotNull($merchant->email_verified_at);
        $this->assertAuthenticatedAs($merchant);

        $shop = $merchant->shop;
        $this->assertNotNull($shop);
        $this->assertSame(Shop::DRAFT, $shop->status);

        // =========================================================================
        // 3. ONBOARDING IA COMPLET (ÉTAPES 1 -> 2 -> 3 -> 4 -> 5)
        // =========================================================================
        $this->get('/onboarding/activite')->assertOk();
        $this->post('/onboarding/activite', [
            'description' => 'Boutique de robes wax contemporaines et sacs en cuir faits main à Lomé.',
            'business_category' => 'mode',
        ])->assertRedirect('/onboarding/style');

        $this->get('/onboarding/style')->assertOk();
        $this->post('/onboarding/style', [
            'suggested_name' => 'Atelier Élégance Lomé',
            'whatsapp' => '+22890112233',
            'target_audience' => 'Femmes actives et jeunes créatifs',
            'visual_style' => 'moderne',
            'brand_color' => '#9E1B32',
            'city' => 'Lomé',
            'address' => 'Boulevard du 13 Janvier, Lomé',
        ])->assertRedirect('/onboarding/photos');

        // Étape 3 : envoi de 2 photos en mode Studio Photoroom
        $this->get('/onboarding/photos')->assertOk();
        $this->post('/onboarding/photos', [
            'photos' => [
                UploadedFile::fake()->image('robe-wax.jpg', 400, 300),
                UploadedFile::fake()->image('sac-cuir.jpg', 350, 350),
            ],
            'photo_enhancement' => 'studio',
        ])->assertRedirect('/onboarding/generation');

        $shop->refresh();
        $this->assertSame(2, $shop->photoroomCreditsUsedThisMonth(), 'Étape 3 doit débiter exactement 2 crédits Photoroom.');
        $this->assertSame(23, $shop->photoroomCreditsRemaining());
        Http::assertSentCount(2);

        // Étape 4 : génération IA sans double facturation Photoroom
        $this->get('/onboarding/generation')->assertOk();
        $this->post('/onboarding/generation')->assertRedirect('/onboarding/apercu');

        $shop->refresh();
        $this->assertSame(2, $shop->photoroomCreditsUsedThisMonth(), 'Étape 4 ne doit pas re-débiter Photoroom sur des photos déjà traitées.');
        Http::assertSentCount(2);
        Http::assertNotSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'v2/edit'));

        $this->assertGreaterThanOrEqual(4, $shop->categories()->count());
        $this->assertSame(4, $shop->pages()->count());
        $generatedProducts = $shop->products()->with('images')->get();
        $this->assertCount(2, $generatedProducts);
        foreach ($generatedProducts as $gp) {
            $this->assertNotEmpty($gp->images->first()?->image_data, 'Chaque image générée à l\'onboarding doit posséder image_data.');
        }

        // Étape 5 : aperçu & choix du logo
        $this->get('/onboarding/apercu')->assertOk()->assertSee('Atelier Élégance Lomé');
        $this->post('/onboarding/logo', ['logo_id' => 1])->assertRedirect();
        $shop->refresh();
        $this->assertSame(1, $shop->onboarding_data['chosen_logo']['id']);

        // =========================================================================
        // 4. CATALOGUE PRODUITS (AJOUT IA, STUDIO PHOTO, ÉDITION MULTI-PHOTOS)
        // =========================================================================
        $this->get('/tableau-de-bord')
            ->assertOk()
            ->assertSee('Kossi Agbeko')
            ->assertSee('Essentiel');
        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('handleProductPhotoInput');

        // A. Analyse IA depuis la bannière photo + sauvegarde ai_temp_photo_path
        $aiAnalyzeRes = $this->postJson(route('ai.products.analyze_photo'), [
            'photo' => UploadedFile::fake()->image('escarpin-or.jpg', 320, 320),
            'user_hint' => 'Escarpins de soirée dorés',
        ]);
        $aiAnalyzeRes->assertOk()->assertJsonPath('status', 'success');
        $tempPhotoPath = (string) $aiAnalyzeRes->json('temp_photo_path');
        $this->assertNotSame('', $tempPhotoPath);

        $shop->refresh();
        $this->assertSame(1, $shop->aiCreditsUsedThisMonth());

        // B. Studio Photo IA sur fiche produit (vérification qualité)
        $qualityRes = $this->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('escarpin-studio.jpg', 320, 320),
            'category_hint' => 'Chaussures femme',
            'style' => 'studio_white',
        ]);
        $qualityRes->assertOk()->assertJsonPath('engine', 'photoroom-ai');

        $shop->refresh();
        $this->assertSame(3, $shop->photoroomCreditsUsedThisMonth());

        // C. Création produit via ai_temp_photo_path -> image_data doit être enregistré
        $firstCat = $shop->categories()->first();
        $this->post('/produits', [
            'title' => 'Escarpins Dorés Prestige',
            'description' => 'Chaussures élégantes pour cérémonies et soirées.',
            'price' => 25000,
            'compare_at_price' => 30000,
            'track_stock' => '1',
            'stock' => 10,
            'low_stock_threshold' => 2,
            'status' => 'active',
            'category_ids' => $firstCat ? [$firstCat->id] : [],
            'ai_temp_photo_path' => $tempPhotoPath,
        ])->assertRedirect('/produits');

        $createdProduct = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('title', 'Escarpins Dorés Prestige')
            ->with('images')
            ->firstOrFail();

        $this->assertCount(1, $createdProduct->images);
        $this->assertNotEmpty($createdProduct->images->first()->image_data, 'La photo attachée via ai_temp_photo_path doit conserver image_data.');

        // D. Modification produit + ajout d'une 2e photo -> la 1re photo ne doit PAS être supprimée
        $this->get(route('products.edit', $createdProduct))
            ->assertOk()
            ->assertSee('handleEditPhotoInput');

        $this->put("/produits/{$createdProduct->id}", [
            'title' => 'Escarpins Dorés Prestige',
            'description' => 'Chaussures élégantes pour cérémonies et soirées.',
            'price' => 25000,
            'compare_at_price' => 30000,
            'track_stock' => '1',
            'stock' => 10,
            'low_stock_threshold' => 2,
            'status' => 'active',
            'category_ids' => $firstCat ? [$firstCat->id] : [],
            'photos' => [UploadedFile::fake()->image('escarpin-profil.jpg', 320, 320)],
        ])->assertRedirect('/produits');

        $createdProduct->refresh();
        $createdProduct->load('images');
        $this->assertCount(2, $createdProduct->images, 'La modification avec nouvelle photo doit conserver la photo IA précédente.');

        $shop->refresh();
        $this->assertSame(3, $shop->photoroomCreditsUsedThisMonth(), 'L’enregistrement conserve la photo sans nouvelle retouche Photoroom.');

        // =========================================================================
        // 5. ZONES DE LIVRAISON & PUBLICATION BOUTIQUE
        // =========================================================================
        $this->get('/livraison')->assertOk();
        $this->post('/livraison', [
            'name' => 'Lomé Centre Express',
            'price' => 1500,
            'delivery_estimate' => 'Sous 24h',
        ])->assertRedirect('/livraison');

        $deliveryZone = ShippingZone::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('name', 'Lomé Centre Express')
            ->firstOrFail();

        $this->post('/boutique/publier')->assertRedirect('/boutique/parametres');
        $shop->refresh();
        $this->assertSame(Shop::PUBLISHED, $shop->status);

        // =========================================================================
        // 6. RÈGLES FORMULE ESSENTIEL VS UPGRADE PRO, CODES PROMO & PUBLICITÉ
        // =========================================================================
        // Sur Essentiel, les textes marketing restent accessibles et le studio pub est réservé à Pro.
        $this->post('/codes-promo', [
            'code' => 'BIENVENUE10',
            'type' => 'percentage',
            'value' => 10,
        ])->assertForbidden();

        $this->post('/publicite', [
            'angle' => VideoAdGeneratorService::ANGLE_COUP_DE_COEUR,
            'product_id' => $createdProduct->id,
            'channel' => VideoAdGeneratorService::PLATFORM_TIKTOK,
        ])->assertForbidden();

        $this->post('/publicite', [
            'angle' => VideoAdGeneratorService::ANGLE_COUP_DE_COEUR,
            'product_id' => $createdProduct->id,
            'channel' => VideoAdGeneratorService::PLATFORM_WHATSAPP,
            'target_duration' => 15,
        ])->assertForbidden();

        $this->get('/publicite/affiches')->assertForbidden();
        $this->postJson(route('ai.products.photoroom_cutout'), [
            'image' => UploadedFile::fake()->image('affiche-produit.png', 280, 280),
            'mode' => 'transparent',
        ])->assertForbidden();

        // Passage au forfait Pro via Checkout Abonnement + Simulation Sandbox
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->get("/abonnement/paiement/{$proPlan->slug}")
            ->assertOk()
            ->assertSee('<dt>Crédits Photoroom par mois</dt><dd>60</dd>', false);

        $subscription = Subscription::where('shop_id', $shop->id)->latest('id')->firstOrFail();
        $this->post("/abonnement/simulate/{$subscription->id}/{$proPlan->id}")
            ->assertRedirect('/tableau-de-bord');

        $shop->refresh();
        $this->assertSame('pro', $shop->currentPlan()->slug);
        $this->assertSame(60, $shop->photoroomCreditsMonthly());
        $this->assertSame(350, $shop->aiCreditsMonthly());

        // Désormais en Pro : création code promo BIENVENUE10 (-10%) et vidéo TikTok avec Photoroom Solution A
        $this->post('/codes-promo', [
            'code' => 'BIENVENUE10',
            'type' => 'percentage',
            'value' => 10,
        ])->assertRedirect('/codes-promo')->assertSessionHas('status');

        $this->assertDatabaseHas('discount_codes', [
            'shop_id' => $shop->id,
            'code' => 'BIENVENUE10',
            'value' => 10,
        ]);

        $creditsBeforeVideo = $shop->photoroomCreditsUsedThisMonth();
        $this->post('/publicite', [
            'angle' => VideoAdGeneratorService::ANGLE_COUP_DE_COEUR,
            'product_id' => $createdProduct->id,
            'tone' => VideoAdGeneratorService::TONE_LUXE,
            'channel' => VideoAdGeneratorService::PLATFORM_TIKTOK,
            'target_duration' => 15,
            'enhance_photoroom' => '1',
        ])->assertRedirect();

        $ad = Advertisement::withoutGlobalScopes()->where('shop_id', $shop->id)->latest('id')->firstOrFail();
        $this->get("/publicite/{$ad->id}")->assertOk();

        $shop->refresh();
        $this->assertSame($creditsBeforeVideo + 1, $shop->photoroomCreditsUsedThisMonth());

        // =========================================================================
        // 7. PARCOURS ACHETEUR COMPLET SUR LA VITRINE PUBLIQUE
        // =========================================================================
        $this->post('/deconnexion')->assertRedirect();
        $this->assertGuest();

        $sub = $shop->subdomain;

        $this->get("/b/{$sub}")->assertOk()->assertSee('Atelier Élégance Lomé');
        $this->get("/b/{$sub}/produits")->assertOk()->assertSee('Escarpins Dorés Prestige');
        $this->get("/b/{$sub}/produits/{$createdProduct->slug}")->assertOk()->assertSee('25 000');

        // Ajout au panier (2 paires * 25 000 = 50 000 FCFA)
        $this->post("/b/{$sub}/panier/ajouter", [
            'product_id' => $createdProduct->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get("/b/{$sub}/panier")->assertOk()->assertSee('Escarpins Dorés Prestige');
        $this->get("/b/{$sub}/commander")->assertOk()->assertSee('Lomé Centre Express');

        // Application code promo BIENVENUE10 (-5 000 FCFA)
        $this->post("/b/{$sub}/commander/code-promo", [
            'promo_code' => 'BIENVENUE10',
        ])->assertRedirect();

        // Passage commande (50 000 - 5 000 + 1 500 = 46 500 FCFA)
        $checkoutRes = $this->post("/b/{$sub}/commander", [
            'customer_name' => 'Afi Kodjo',
            'customer_phone' => '90998877',
            'shipping_zone_id' => $deliveryZone->id,
            'delivery_address' => 'Quartier Nyékonakpoè, Rue 12',
            'payment_method' => 'cash_on_delivery',
        ]);
        $checkoutRes->assertRedirect();

        $order = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Afi Kodjo', $order->customer_name);
        $this->assertSame(50000, $order->subtotal);
        $this->assertSame(5000, $order->discount_amount);
        $this->assertSame(1500, $order->shipping_cost);
        $this->assertSame(46500, $order->total);

        $this->get("/b/{$sub}/commandes/{$order->order_number}")
            ->assertOk()
            ->assertSee($order->order_number);

        $this->get("/recu/{$order->order_number}?token=".urlencode($order->receiptToken()))
            ->assertOk()
            ->assertSee('Afi Kodjo');

        $this->get("/b/{$sub}/suivi")->assertOk();

        // Stock décrémenté après commande
        $createdProduct->refresh();
        $this->assertSame(8, $createdProduct->stock);

        // =========================================================================
        // 8. GESTION DE COMMANDE, CLIENTS, STATISTIQUES & STUDIO IA COMMERÇANT
        // =========================================================================
        $this->actingAs($merchant);

        $this->get('/commandes')->assertOk()->assertSee($order->order_number);
        $this->get("/commandes/{$order->id}")->assertOk()->assertSee('Afi Kodjo');
        $this->get("/commandes/{$order->id}/recu")->assertOk();

        $this->post("/commandes/{$order->id}/encaisser")->assertRedirect();
        $this->post("/commandes/{$order->id}/statut", ['status' => 'confirmed'])->assertRedirect();
        $this->post("/commandes/{$order->id}/statut", ['status' => 'shipped'])->assertRedirect();
        $this->post("/commandes/{$order->id}/statut", ['status' => 'delivered'])->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);

        $this->get('/clients')->assertOk()->assertSee('Afi Kodjo');
        $this->get('/statistiques')->assertOk();
        $this->get('/assistant-ia')->assertOk();
        $this->get('/assistant-ia/studio')->assertOk();
        $this->get('/boutique/pages')->assertOk();
    }

    public function test_all_blade_templates_contain_valid_javascript_syntax(): void
    {
        $viewsRoot = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsRoot));

        $bladeFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $bladeFiles[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($bladeFiles);

        foreach ($bladeFiles as $filePath) {
            $content = (string) file_get_contents($filePath);
            $this->assertLessThanOrEqual(
                1,
                substr_count($content, 'let currentAiVariations'),
                "Déclaration dupliquée let currentAiVariations détectée dans {$filePath}"
            );
        }

        $nodeVersion = trim((string) @shell_exec('node -v'));
        if ($nodeVersion !== '') {
            $script = <<<'JS'
const fs = require('fs'), path = require('path'), vm = require('vm');
function walk(dir) {
    let res = [];
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) res = res.concat(walk(p));
        else if (e.name.endsWith('.blade.php')) res.push(p);
    }
    return res;
}
function stripJson(s) {
    let idx;
    while ((idx = s.indexOf('@json(')) !== -1) {
        let d = 0, i = idx + 5;
        for (; i < s.length; i++) {
            if (s[i] === '(') d++;
            else if (s[i] === ')') {
                d--;
                if (d === 0) { i++; break; }
            }
        }
        s = s.slice(0, idx) + '{}' + s.slice(i);
    }
    return s;
}
let errors = [];
for (const f of walk(process.argv[2])) {
    const html = fs.readFileSync(f, 'utf8');
    let i = 0;
    for (const m of html.matchAll(/<script(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/gi)) {
        i++;
        const code = stripJson(m[1])
            .replace(/\{\{[\s\S]*?\}\}/g, '0')
            .replace(/\{!![\s\S]*?!!\}/g, '{}')
            .replace(/@if[\s\S]*?@endif/g, '');
        try {
            new vm.Script(code, { filename: `${f}#${i}` });
        } catch (err) {
            errors.push(`${f}#${i}: ${err.message}`);
        }
    }
}
if (errors.length > 0) {
    console.error(errors.join('\n'));
    process.exit(1);
}
JS;
            $tmpScript = tempnam(sys_get_temp_dir(), 'blade_js_').'.cjs';
            file_put_contents($tmpScript, $script);
            $cmd = 'node '.escapeshellarg($tmpScript).' '.escapeshellarg($viewsRoot).' 2>&1';
            exec($cmd, $outputLines, $exitCode);
            @unlink($tmpScript);

            $this->assertSame(0, $exitCode, "Erreurs de syntaxe JavaScript dans les vues Blade :\n".implode("\n", $outputLines));
        }
    }
}
