<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ProductStudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductStudioServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(string $email = 'merchant.studio@example.test'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique Studio ' . $user->name,
            'status' => 'draft',
        ]);

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

    /**
     * Test 1 : Détection intelligente de la catégorie pour toutes les catégories e-commerce requises
     */
    public function test_detect_product_category_for_all_required_categories(): void
    {
        $service = new ProductStudioService();
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
        $service = new ProductStudioService();
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
        $service = new ProductStudioService();
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

        $service = new ProductStudioService();
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
        $hasSource = collect($variations)->contains(fn($v) => $v['is_source'] === true);
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
        $this->assertEquals(12, $shop->ai_credits_used);
    }

    /**
     * Test 6 : Contrôleur POST /assistant-ia/produits/variante
     */
    public function test_generate_variant_controller_endpoint(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        // 1. Créer d'abord un fichier de détourage factice
        $service = new ProductStudioService();
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
        $this->assertEquals(6, $shop->ai_credits_used);
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
}
