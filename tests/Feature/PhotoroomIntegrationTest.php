<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\PhotoroomService;
use App\Services\Ai\ProductStudioService;
use App\Services\VideoAdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoroomIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(string $email = 'merchant.pr@example.test'): array
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
            'subdomain' => 'maison-pr-' . $user->id,
        ]);

        return [$user, $shop];
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

        $service = new PhotoroomService();
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

        $service = new PhotoroomService();
        $result = $service->studioPackshot($this->makePngBinary(250, 250), 'studio_white');

        $this->assertNotNull($result);
        $this->assertSame($fakeCutoutPng, $result);
    }

    public function test_product_studio_service_uses_photoroom_when_configured_and_falls_back_to_local(): void
    {
        Storage::fake('public');
        [, $shop] = $this->createMerchant();
        $source = $this->makePngBinary(260, 260, false);

        // 1. Photoroom actif
        config(['services.photoroom.key' => 'pr_active_key']);
        Http::fake([
            'https://sdk.photoroom.com/v1/segment' => Http::response($this->makePngBinary(260, 260, true), 200),
            'https://image-api.photoroom.com/v2/edit' => Http::response($this->makePngBinary(1000, 1000, false), 200),
        ]);

        $studio = new ProductStudioService();
        $prResult = $studio->processProductPhoto($shop, $source, 'Sac à main cuir');

        $this->assertSame('photoroom-ai', $prResult['engine']);
        $this->assertSame('photoroom_v2_edit', $prResult['cutout_method']);
        $this->assertNotEmpty($prResult['cutout_path']);
        $this->assertTrue(collect($prResult['variations'])->contains(fn ($v) => ($v['badge'] ?? '') === 'Photoroom IA'));

        // 2. Fallback sans clé API
        config(['services.photoroom.key' => '']);
        $localStudio = new ProductStudioService();
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

        [$merchant, $shop] = $this->createMerchant('flyer.owner@example.test');
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
        [$otherMerchant] = $this->createMerchant('other.merchant@example.test');
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
        [, $shop] = $this->createMerchant('video.pr@example.test');

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

        $fakeStudioSquare = $this->makePngBinary(1000, 1000, false);
        Http::fake([
            'https://image-api.photoroom.com/v2/edit' => Http::response($fakeStudioSquare, 200, ['Content-Type' => 'image/png']),
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

        Http::assertSent(fn (HttpClientRequest $req) => str_contains($req->url(), 'image-api.photoroom.com/v2/edit'));
    }

    public function test_studio_generate_visual_handles_uploaded_image_file_and_local_fallback(): void
    {
        Storage::fake('public');
        config(['services.photoroom.key' => '']);

        [$merchant, $shop] = $this->createMerchant('upload.fallback@example.test');
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
}
