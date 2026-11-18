<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\PhotoroomService;
use App\Services\Ai\ProductStudioService;
use App\Services\ImageService;
use App\Services\VideoAdGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoroomIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeout_has_a_safe_diagnostic_message(): void
    {
        config(['services.photoroom.key' => 'test-key', 'services.photoroom.sandbox_key' => '']);
        Http::preventStrayRequests();
        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;
            throw new ConnectionException('cURL error 28: PRIVATE_CONNECTION_DETAIL');
        });
        $service = app(PhotoroomService::class);

        $this->assertNull($service->editProductImage($this->makePngBinary()));
        $this->assertStringContainsString('délai prévu', $service->failureMessage());
        $this->assertStringNotContainsString('PRIVATE_CONNECTION_DETAIL', $service->failureMessage());
        $this->assertSame(1, $attempts);
    }

    public function test_invalid_image_response_has_a_safe_diagnostic_message(): void
    {
        config(['services.photoroom.key' => 'test-key', 'services.photoroom.sandbox_key' => '']);
        Http::preventStrayRequests();
        Http::fake(['https://image-api.photoroom.com/v2/edit' => Http::response('not-an-image')]);
        $service = app(PhotoroomService::class);
        $this->assertNull($service->editProductImage($this->makePngBinary()));
        $this->assertStringContainsString('image exploitable', $service->failureMessage());
        Http::assertSentCount(1);
    }

    public function test_production_never_falls_back_to_a_watermarked_sandbox_image(): void
    {
        $this->app['env'] = 'production';
        config(['services.photoroom.key' => 'live-test', 'services.photoroom.sandbox_key' => 'sandbox_test']);
        Http::preventStrayRequests();
        Http::fake(['https://image-api.photoroom.com/v2/edit' => Http::response([], 402)]);

        $result = app(PhotoroomService::class)->editProductImage($this->makePngBinary());

        $this->assertNull($result);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->hasHeader('x-api-key', 'sandbox_test'));
    }

    private function createMerchant(string $email = 'merchant.pr@example.test', string $plan = 'essentiel'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Maison Photoroom Test',
            'business_category' => 'mode',
            'description' => 'Boutique de mode et accessoires contemporains.',
            'status' => Shop::PUBLISHED,
            'subdomain' => 'maison-pr-'.$user->id,
        ]);
        $this->subscribeShopToPlan($shop, $plan);

        return [$user, $shop];
    }

    private function subscribeShopToPlan(Shop $shop, string $slug): Plan
    {
        $defaults = match ($slug) {
            'pro' => [
                'name' => 'Pro',
                'monthly_price' => 15000,
                'max_products' => 300,
                'max_shops' => 3,
                'storage_mb' => 8192,
                'ai_credits' => 350,
                'photoroom_credits' => 60,
                'full_generations' => 2,
                'active' => true,
            ],
            'premium' => [
                'name' => 'Premium',
                'monthly_price' => 30000,
                'max_products' => 2500,
                'max_shops' => 999,
                'storage_mb' => 25600,
                'ai_credits' => 1200,
                'photoroom_credits' => 180,
                'full_generations' => 5,
                'active' => true,
            ],
            default => [
                'name' => 'Essentiel',
                'monthly_price' => 10000,
                'max_products' => 50,
                'max_shops' => 1,
                'storage_mb' => 2048,
                'ai_credits' => 80,
                'photoroom_credits' => 25,
                'full_generations' => 1,
                'active' => true,
            ],
        };

        $plan = Plan::firstOrCreate(['slug' => $slug], $defaults);

        Subscription::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        $shop->refresh();

        return $plan;
    }

    private function makePngBinary(int $width = 240, int $height = 240, bool $transparent = false): string
    {
        $im = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            $bg = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefilledrectangle($im, 0, 0, $width, $height, $bg);
        } else {
            $bg = imagecolorallocate($im, 240, 240, 245);
            imagefilledrectangle($im, 0, 0, $width, $height, $bg);
        }

        $accent = imagecolorallocate($im, 158, 27, 50);
        imagefilledellipse($im, (int) ($width / 2), (int) ($height / 2), (int) ($width * 0.6), (int) ($height * 0.5), $accent);

        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return (string) $bin;
    }

    public function test_photoroom_service_remove_background_and_studio_packshot_via_http_fake(): void
    {
        config(['services.photoroom.key' => 'pr_live_test_123']);

        $fakeCutoutPng = $this->makePngBinary(300, 300, true);
        $fakeStudioPng = $this->makePngBinary(1000, 1000, false);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeStudioPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $service = new PhotoroomService;
        $this->assertTrue($service->isConfigured());

        $source = $this->makePngBinary(250, 250, false);

        $cutout = $service->removeBackground($source);
        $this->assertNotNull($cutout);
        $this->assertSame($fakeCutoutPng, $cutout);

        $packshot = $service->studioPackshot($source, 'studio_white', '1000x1000');
        $this->assertNotNull($packshot);
        $this->assertSame($fakeStudioPng, $packshot);

        Http::assertSent(fn (HttpClientRequest $req) => $req->hasHeader('x-api-key', 'pr_live_test_123'));
    }

    public function test_photoroom_service_studio_packshot_falls_back_to_v1_segment_when_v2_fails(): void
    {
        config(['services.photoroom.key' => 'pr_basic_key']);

        $fakeCutoutPng = $this->makePngBinary(300, 300, true);

        Http::fake([
            'https://image-api.photoroom.com/v2/edit' => Http::response(['error' => 'Plan limit'], 402),
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $service = new PhotoroomService;
        $result = $service->studioPackshot($this->makePngBinary(250, 250), 'studio_white');

        $this->assertNotNull($result);
        $this->assertSame($fakeCutoutPng, $result);
    }

    public function test_product_studio_service_uses_photoroom_when_configured_and_falls_back_to_local(): void
    {
        Storage::fake('public');
        [, $shop] = $this->createMerchant();
        $this->subscribeShopToPlan($shop, 'pro');
        $source = $this->makePngBinary(260, 260, false);

        // 1. Photoroom actif
        config(['services.photoroom.key' => 'pr_active_key']);
        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($this->makePngBinary(260, 260, true), 200),
            'https://image-api.photoroom.com/v2/edit' => Http::response($this->makePngBinary(1000, 1000, false), 200),
        ]);

        $studio = new ProductStudioService;
        $prResult = $studio->processProductPhoto($shop, $source, 'Sac à main cuir');

        $this->assertSame('photoroom-ai', $prResult['engine']);
        $this->assertSame('photoroom_v2_edit', $prResult['cutout_method']);
        $this->assertNotEmpty($prResult['cutout_path']);
        $this->assertTrue(collect($prResult['variations'])->contains(fn ($v) => ($v['badge'] ?? '') === 'Photoroom IA'));

        // 2. Fallback sans clé API
        config(['services.photoroom.key' => '']);
        $localStudio = new ProductStudioService;
        $localResult = $localStudio->processProductPhoto($shop, $source, 'Sac à main cuir');

        $this->assertSame('zanika-product-studio', $localResult['engine']);
        $this->assertSame('local_saliency_segmentation', $localResult['cutout_method']);
        $this->assertGreaterThanOrEqual(4, count($localResult['variations']));
    }

    public function test_onboarding_respects_original_mode_without_square_cropping_and_generates_products_cleanly(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => '']);

        [$merchant, $shop] = $this->createMerchant();
        $shop->update([
            'name' => 'Atelier Original',
            'onboarding_data' => [
                'description' => 'Boutique d\'artisanat et textiles authentiques.',
                'business_category' => 'mode',
                'visual_style' => 'moderne',
                'brand_color' => '#9E1B32',
                'suggested_name' => 'Atelier Original',
            ],
        ]);

        // Image rectangulaire 400x200 pour vérifier que le mode 'original' ne la force pas en 1:1 studio blanc
        $rectImage = UploadedFile::fake()->image('tissu-kente.jpg', 400, 200);

        $this->actingAs($merchant)->post('/onboarding/photos', [
            'photos' => [$rectImage],
            'photo_enhancement' => 'original',
        ])->assertRedirect('/onboarding/generation');

        $shop->refresh();
        $this->assertSame('original', $shop->onboarding_data['photo_enhancement']);
        $uploadedPath = $shop->onboarding_data['uploaded_photos'][0];
        $savedBinary = Storage::disk('public')->get($uploadedPath);
        $savedGd = imagecreatefromstring($savedBinary);
        $this->assertNotFalse($savedGd);
        $this->assertGreaterThan(imagesy($savedGd), imagesx($savedGd), 'Le mode original doit préserver le ratio rectangulaire d\'origine sans bandes blanches 1:1.');
        imagedestroy($savedGd);

        // Exécuter l'étape 4 (génération IA complète sur photos uploadées -> ne doit plus planter sur $imageData string)
        $this->actingAs($merchant)->post('/onboarding/generation')
            ->assertRedirect('/onboarding/apercu');

        $product = Product::withoutGlobalScopes()->where('shop_id', $shop->id)->first();
        $this->assertNotNull($product);
        $primary = $product->images()->first();
        $this->assertNotNull($primary);
        $this->assertIsString($primary->image_data);
        $this->assertNotEmpty($primary->image_data);
    }

    public function test_photoroom_cutout_endpoint_for_flyers_and_video_ads_with_cross_shop_protection(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_flyer_key']);

        $fakeCutoutPng = $this->makePngBinary(400, 400, true);
        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('flyer.owner@example.test', 'pro');
        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Montre Royale Dorée',
            'slug' => 'montre-royale-doree',
            'price' => 35000,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $srcPath = "shops/{$shop->id}/products/montre.png";
        Storage::disk('public')->put($srcPath, $this->makePngBinary(400, 400, false));
        $product->images()->create([
            'path' => $srcPath,
            'sort_order' => 0,
            'size_bytes' => 2048,
        ]);

        $response = $this->actingAs($merchant)->postJson(route('ai.products.photoroom_cutout'), [
            'product_id' => $product->id,
            'mode' => 'transparent',
            'save_to_product' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'success',
                'engine' => 'photoroom-ai',
                'cutout_method' => 'photoroom_v1_segment',
            ]);

        $dataUri = $response->json('data_uri');
        $this->assertStringStartsWith('data:image/png;base64,', (string) $dataUri);

        $product->refresh();
        $this->assertCount(2, $product->images()->get());
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ai_photoroom_cutout',
            'model_used' => 'photoroom-ai',
        ]);

        // Isolation multi-boutique : un autre marchand ne doit pas pouvoir détourer ce produit
        [$otherMerchant] = $this->createMerchant('other.merchant@example.test', 'pro');
        $this->actingAs($otherMerchant)->postJson(route('ai.products.photoroom_cutout'), [
            'product_id' => $product->id,
        ])->assertStatus(403);
    }

    public function test_studio_generate_visual_uses_photoroom_for_real_product(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_studio_key']);

        $fakeEditedScene = $this->makePngBinary(1080, 1920, false);
        Http::fake([
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeEditedScene, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('visual.studio@example.test');
        $this->subscribeShopToPlan($shop, 'pro');
        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Parfum Oud Prestige',
            'slug' => 'parfum-oud-prestige',
            'price' => 25000,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $srcPath = "shops/{$shop->id}/products/parfum.png";
        Storage::disk('public')->put($srcPath, $this->makePngBinary(300, 300, false));
        $product->images()->create([
            'path' => $srcPath,
            'sort_order' => 0,
            'size_bytes' => 1500,
        ]);

        $response = $this->actingAs($merchant)->postJson(route('ai.studio.generate'), [
            'subject' => 'Parfum Oud Prestige sur podium doré',
            'style' => 'luxury_showroom',
            'format' => '9:16',
            'product_id' => $product->id,
            'badge_text' => 'NOUVEAU',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'engine' => 'photoroom-ai',
                'model_used' => 'photoroom-v2-edit',
                'style' => 'luxury_showroom',
                'format' => '9:16',
            ]);

        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ai_visual_generation',
            'model_used' => 'photoroom-v2-edit',
        ]);
    }

    public function test_video_ad_campaign_reports_photoroom_visual_engine_when_configured(): void
    {
        config(['services.photoroom.key' => 'pr_video_key']);
        [, $shop] = $this->createMerchant('video.pr@example.test', 'pro');

        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Chaussure Cuir Lomé',
            'slug' => 'chaussure-cuir-lome',
            'price' => 18000,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $campaign = app(VideoAdGeneratorService::class)->generateVideoCampaign($shop, [
            'angle' => VideoAdGeneratorService::ANGLE_NOUVEAUTES,
            'tone' => VideoAdGeneratorService::TONE_DYNAMIQUE,
            'platform' => VideoAdGeneratorService::PLATFORM_TIKTOK,
        ]);

        $this->assertSame('photoroom-ai', $campaign['visual_config']['visual_engine']);
    }

    public function test_onboarding_studio_mode_uses_photoroom_packshot_when_configured(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_onboarding_key']);

        // Smart Cost : l'import produit studio utilise v1/segment (13 FCFA) + ombre studio locale 1:1
        $fakeCutoutPng = $this->makePngBinary(400, 200, true);
        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('studio.onboarding@example.test');
        $rectImage = UploadedFile::fake()->image('baskets.jpg', 400, 200);

        $this->actingAs($merchant)->post('/onboarding/photos', [
            'photos' => [$rectImage],
            'photo_enhancement' => 'studio',
        ])->assertRedirect('/onboarding/generation');

        $shop->refresh();
        $this->assertSame('studio', $shop->onboarding_data['photo_enhancement']);

        $uploadedPath = $shop->onboarding_data['uploaded_photos'][0];
        $savedBinary = Storage::disk('public')->get($uploadedPath);
        $savedGd = imagecreatefromstring($savedBinary);
        $this->assertNotFalse($savedGd);
        $this->assertSame(imagesx($savedGd), imagesy($savedGd), 'Le mode studio Photoroom doit produire un packshot carré 1:1.');
        imagedestroy($savedGd);

        Http::assertSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'sdk.photoroom.com/v1/segment'));
        Http::assertNotSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'v2/edit'));
    }

    public function test_studio_generate_visual_handles_uploaded_image_file_and_local_fallback(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => '']);

        [$merchant, $shop] = $this->createMerchant('upload.fallback@example.test', 'pro');
        $uploadedFile = UploadedFile::fake()->image('article-local.png', 300, 300);

        $response = $this->actingAs($merchant)->postJson(route('ai.studio.generate'), [
            'subject' => 'Robe Wax Moderne',
            'style' => 'flash_sale',
            'format' => '1:1',
            'image' => $uploadedFile,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'engine' => 'zanika-product-studio',
                'style' => 'flash_sale',
                'format' => '1:1',
            ]);
    }

    public function test_photoroom_service_uses_sandbox_key_when_live_key_is_empty(): void
    {
        config([
            'services.photoroom.key' => '',
            'services.photoroom.sandbox_key' => 'sandbox_sk_test_999',
        ]);

        $fakeCutoutPng = $this->makePngBinary(200, 200, true);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $service = new PhotoroomService;
        $this->assertTrue($service->isConfigured());

        $cutout = $service->removeBackground($this->makePngBinary(180, 180, false));
        $this->assertSame($fakeCutoutPng, $cutout);

        Http::assertSent(fn (HttpClientRequest $req) => $req->hasHeader('x-api-key', 'sandbox_sk_test_999'));
    }

    public function test_photoroom_service_prefers_live_key_over_sandbox_key(): void
    {
        config([
            'services.photoroom.key' => 'sk_live_test_777',
            'services.photoroom.sandbox_key' => 'sandbox_sk_test_999',
        ]);

        $fakeCutoutPng = $this->makePngBinary(200, 200, true);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
        ]);

        $service = new PhotoroomService;
        $cutout = $service->removeBackground($this->makePngBinary(180, 180, false));
        $this->assertSame($fakeCutoutPng, $cutout);

        Http::assertSent(fn (HttpClientRequest $req) => $req->hasHeader('x-api-key', 'sk_live_test_777'));
    }

    public function test_photoroom_credits_and_system_ai_credits_are_tracked_separately(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_quota_test_key']);

        $fakeCutoutPng = $this->makePngBinary(300, 300, true);
        $fakeStudioPng = $this->makePngBinary(1000, 1000, false);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeStudioPng, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('quota.split@example.test', 'pro');
        $this->assertSame(350, $shop->aiCreditsRemaining());
        $this->assertSame(60, $shop->photoroomCreditsRemaining());

        // 1. Détourage flyer via Photoroom -> consomme uniquement 1 crédit Photoroom
        $cutoutResponse = $this->actingAs($merchant)->postJson(route('ai.products.photoroom_cutout'), [
            'image' => UploadedFile::fake()->image('sac.png', 280, 280),
            'mode' => 'transparent',
        ]);

        $cutoutResponse->assertOk()
            ->assertJson([
                'success' => true,
                'engine' => 'photoroom-ai',
                'photoroom_credits_remaining' => 59,
                'ai_credits_remaining' => 350,
            ]);

        // 2. Studio publicitaire avec photo produit -> consomme un crédit Photoroom.
        $studioResponse = $this->actingAs($merchant)->postJson(route('ai.studio.generate'), [
            'subject' => 'Sac cuir camel sur marbre',
            'style' => 'luxury_showroom',
            'format' => '1:1',
            'image' => UploadedFile::fake()->image('sac-studio.png', 300, 300),
        ]);

        $studioResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'engine' => 'photoroom-ai',
                'photoroom_credits_remaining' => 58,
                'ai_credits_remaining' => 350,
            ]);

        // 3. Import produit automatique via ImageService -> consomme 1 crédit Photoroom + log dédié
        $uploaded = UploadedFile::fake()->image('robe.png', 320, 320);
        app(ImageService::class)->processAndStore($uploaded, "shops/{$shop->id}/products", 'studio');

        $shop->refresh();
        $this->assertSame(3, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(57, $shop->photoroomCreditsRemaining());
        $this->assertSame(0, $shop->aiCreditsUsedThisMonth());
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ai_photoroom_packshot',
            'model_used' => 'photoroom-v1-segment',
            'cost_estimated_fcfa' => 13,
        ]);
    }

    public function test_exhausted_photoroom_quota_falls_back_to_local_studio_without_calling_photoroom_api(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_blocked_when_quota_zero']);
        Http::fake();

        [$merchant, $shop] = $this->createMerchant('quota.exhausted@example.test', 'pro');
        $shop->update([
            'photoroom_credits_used' => $shop->photoroomCreditsMonthly(),
            'ai_credits_used' => 0,
        ]);

        $this->assertFalse($shop->hasEnoughPhotoroomCredits(1));
        $this->assertTrue($shop->hasEnoughAiCredits(1));

        // L'upload produit et le détourage doivent fonctionner via le moteur local sans aucun appel Photoroom
        $uploaded = UploadedFile::fake()->image('bijou.png', 300, 300);
        $uploadResult = app(ImageService::class)->processAndStore($uploaded, "shops/{$shop->id}/products", 'studio');
        $this->assertSame('zanika-local-studio', $uploadResult['engine']);

        $cutoutResponse = $this->actingAs($merchant)->postJson(route('ai.products.photoroom_cutout'), [
            'image' => UploadedFile::fake()->image('bijou-cutout.png', 280, 280),
            'mode' => 'studio_white',
        ]);

        $cutoutResponse->assertOk()
            ->assertJson([
                'success' => true,
                'engine' => 'zanika-product-studio',
                'photoroom_credits_remaining' => 0,
                'ai_credits_remaining' => 349,
            ]);

        Http::assertNothingSent();

        $shop->refresh();
        $this->assertSame(60, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(1, $shop->aiCreditsUsedThisMonth());
    }

    public function test_smart_cost_uses_v1_segment_on_essentiel_and_v2_edit_on_pro_for_generative_scenes(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_smart_cost_key']);

        $fakeCutoutPng = $this->makePngBinary(300, 300, true);
        $fakeScenePng = $this->makePngBinary(1000, 1000, false);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeScenePng, 200, ['Content-Type' => 'image/png']),
        ]);

        // 1. La fiche produit Essentiel peut utiliser le détourage v1, sans accès au studio pub.
        [$essentielUser, $essentielShop] = $this->createMerchant('smart.essentiel@example.test');
        $this->subscribeShopToPlan($essentielShop, 'essentiel');

        $essentielRes = $this->actingAs($essentielUser)->postJson(route('ai.products.verify_quality'), [
            'style' => 'studio_white',
            'photo' => UploadedFile::fake()->image('montre.png', 300, 300),
        ]);

        $essentielRes->assertOk()->assertJson([
            'status' => 'success',
            'engine' => 'photoroom-ai',
            'photoroom_credits_remaining' => 24,
        ]);

        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $essentielShop->id,
            'type' => 'ai_quality_check',
            'cost_estimated_fcfa' => 13,
        ]);
        Http::assertNotSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'v2/edit'));

        // 2. Boutique Pro -> packshot studio blanc reste en v1/segment (13 FCFA), scène générative active v2/edit (62 FCFA)
        [$proUser, $proShop] = $this->createMerchant('smart.pro@example.test');
        $this->subscribeShopToPlan($proShop, 'pro');

        $proWhiteRes = $this->actingAs($proUser)->postJson(route('ai.studio.generate'), [
            'subject' => 'Baskets blanches',
            'style' => 'studio_minimalist',
            'format' => '1:1',
            'image' => UploadedFile::fake()->image('baskets-pro.png', 300, 300),
        ]);

        $proWhiteRes->assertOk()->assertJson([
            'status' => 'success',
            'engine' => 'photoroom-ai',
            'model_used' => 'photoroom-v1-segment',
            'photoroom_credits_remaining' => 59,
        ]);
        Http::assertNotSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'v2/edit'));

        $proSceneRes = $this->actingAs($proUser)->postJson(route('ai.studio.generate'), [
            'subject' => 'Parfum ambré',
            'style' => 'luxury_showroom',
            'format' => '1:1',
            'image' => UploadedFile::fake()->image('parfum-pro.png', 300, 300),
        ]);

        $proSceneRes->assertOk()->assertJson([
            'status' => 'success',
            'engine' => 'photoroom-ai',
            'model_used' => 'photoroom-v2-edit',
            'photoroom_credits_remaining' => 58,
        ]);

        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $proShop->id,
            'type' => 'ai_visual_generation',
            'model_used' => 'photoroom-v2-edit',
            'cost_estimated_fcfa' => 62,
        ]);
        Http::assertSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'image-api.photoroom.com/v2/edit'));
    }

    public function test_super_admin_can_recharge_and_reset_photoroom_credits_independently(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        [, $shop] = $this->createMerchant('admin.quota@example.test');
        $shop->update([
            'ai_credits_used' => 20,
            'photoroom_credits_used' => 10,
        ]);

        $plan = Plan::firstOrCreate(['slug' => 'essentiel'], [
            'name' => 'Essentiel',
            'monthly_price' => 10000,
            'max_products' => 50,
            'max_shops' => 1,
            'storage_mb' => 2048,
            'ai_credits' => 80,
            'photoroom_credits' => 25,
            'full_generations' => 1,
            'active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.ai_usage'))
            ->assertOk()
            ->assertSee('Appels IA Système')
            ->assertSee('Appels Photoroom Studio');

        // Recharge ciblée Photoroom (+5) : ne doit pas toucher aux crédits IA Système
        $this->actingAs($admin)->post(route('admin.ai.recharge', $shop), [
            'quota_target' => 'photoroom',
            'action_type' => 'add',
            'bonus_credits' => 5,
        ])->assertRedirect();

        $shop->refresh();
        $this->assertSame(5, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(20, $shop->aiCreditsUsedThisMonth());

        // Reset ciblé Photoroom : remet uniquement photoroom_credits_used à 0
        $this->actingAs($admin)->post(route('admin.ai.recharge', $shop), [
            'quota_target' => 'photoroom',
            'action_type' => 'reset_used',
            'bonus_credits' => 0,
        ])->assertRedirect();

        $shop->refresh();
        $this->assertSame(0, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(20, $shop->aiCreditsUsedThisMonth());

        // Mise à jour du quota Photoroom sur la formule par le Super-Admin
        $this->actingAs($admin)->post(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'max_products' => $plan->max_products,
            'max_shops' => $plan->max_shops,
            'storage_mb' => $plan->storage_mb,
            'ai_credits' => 90,
            'photoroom_credits' => 30,
            'full_generations' => $plan->full_generations,
            'active' => '1',
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame(90, $plan->ai_credits);
        $this->assertSame(30, $plan->photoroomCreditsMonthly());

        $this->actingAs($admin)->get(route('admin.plans'))
            ->assertOk()
            ->assertSee('Crédits Photoroom par mois');
    }

    public function test_video_ad_campaign_solution_a_enhances_products_with_photoroom_and_caches_for_reuse(): void
    {
        Storage::fake('public');
        config([
            'services.photoroom.key' => 'pr_video_solution_a_key',
            'services.groq.key' => '',
            'services.openrouter.key' => '',
            'services.gemini.key' => '',
        ]);

        $fakePhotoroomPackshot = $this->makePngBinary(1000, 1000, false);
        Http::fake([
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakePhotoroomPackshot, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('video.solutiona@example.test');
        $this->subscribeShopToPlan($shop, 'pro');

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Montre Élégance Dakar',
            'slug' => 'montre-elegance-dakar',
            'price' => 35000,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $rawPath = "shops/{$shop->id}/products/montre-raw.png";
        Storage::disk('public')->put($rawPath, $this->makePngBinary(300, 300, false));
        $productImage = $product->images()->create([
            'path' => $rawPath,
            'sort_order' => 0,
            'size_bytes' => 1500,
        ]);

        // L'écran de création affiche bien l'option Photoroom Solution A
        $this->actingAs($merchant)->get(route('ads.create'))
            ->assertOk()
            ->assertSee('Détourer les photos des produits');

        // 1. Première vidéo avec sublimation Photoroom -> 1 seul appel Photoroom, mise en cache sur disque
        $firstResponse = $this->actingAs($merchant)->post(route('ads.store'), [
            'angle' => VideoAdGeneratorService::ANGLE_COUP_DE_COEUR,
            'product_id' => $product->id,
            'tone' => VideoAdGeneratorService::TONE_LUXE,
            'channel' => VideoAdGeneratorService::PLATFORM_TIKTOK,
            'target_duration' => 15,
            'enhance_photoroom' => '1',
        ]);

        $firstResponse->assertRedirect();
        $cachePath = "shops/{$shop->id}/products_ai/video_pr_{$productImage->id}.png";
        Storage::disk('public')->assertExists($cachePath);

        $shop->refresh();
        $this->assertSame(1, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(59, $shop->photoroomCreditsRemaining());
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ai_photoroom_video_slide',
            'model_used' => 'photoroom-v2-edit',
            'cost_estimated_fcfa' => 62,
        ]);

        // 2. Deuxième vidéo avec le même produit -> réutilise le cache sans rappeler Photoroom ni consommer de crédit
        $secondResponse = $this->actingAs($merchant)->post(route('ads.store'), [
            'angle' => VideoAdGeneratorService::ANGLE_COUP_DE_COEUR,
            'product_id' => $product->id,
            'tone' => VideoAdGeneratorService::TONE_DYNAMIQUE,
            'channel' => VideoAdGeneratorService::PLATFORM_WHATSAPP,
            'target_duration' => 20,
            'enhance_photoroom' => '1',
        ]);

        $secondResponse->assertRedirect();
        $shop->refresh();
        $this->assertSame(1, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(59, $shop->photoroomCreditsRemaining());

        Http::assertSentCount(1);
    }

    public function test_verify_quality_handles_high_resolution_mobile_photo_and_rejects_corrupt_binary_cleanly(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => '']);

        [$merchant] = $this->createMerchant('highres.studio@example.test');

        $highResPhoto = UploadedFile::fake()->image('iphone-photo.jpg', 2400, 1800);

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => $highResPhoto,
            'category_hint' => 'Sac à main',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('engine', 'zanika-product-studio');

        $variations = $response->json('variations');
        $this->assertIsArray($variations);
        $this->assertCount(2, $variations);

        // Vérifier le rejet JSON clair (avec clé error en français) si binaire corrompu
        $corruptFile = UploadedFile::fake()->createWithContent('broken.heic', 'NOT_AN_IMAGE_BINARY');
        $badResponse = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => $corruptFile,
        ]);

        $badResponse->assertStatus(422)
            ->assertJsonStructure(['status', 'error']);
    }

    public function test_photoroom_service_falls_back_from_live_to_sandbox_key_when_live_returns_402(): void
    {
        config([
            'services.photoroom.key' => 'sk_live_exhausted',
            'services.photoroom.sandbox_key' => 'sandbox_sk_backup',
        ]);

        $fakeCutoutPng = $this->makePngBinary(240, 240, true);
        $fakeEditedPng = $this->makePngBinary(1000, 1000, false);

        Http::fake(function (HttpClientRequest $req) use ($fakeCutoutPng, $fakeEditedPng) {
            $apiKey = $req->header('x-api-key')[0] ?? '';
            if ($apiKey === 'sk_live_exhausted') {
                return Http::response(['error' => 'Live quota exceeded'], 402);
            }

            if ($apiKey === 'sandbox_sk_backup') {
                return str_contains($req->url(), 'v2/edit')
                    ? Http::response($fakeEditedPng, 200, ['Content-Type' => 'image/png'])
                    : Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']);
            }

            return Http::response(['error' => 'Unauthorized'], 401);
        });

        $service = new PhotoroomService;
        $source = $this->makePngBinary(200, 200, false);

        $cutout = $service->removeBackground($source);
        $this->assertSame($fakeCutoutPng, $cutout);

        $edited = $service->editProductImage($source, $service->mapStyleToEditParams('studio_white'));
        $this->assertSame($fakeEditedPng, $edited);

        Http::assertSent(fn (HttpClientRequest $req) => $req->hasHeader('x-api-key', 'sk_live_exhausted'));
        Http::assertSent(fn (HttpClientRequest $req) => $req->hasHeader('x-api-key', 'sandbox_sk_backup'));
    }

    public function test_local_background_segmentation_never_punches_holes_inside_grey_product(): void
    {
        $im = imagecreatetruecolor(300, 300);
        // Fond neutre gris clair
        $outerBg = imagecolorallocate($im, 230, 230, 232);
        imagefilledrectangle($im, 0, 0, 300, 300, $outerBg);

        // Contour sombre du vêtement + intérieur gris quasi identique au fond extérieur
        $seam = imagecolorallocate($im, 45, 45, 50);
        imagefilledrectangle($im, 60, 50, 240, 250, $seam);

        $innerGrey = imagecolorallocate($im, 228, 228, 230);
        imagefilledrectangle($im, 76, 66, 224, 234, $innerGrey);

        ob_start();
        imagepng($im);
        $binary = (string) ob_get_clean();
        imagedestroy($im);

        $cutoutPng = app(ProductStudioService::class)->segmentBackgroundLocally($binary);
        $this->assertNotNull($cutoutPng);

        $cutoutGd = imagecreatefromstring($cutoutPng);
        $this->assertNotFalse($cutoutGd);

        $cornerAlpha = (imagecolorat($cutoutGd, 5, 5) >> 24) & 0x7F;
        $centerAlpha = (imagecolorat($cutoutGd, 150, 150) >> 24) & 0x7F;
        $seamAlpha = (imagecolorat($cutoutGd, 68, 150) >> 24) & 0x7F;

        imagedestroy($cutoutGd);

        $this->assertGreaterThan(100, $cornerAlpha, 'Le fond extérieur doit être détouré.');
        $this->assertSame(0, $seamAlpha, 'La bordure du produit doit rester 100% opaque.');
        $this->assertSame(0, $centerAlpha, 'Aucun trou blanc ne doit apparaître à l\'intérieur d\'un vêtement gris.');
    }

    public function test_missing_quota_column_disables_paid_photoroom_without_runtime_schema_changes(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => 'pr_autorepair_key']);

        $fakeCutoutPng = $this->makePngBinary(400, 400, true);
        $fakeScenePng = $this->makePngBinary(1000, 1000, false);

        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($fakeCutoutPng, 200, ['Content-Type' => 'image/png']),
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeScenePng, 200, ['Content-Type' => 'image/png']),
        ]);

        [$merchant, $shop] = $this->createMerchant('autorepair.studio@example.test');
        $this->subscribeShopToPlan($shop, 'pro');

        // Simuler une base de production où la migration photoroom_credits_used avait été bloquée
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('photoroom_credits_used');
        });
        $this->assertFalse(Schema::hasColumn('shops', 'photoroom_credits_used'));

        $photo = UploadedFile::fake()->image('pantalon.jpg', 500, 500);
        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => $photo,
            'category_hint' => 'Pantalon jogging',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('engine', 'zanika-product-studio');

        $this->assertFalse(Schema::hasColumn('shops', 'photoroom_credits_used'));
        $shop->refresh();
        $this->assertFalse($shop->hasEnoughPhotoroomCredits());
        $this->assertNotEmpty($response->json('variations'));
    }
}
