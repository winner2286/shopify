<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\AiVisionService;
use App\Services\Ai\Tools\CreateProductFromPhotoTool;
use App\Services\Ai\Tools\GenerateVideoAdScriptTool;
use App\Services\VideoAdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected Shop $shop;
    protected User $user;
    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proPlan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Professionnel',
                'monthly_price' => 15000,
                'max_products' => 200,
                'storage_mb' => 4096,
                'ai_credits' => 300,
                'full_generations' => 3,
                'active' => true,
            ]
        );

        $this->user = User::factory()->create([
            'email' => 'merchant_phase3@zanika.shop',
            'role' => 'merchant',
            'email_verified_at' => now(),
        ]);

        $this->shop = Shop::create([
            'user_id' => $this->user->id,
            'name' => 'Kente & Wax Mode',
            'slug' => 'kente-wax',
            'subdomain' => 'kente-wax',
            'currency' => 'FCFA',
            'whatsapp' => '+22890112233',
            'phone' => '+22890112233',
            'is_active' => true,
        ]);

        Subscription::create([
            'shop_id' => $this->shop->id,
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
        ]);
    }

    public function test_create_product_from_photo_flow(): void
    {
        $mockVision = $this->createMock(AiVisionService::class);
        $mockVision->method('analyzeProductPhoto')->willReturn([
            'title' => 'Robe Africaine Wax Royale',
            'description' => 'Superbe robe traditionnelle confectionnée avec soin en pur coton wax.',
            'category_name' => 'Mode Féminine',
            'price_fcfa' => 18500,
            'compare_at_price_fcfa' => 25000,
            'seo_keywords' => ['robe', 'wax', 'africaine', 'mode', 'coton'],
            'price_range' => [
                'min_fcfa' => 15000,
                'max_fcfa' => 22000,
                'recommended_fcfa' => 18500,
            ],
        ]);

        $tool = new CreateProductFromPhotoTool($mockVision);

        // 1. Étape proposition à partir de l'image
        $proposal = $tool->execute($this->shop, [
            'image_data' => 'data:image/jpeg;base64,' . base64_encode('fake-image-bytes'),
            'merchant_hint' => 'Modèle haut de gamme collection 2026',
            'stock' => 15,
        ], $this->user);

        $this->assertEquals('action_required', $proposal['status']);
        $this->assertEquals('create_product_from_photo', $proposal['action_type']);
        $this->assertNotEmpty($proposal['action_token']);
        $this->assertEquals('Robe Africaine Wax Royale', $proposal['product_payload']['title']);
        $this->assertEquals(18500, $proposal['product_payload']['price']);

        // 2. Étape confirmation avec jeton HMAC
        $confirmArgs = array_merge($proposal['product_payload'], [
            'confirmed_by_user' => true,
            'action_token' => $proposal['action_token'],
        ]);

        $execResult = $tool->execute($this->shop, $confirmArgs, $this->user);
        $this->assertEquals('success', $execResult['status']);

        // 3. Vérifier en base de données
        $product = Product::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('title', 'Robe Africaine Wax Royale')
            ->first();

        $this->assertNotNull($product);
        $this->assertEquals(18500, $product->price);
        $this->assertEquals(15, $product->stock);

        // Vérifier l'audit StockMovement
        $movement = StockMovement::where('product_id', $product->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(15, $movement->quantity_change);
        $this->assertEquals('initial_creation', $movement->reason);

        // 4. Test tentative de falsification de sécurité
        $tamperedArgs = array_merge($proposal['product_payload'], [
            'price' => 1000, // tentative de piratage du prix
            'confirmed_by_user' => true,
            'action_token' => $proposal['action_token'],
        ]);

        $tamperResult = $tool->execute($this->shop, $tamperedArgs, $this->user);
        $this->assertEquals('security_rejection', $tamperResult['status']);
    }

    public function test_generate_video_ad_script_creates_campaign_for_tiktok(): void
    {
        // Créer un produit test pour la boutique
        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'title' => 'Sandales Royales en Cuir',
            'slug' => 'sandales-royales-cuir',
            'price' => 12500,
            'compare_at_price' => 16000,
            'stock' => 20,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $tool = new GenerateVideoAdScriptTool();

        $result = $tool->execute($this->shop, [
            'platform' => 'tiktok',
            'angle' => 'promotions',
            'tone' => 'dynamique',
            'custom_message' => 'Livraison rapide partout à Lomé !',
        ], $this->user);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('tiktok', $result['platform']);
        $this->assertNotEmpty($result['voiceover_script']);
        $this->assertNotEmpty($result['products_featured']);
        $this->assertStringContainsString('Sandales Royales en Cuir', json_encode($result['products_featured']));
    }

    public function test_generate_video_ad_script_multi_tenant_isolation(): void
    {
        // Créer une autre boutique avec son propre produit
        $otherUser = User::factory()->create(['email' => 'other_artist@zanika.shop']);
        $otherShop = Shop::create([
            'user_id' => $otherUser->id,
            'name' => 'Autre Enseigne',
            'slug' => 'autre-enseigne',
            'subdomain' => 'autre-enseigne',
            'currency' => 'FCFA',
            'is_active' => true,
        ]);

        $foreignProduct = Product::withoutGlobalScopes()->create([
            'shop_id' => $otherShop->id,
            'title' => 'Produit Externe Secret',
            'slug' => 'produit-externe-secret',
            'price' => 50000,
            'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $tool = new GenerateVideoAdScriptTool();

        // Tenter de cibler le produit d'une autre boutique
        $result = $tool->execute($this->shop, [
            'platform' => 'tiktok',
            'angle' => 'coup_de_coeur',
            'product_id' => $foreignProduct->id,
        ], $this->user);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('n\'appartient pas à cette boutique', $result['message']);
    }

    public function test_generate_video_ad_script_blocks_tiktok_on_essentiel_plan_but_allows_whatsapp(): void
    {
        $essentielPlan = Plan::firstOrCreate(
            ['slug' => 'essentiel'],
            [
                'name' => 'Essentiel',
                'monthly_price' => 10000,
                'max_products' => 50,
                'storage_mb' => 2048,
                'ai_credits' => 80,
                'full_generations' => 1,
                'active' => true,
            ]
        );

        $this->shop->subscription()->update(['plan_id' => $essentielPlan->id]);
        $this->shop->unsetRelation('subscription');

        $tool = new GenerateVideoAdScriptTool();

        $blockedTikTok = $tool->execute($this->shop, [
            'platform' => 'tiktok',
            'angle' => 'nouveautes',
        ], $this->user);

        $this->assertEquals('feature_restricted', $blockedTikTok['status']);
        $this->assertStringContainsString('Pro et Premium', $blockedTikTok['error']);

        $allowedWhatsapp = $tool->execute($this->shop, [
            'platform' => 'whatsapp',
            'angle' => 'boutique',
        ], $this->user);

        $this->assertEquals('success', $allowedWhatsapp['status']);
    }
}
