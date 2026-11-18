<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ProductStudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductStudioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.product_studio.scene_provider' => 'photoroom', 'services.rembg.enabled' => false]);
    }

    private function createMerchant(string $email = 'merchant.studio@example.test'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique Studio '.$user->name,
            'status' => 'draft',
        ]);
        $shop->subscriptions()->create(['status' => 'active', 'plan_id' => Plan::where('slug', 'essentiel')->firstOrFail()->id]);

        return [$user, $shop];
    }

    /**
     * Crée une image de test synthétique avec un objet coloré au centre et un fond clair
     */
    private function createSampleProductImage(int $width = 300, int $height = 300): string
    {
        $im = imagecreatetruecolor($width, $height);
        // Fond gris très clair (style photo smartphone amateur)
        $bg = imagecolorallocate($im, 240, 240, 242);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        // Objet produit (rouge bordeaux, par exemple une chaussure ou flacon)
        $productColor = imagecolorallocate($im, 180, 25, 45);
        $cx = (int) round($width / 2);
        $cy = (int) round($height / 2);
        imagefilledellipse($im, $cx, $cy, (int) round($width * 0.6), (int) round($height * 0.4), $productColor);

        ob_start();
        imagejpeg($im, null, 90);
        $binary = ob_get_clean();
        imagedestroy($im);

        return $binary;
    }

    private function sampleMask(): string
    {
        $image = imagecreatetruecolor(300, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, 150, 150, 180, 120, imagecolorallocatealpha($image, 0, 255, 0, 0));
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    public function test_manual_mask_and_prepared_scene_work_without_any_photoroom_configuration(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => '', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $source = $this->createSampleProductImage();
        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $source),
            'subject_mask' => UploadedFile::fake()->createWithContent('contours.png', $this->sampleMask()),
            'style' => 'scene_template', 'scene_template' => 'podium', 'shadow' => 20,
        ])->assertOk()->assertJsonPath('cutout_method', 'manual_mask')->assertJsonPath('variations.0.title', 'Podium en pierre');

        $binary = Storage::disk('public')->get($response->json('variations.0.path'));
        $size = getimagesizefromstring($binary);
        $this->assertSame([1000, 1000], [$size[0], $size[1]]);
        $cutout = imagecreatefromstring(Storage::disk('public')->get($response->json('cutout_path')));
        $center = imagecolorsforindex($cutout, imagecolorat($cutout, 150, 150));
        $this->assertGreaterThan(150, $center['red']);
        $this->assertLessThan(50, $center['green'], 'Only the mask alpha is used; its green RGB pixels must never replace the product.');
        $this->assertSame(127, imagecolorsforindex($cutout, imagecolorat($cutout, 0, 0))['alpha']);
        imagedestroy($cutout);
        $this->assertSame($source, Storage::disk('public')->get($response->json('source_path')));
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertNothingSent();
    }

    public function test_custom_background_preserves_backdrop_and_obeys_product_placement(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant, $shop] = $this->createMerchant();
        $background = imagecreatetruecolor(600, 300);
        imagefill($background, 0, 0, imagecolorallocate($background, 20, 40, 180));
        ob_start();
        imagepng($background);
        $backdrop = (string) ob_get_clean();
        imagedestroy($background);
        $bounds = [];
        foreach ([20, 80] as $x) {
            $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
                'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $this->createSampleProductImage()),
                'subject_mask' => UploadedFile::fake()->createWithContent('contours.png', $this->sampleMask()),
                'background' => UploadedFile::fake()->createWithContent('decor.png', $backdrop),
                'style' => 'custom_background', 'position_x' => $x, 'position_y' => 80, 'scale' => 30, 'shadow' => 0,
            ])->assertOk()->assertJsonPath('variations.0.title', 'Votre décor importé');
            $image = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
            $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
            $this->assertEqualsWithDelta(180, $corner['blue'], 5);
            $left = 1000;
            $right = 0;
            for ($row = 0; $row < 1000; $row += 5) {
                for ($col = 0; $col < 1000; $col += 5) {
                    $pixel = imagecolorsforindex($image, imagecolorat($image, $col, $row));
                    if ($pixel['red'] > 130 && $pixel['blue'] < 80) {
                        $left = min($left, $col);
                        $right = max($right, $col);
                    }
                }
            }
            $this->assertGreaterThanOrEqual(15, $left);
            $this->assertLessThanOrEqual(985, $right);
            $bounds[] = ($left + $right) / 2;
            imagedestroy($image);
        }
        $this->assertGreaterThan(400, $bounds[1] - $bounds[0]);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertNothingSent();
    }

    public function test_incomplete_manual_cutout_does_not_call_a_paid_provider_or_spend_credits(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => 'test-key']);
        [$merchant, $shop] = $this->createMerchant();
        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('source.jpg', 300, 300),
            'subject_mask' => UploadedFile::fake()->image('contours.png', 300, 300),
            'style' => 'scene_template', 'scene_template' => 'podium',
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertNothingSent();
    }

    public function test_scene_validation_rejects_missing_backdrop_unsafe_template_and_excessive_adjustment(): void
    {
        Http::preventStrayRequests();
        [$merchant, $shop] = $this->createMerchant();
        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('source.jpg'), 'style' => 'custom_background',
            'scene_template' => '../outside', 'brightness' => 80,
        ])->assertUnprocessable()->assertJsonValidationErrors(['background', 'scene_template', 'brightness']);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
    }

    public function test_light_adjustment_changes_luminance_without_changing_source_file(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant] = $this->createMerchant();
        $source = $this->createSampleProductImage();
        $reds = [];
        foreach ([-10, 10] as $brightness) {
            $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
                'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $source),
                'style' => 'retouch', 'brightness' => $brightness, 'contrast' => 0, 'sharpen' => false,
            ])->assertOk();
            $image = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
            $reds[] = imagecolorsforindex($image, imagecolorat($image, 500, 500))['red'];
            imagedestroy($image);
            $this->assertSame($source, Storage::disk('public')->get($response->json('source_path')));
        }
        $this->assertGreaterThan(15, $reds[1] - $reds[0]);
        Http::assertNothingSent();
    }

    public function test_retouch_removes_phone_bars_and_lifts_dark_product_details(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant] = $this->createMerchant();
        $source = imagecreatetruecolor(240, 320);
        imagefilledrectangle($source, 0, 40, 239, 279, 0x625347);
        imagefilledrectangle($source, 38, 315, 202, 317, 0xFFFFFF);
        ob_start();
        imagepng($source);
        $original = (string) ob_get_clean();

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('capture.png', $original),
            'style' => 'retouch',
        ])->assertOk();

        $render = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
        $center = imagecolorsforindex($render, imagecolorat($render, 500, 500));
        $this->assertGreaterThan(100, $center['red']);
        $this->assertEqualsWithDelta(15, $center['red'] - $center['green'], 3);
        $this->assertEqualsWithDelta($center['red'], (imagecolorat($render, 500, 20) >> 16) & 255, 3);
        $this->assertEqualsWithDelta($center['red'], (imagecolorat($render, 500, 980) >> 16) & 255, 3);
        $this->assertSame($original, Storage::disk('public')->get($response->json('source_path')));
        Http::assertNothingSent();
    }

    public function test_explicit_crop_keeps_manual_cutout_aligned_with_original_photo(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant] = $this->createMerchant();
        $original = $this->createSampleProductImage();

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $original),
            'subject_mask' => UploadedFile::fake()->createWithContent('contours.png', $this->sampleMask()),
            'style' => 'studio_white',
            'crop_left' => 20,
        ])->assertOk()->assertJsonPath('cutout_method', 'manual_mask');

        $response->assertJsonPath('crop_frame.x', 60)->assertJsonPath('crop_frame.width', 240);
        $cutout = imagecreatefromstring(Storage::disk('public')->get($response->json('cutout_path')));
        $center = imagecolorsforindex($cutout, imagecolorat($cutout, 90, 150));
        $this->assertGreaterThan(150, $center['red']);
        $this->assertSame(0, $center['alpha']);
        $this->assertSame(127, (imagecolorat($cutout, 0, 0) >> 24) & 127);
        $this->assertSame($original, Storage::disk('public')->get($response->json('source_path')));
        Http::assertNothingSent();
    }

    public function test_dressing_places_both_garments_between_the_rack_posts(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant] = $this->createMerchant();
        $source = imagecreatetruecolor(240, 220);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, 0x7F000000);
        imagefilledrectangle($source, 10, 20, 98, 200, 0xBE332B);
        imagefilledrectangle($source, 140, 20, 230, 200, 0x224DAF);
        ob_start();
        imagepng($source);
        $original = (string) ob_get_clean();

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('pantalons.png', $original),
            'style' => 'scene_template', 'scene_template' => 'dressing',
        ])->assertOk()->assertJsonPath('cutout_method', 'source_transparency');

        $render = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
        $red = imagecolorsforindex($render, imagecolorat($render, 330, 515));
        $blue = imagecolorsforindex($render, imagecolorat($render, 650, 515));
        $this->assertGreaterThan(85, $red['red'] - $red['blue']);
        $this->assertGreaterThan(85, $blue['blue'] - $blue['red']);
        $gap = imagecolorsforindex($render, imagecolorat($render, 490, 515));
        $this->assertLessThan(60, abs($gap['red'] - $gap['blue']));
        Http::assertNothingSent();
    }

    /**
     * Test 1 : Détection intelligente de la catégorie pour toutes les catégories e-commerce requises
     */
    public function test_detect_product_category_for_all_required_categories(): void
    {
        $service = new ProductStudioService;
        $sampleBinary = $this->createSampleProductImage(100, 100);

        // A. Chaussures à talons
        $res = $service->detectProductCategory($sampleBinary, 'Escarpins en cuir noir à talons aiguilles');
        $this->assertEquals(ProductStudioService::CATEGORY_SHOES, $res['category']);

        // B. Chaussure de sport
        $res = $service->detectProductCategory($sampleBinary, 'Sneakers de sport Nike Air running');
        $this->assertEquals(ProductStudioService::CATEGORY_SHOES, $res['category']);

        // C. Sac à main
        $res = $service->detectProductCategory($sampleBinary, 'Sac à main en cuir italien de luxe');
        $this->assertEquals(ProductStudioService::CATEGORY_BAGS, $res['category']);

        // D. Vêtement sur cintre
        $res = $service->detectProductCategory($sampleBinary, 'Robe de soirée élégante en soie sur cintre');
        $this->assertEquals(ProductStudioService::CATEGORY_CLOTHING_HANGING, $res['category']);

        // E. Vêtement à plat
        $res = $service->detectProductCategory($sampleBinary, 'Pantalon jean denim classique');
        $this->assertEquals(ProductStudioService::CATEGORY_CLOTHING_FLAT, $res['category']);

        // F. Smartphone & Électronique
        $res = $service->detectProductCategory($sampleBinary, 'Smartphone iPhone 15 Pro Max 256GB');
        $this->assertEquals(ProductStudioService::CATEGORY_ELECTRONICS, $res['category']);

        // G. Parfum & Cosmétiques
        $res = $service->detectProductCategory($sampleBinary, 'Eau de parfum de luxe flacon 100ml');
        $this->assertEquals(ProductStudioService::CATEGORY_COSMETICS, $res['category']);

        // H. Meubles & Décoration
        $res = $service->detectProductCategory($sampleBinary, 'Fauteuil contemporain scandinave en bois');
        $this->assertEquals(ProductStudioService::CATEGORY_FURNITURE, $res['category']);

        // I. Bijoux
        $res = $service->detectProductCategory($sampleBinary, 'Bague solitaire or blanc et diamant');
        $this->assertEquals(ProductStudioService::CATEGORY_JEWELRY, $res['category']);
    }

    /**
     * Test 2 : Détourage local haute fidélité et rognage des marges transparentes
     * Règle 4.A : Le produit doit être conservé pixel pour pixel sans altération
     */
    public function test_local_background_segmentation_and_bounding_box_cropping(): void
    {
        $service = new ProductStudioService;
        $sampleBinary = $this->createSampleProductImage(300, 300);

        // 1. Segmentation
        $cutoutPng = $service->segmentBackgroundLocally($sampleBinary);
        $this->assertNotNull($cutoutPng);
        $this->assertGreaterThan(500, strlen($cutoutPng));

        $cutoutGd = imagecreatefromstring($cutoutPng);
        $this->assertNotFalse($cutoutGd);

        // 2. Vérifier que les 4 coins sont bien rendus transparents
        $cornerAlpha = (imagecolorat($cutoutGd, 5, 5) >> 24) & 0x7F;
        $this->assertGreaterThan(100, $cornerAlpha, 'Le fond doit être rendu transparent.');

        // 3. Vérifier que le centre (l\'objet produit) a conservé sa couleur d\'origine (Règle 4.A)
        $centerCol = imagecolorat($cutoutGd, 150, 150);
        $r = ($centerCol >> 16) & 0xFF;
        $g = ($centerCol >> 8) & 0xFF;
        $b = $centerCol & 0xFF;
        $alpha = ($centerCol >> 24) & 0x7F;

        $this->assertLessThan(20, $alpha, 'Le produit doit être opaque et préservé.');
        $this->assertGreaterThan(120, $r, 'La couleur rouge du produit doit être conservée fidèlement.');

        // 4. Bounding box cropping
        $cropped = $service->cropTransparentBoundingBox($cutoutGd);
        $cropW = imagesx($cropped);
        $cropH = imagesy($cropped);

        $this->assertLessThan(300, $cropW, 'Les marges transparentes inutiles doivent être rognées.');
        $this->assertLessThan(300, $cropH, 'Les marges transparentes inutiles doivent être rognées.');

        imagedestroy($cutoutGd);
        if ($cropped !== $cutoutGd) {
            imagedestroy($cropped);
        }
    }

    /**
     * Test 3 : Rendu des packshots studio 1:1 carrés et ombres physiques de contact
     */
    public function test_render_studio_packshots_square_ratio_and_physics_shadows(): void
    {
        $service = new ProductStudioService;
        $sampleBinary = $this->createSampleProductImage(200, 200);
        $cutoutPng = $service->segmentBackgroundLocally($sampleBinary);
        $cutoutGd = imagecreatefromstring($cutoutPng);

        // A. Studio Blanc Épuré (Amazon/Shopify standard 1000x1000)
        $whiteCanvas = $service->renderStudioPackshot($cutoutGd, 'white', 1000, 'shoes');
        $this->assertNotNull($whiteCanvas);
        $this->assertEquals(1000, imagesx($whiteCanvas));
        $this->assertEquals(1000, imagesy($whiteCanvas));
        imagedestroy($whiteCanvas);

        // B. Ambiance Douce Ivoire
        $ambientCanvas = $service->renderStudioPackshot($cutoutGd, 'ambient_warm', 1000, 'shoes');
        $this->assertNotNull($ambientCanvas);
        $this->assertEquals(1000, imagesx($ambientCanvas));
        $this->assertEquals(1000, imagesy($ambientCanvas));
        imagedestroy($ambientCanvas);

        // C. Studio Podium Minimaliste
        $podiumCanvas = $service->renderPodiumPackshot($cutoutGd, 1000, 'shoes');
        $this->assertNotNull($podiumCanvas);
        $this->assertEquals(1000, imagesx($podiumCanvas));
        $this->assertEquals(1000, imagesy($podiumCanvas));
        imagedestroy($podiumCanvas);

        // D. Décor Catégorisé (Chaussures sur parquet)
        $lifestyle = $service->renderCategoryLifestyle($cutoutGd, 'shoes', 1000);
        $this->assertNotNull($lifestyle);
        $this->assertArrayHasKey('canvas', $lifestyle);
        $this->assertEquals(1000, imagesx($lifestyle['canvas']));
        $this->assertEquals(1000, imagesy($lifestyle['canvas']));
        imagedestroy($lifestyle['canvas']);

        imagedestroy($cutoutGd);
    }

    /**
     * Test 4 : Cycle complet processProductPhoto (Conservation de l\'originale + Quality Gate)
     */
    public function test_process_product_photo_complete_pipeline(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        $service = new ProductStudioService;
        $sampleBinary = $this->createSampleProductImage(250, 250);

        $result = $service->processProductPhoto($shop, $sampleBinary, 'Escarpins talons hauts cuir');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(ProductStudioService::CATEGORY_SHOES, $result['category']);
        $this->assertNotEmpty($result['source_path']);
        $this->assertTrue(Storage::disk('public')->exists($result['source_path']));

        // Variations produites
        $variations = $result['variations'];
        $this->assertGreaterThanOrEqual(4, count($variations));

        // Règle 3 & 6.8 : La photo source originale doit TOUJOURS être présente dans les choix
        $hasSource = collect($variations)->contains(fn ($v) => $v['is_source'] === true);
        $this->assertTrue($hasSource, 'La photo source originale doit toujours être proposée.');

        // Quality Gate automatique
        $audit = $result['quality_audit'];
        $this->assertNotNull($audit);
        $this->assertGreaterThanOrEqual(80, $audit['score']);
        $this->assertTrue($audit['checks']['product_preserved']);
        $this->assertTrue($audit['checks']['square_ratio']);
    }

    /**
     * Test 5 : Contrôleur POST /assistant-ia/produits/verifier-qualite
     */
    public function test_verify_quality_controller_endpoint(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        $file = UploadedFile::fake()->image('chaussure.jpg', 400, 400);

        $response = $this->actingAs($merchant)->postJson('/assistant-ia/produits/verifier-qualite', [
            'photo' => $file,
            'category_hint' => 'Chaussures sport basket',
            'style' => null,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'category',
            'product_title',
            'source_path',
            'source_url',
            'variations',
            'quality_audit',
        ]);

        $json = $response->json();
        $this->assertEquals('success', $json['status']);
        $this->assertEquals(ProductStudioService::CATEGORY_SHOES, $json['category']);
        $this->assertNotEmpty($json['variations']);

        // Vérification de la journalisation et des crédits
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ai_quality_check',
            'step' => 'studio_packshot',
        ]);
        $shop->refresh();
        $this->assertEquals(0, $shop->ai_credits_used);
    }

    /**
     * Test 6 : Contrôleur POST /assistant-ia/produits/variante
     */
    public function test_generate_variant_controller_endpoint(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        // 1. Créer d'abord un fichier de détourage factice
        $service = new ProductStudioService;
        $sampleBinary = $this->createSampleProductImage(200, 200);
        $cutoutPng = $service->segmentBackgroundLocally($sampleBinary);

        $cutoutPath = "shops/{$shop->id}/products_ai/cutout_test.png";
        Storage::disk('public')->put($cutoutPath, $cutoutPng);

        // 2. Demander une variante Studio Sombre Graphite
        $response = $this->actingAs($merchant)->postJson(route('ai.products.generate_variant'), [
            'cutout_path' => $cutoutPath,
            'style' => 'studio_dark',
            'category' => 'shoes',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'variation' => [
                'type' => 'studio_dark',
                'title' => 'Studio Sombre Graphite',
            ],
        ]);

        $shop->refresh();
        $this->assertEquals(0, $shop->ai_credits_used);
    }

    /**
     * Test 7 : Conservation de la photo choisie lors de la création de produit (ai_temp_photo_path)
     */
    public function test_product_store_attaches_studio_packshot(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        $packshotPath = "shops/{$shop->id}/products_ai/studio_packshot_confirmed.jpg";
        Storage::disk('public')->put($packshotPath, $this->createSampleProductImage(1000, 1000));

        $response = $this->actingAs($merchant)->post('/produits', [
            'title' => 'Chaussure Studio Pro',
            'price' => 25000,
            'status' => 'active',
            'ai_temp_photo_path' => $packshotPath,
        ]);

        $response->assertRedirect('/produits');
        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'title' => 'Chaussure Studio Pro',
        ]);

        $product = Product::where('title', 'Chaussure Studio Pro')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->images);
        $this->assertEquals($packshotPath, $product->images->first()->path);
    }

    public function test_preview_generates_only_requested_background_and_keeps_exact_source(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => '', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $source = $this->createSampleProductImage();

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $source), 'style' => 'studio_dark',
        ])->assertOk()->assertJsonPath('variations.0.type', 'studio_dark')->assertJsonPath('quality_audit.status', 'review_required');

        $this->assertCount(2, $response->json('variations'));
        $this->assertSame($source, Storage::disk('public')->get($response->json('source_path')));
        $image = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
        $this->assertSame(1000, imagesx($image));
        $corner = imagecolorsforindex($image, imagecolorat($image, 5, 5));
        $this->assertLessThan(50, $corner['red']);
        imagedestroy($image);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
    }

    public function test_uncertain_cutout_is_rejected_without_charging_or_creating_fake_decor(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => '', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $source = file_get_contents(public_path('images/catalog/burger.webp'));

        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.webp', $source), 'style' => 'studio_white',
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertNothingSent();
    }

    public function test_unavailable_custom_scene_is_rejected_and_refunds_reserved_credits(): void
    {
        Http::preventStrayRequests();
        config(['services.photoroom.key' => '', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();

        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('photo.jpg'), 'style' => 'lifestyle', 'scene_prompt' => 'Une table en bois sous une lumière douce',
        ])->assertUnprocessable()->assertJsonValidationErrors('style');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
    }

    public function test_saving_an_uploaded_photo_preserves_background_and_does_not_call_photoroom(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => 'test-key']);
        [$merchant, $shop] = $this->createMerchant();
        $source = $this->createSampleProductImage(400, 200);

        $this->actingAs($merchant)->post(route('products.store'), [
            'title' => 'Photo approuvée', 'price' => 5000, 'status' => 'active',
            'photos' => [UploadedFile::fake()->createWithContent('photo.jpg', $source)],
        ])->assertRedirect(route('products.index'));

        $product = $shop->products()->firstOrFail();
        $image = imagecreatefromstring(Storage::disk('public')->get($product->images->first()->path));
        $this->assertSame(400, imagesx($image));
        $this->assertSame(200, imagesy($image));
        imagedestroy($image);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertNothingSent();
    }

    public function test_existing_photo_can_be_replaced_at_gallery_limit_without_losing_other_photos(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant, $shop] = $this->createMerchant();
        $product = $shop->products()->create(['title' => 'Produit', 'slug' => 'produit', 'price' => 5000, 'status' => 'active']);
        for ($i = 0; $i < 8; $i++) {
            $product->images()->create(['path' => "shops/{$shop->id}/products/old-{$i}.jpg", 'sort_order' => $i, 'image_data' => base64_encode($this->createSampleProductImage())]);
        }
        $original = $product->images()->orderBy('sort_order')->firstOrFail();
        $remaining = $product->images()->where('id', '!=', $original->id)->pluck('path', 'id')->all();

        $this->actingAs($merchant)->put(route('products.update', $product), [
            'title' => $product->title, 'price' => 5000, 'status' => 'active',
            'studio_replace_image_id' => $original->id, 'photos' => [UploadedFile::fake()->image('retouche.jpg', 300, 300)],
        ])->assertRedirect(route('products.index'));

        $this->assertSame(8, $product->images()->count());
        $this->assertNotSame($original->path, $original->fresh()->path);
        $this->assertSame(0, $original->fresh()->sort_order);
        $this->assertSame($remaining, $product->images()->where('id', '!=', $original->id)->pluck('path', 'id')->all());
        Http::assertNothingSent();
    }

    public function test_replacing_a_foreign_product_photo_is_rejected_before_changing_product(): void
    {
        [$merchant, $shop] = $this->createMerchant();
        [, $otherShop] = $this->createMerchant('other.photo@example.test');
        $product = $shop->products()->create(['title' => 'Produit', 'slug' => 'produit', 'price' => 5000, 'status' => 'active']);
        $otherProduct = $otherShop->products()->create(['title' => 'Autre', 'slug' => 'autre', 'price' => 9000, 'status' => 'active']);
        $image = $otherProduct->images()->create(['path' => "shops/{$otherShop->id}/products/photo.jpg"]);

        $this->actingAs($merchant)->putJson(route('products.update', $product), [
            'title' => 'Titre modifié', 'price' => 5000, 'status' => 'active', 'studio_replace_image_id' => $image->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('studio_replace_image_id');

        $this->assertSame('Produit', $product->fresh()->title);
    }

    public function test_custom_scene_uses_merchant_prompt_in_one_provider_call_and_one_photoroom_credit(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => 'test-key', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $merchant->update(['role' => User::ROLE_ADMIN]);
        $source = $this->createSampleProductImage();
        $scene = imagecreatefromstring($source);
        ob_start();
        imagepng($scene);
        $result = ob_get_clean();
        imagedestroy($scene);
        Http::fake(['https://image-api.photoroom.com/v2/edit' => Http::response($result, 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.jpg', $source), 'style' => 'lifestyle', 'scene_prompt' => 'Une table en bois sous une lumière douce',
        ])->assertOk()->assertJsonPath('variations.0.type', 'lifestyle')->assertJsonPath('engine', 'photoroom-ai');

        $this->assertSame(1, $shop->fresh()->photoroom_credits_used);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name');

            return $request->url() === 'https://image-api.photoroom.com/v2/edit'
                && str_contains($fields['background.prompt'], 'Une table en bois sous une lumière douce')
                && $fields['lighting.mode'] === 'ai.preserve-hue-and-saturation'
                && ! $fields->has('shadow.mode');
        });
        Http::assertSentCount(1);
    }

    public function test_failed_scene_refunds_both_quotas_and_does_not_return_a_local_substitute(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => 'test-key', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $merchant->update(['role' => User::ROLE_ADMIN]);
        Http::fake(['https://image-api.photoroom.com/v2/edit' => Http::response([], 503)]);

        $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('photo.jpg'), 'style' => 'lifestyle', 'scene_prompt' => 'Une table en bois sous une lumière douce',
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSentCount(1);
    }

    #[TestWith([401, 'accès'])]
    #[TestWith([402, 'facturation'])]
    #[TestWith([403, 'n’autorise pas'])]
    #[TestWith([400, 'paramètres'])]
    #[TestWith([422, 'paramètres'])]
    #[TestWith([429, 'limite'])]
    #[TestWith([503, 'indisponible'])]
    public function test_provider_rejection_is_explained_without_exposing_private_response(int $status, string $explanation): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.photoroom.key' => 'test-key', 'services.photoroom.sandbox_key' => '']);
        [$merchant, $shop] = $this->createMerchant();
        $merchant->update(['role' => User::ROLE_ADMIN]);
        Http::fake(['https://image-api.photoroom.com/v2/edit' => Http::response(['error' => ['message' => 'PRIVATE_PROVIDER_MESSAGE']], $status)]);

        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->image('photo.jpg'), 'style' => 'lifestyle', 'scene_prompt' => 'Décor chambre',
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');

        $error = $response->json('errors.photo.0');
        $this->assertStringContainsString($explanation, $error);
        $this->assertStringContainsString('Référence :', $error);
        $this->assertStringNotContainsString('PRIVATE_PROVIDER_MESSAGE', $response->getContent());
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertSentCount(1);
    }

    public function test_product_forms_render_without_generating_previews_or_claiming_perfect_fidelity(): void
    {
        Http::preventStrayRequests();
        [$merchant, $shop] = $this->createMerchant();
        $product = $shop->products()->create(['title' => 'Mon produit', 'slug' => 'produit', 'price' => 5000, 'status' => 'active']);
        $create = $this->actingAs($merchant)->get(route('products.create'))->assertOk()->assertSee('product-photo-studio')->assertDontSee('100% Fidèle');
        for ($i = 0; $i < 8; $i++) {
            $product->images()->create(['path' => "shops/{$shop->id}/products/photo-{$i}.jpg", 'sort_order' => $i]);
        }
        $edit = $this->get(route('products.edit', $product))->assertOk()->assertSee('studio_replace_image_id')->assertSee('Ouvrir le studio photo')->assertSee('master-photos-input');
        if (getenv('CREATIVE_STUDIO_REVIEW') === '1') {
            foreach (['product-create' => $create, 'product-edit' => $edit] as $name => $response) {
                file_put_contents(storage_path('app/private/studio-review-'.$name.'.html'), str_replace(
                    ['http://localhost:8080', 'http://localhost'], ['http://127.0.0.1:8127', 'http://127.0.0.1:8127'], $response->getContent()
                ));
            }
        }
        Http::assertNothingSent();
    }

    public function test_light_retouch_preserves_the_scene_and_returns_an_explicit_preview(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        [$merchant] = $this->createMerchant();
        $source = file_get_contents(public_path('images/catalog/burger.webp'));
        $response = $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), [
            'photo' => UploadedFile::fake()->createWithContent('photo.webp', $source), 'style' => 'retouch',
        ])->assertOk()->assertJsonPath('cutout_method', 'none')->assertJsonPath('variations.0.title', 'Photo recadrée et retouchée');

        $this->assertCount(2, $response->json('variations'));
        $this->assertSame($source, Storage::disk('public')->get($response->json('source_path')));
        $rendered = Storage::disk('public')->get($response->json('variations.0.path'));
        $dimensions = getimagesizefromstring($rendered);
        $this->assertSame([1000, 1000], [$dimensions[0], $dimensions[1]]);
        if (getenv('CREATIVE_STUDIO_REVIEW') === '1') {
            file_put_contents(storage_path('app/private/product-retouch-review.jpg'), $rendered);
        }
        Http::assertNothingSent();
    }
}
