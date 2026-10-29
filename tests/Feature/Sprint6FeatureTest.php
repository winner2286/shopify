<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint6FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMerchant(string $email = 'marchand.sprint6@test.com'): User
    {
        return User::create([
            'name' => 'Marchand Sprint 6',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => User::ROLE_MERCHANT,
            'country' => 'TG',
            'phone' => '+228 90 99 88 77',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);
    }

    private function createShop(User $user, string $subdomain = 'sprint6-shop'): Shop
    {
        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Sprint6 Shop',
            'subdomain' => $subdomain,
            'status' => Shop::PUBLISHED,
            'business_category' => 'mode',
            'brand_color' => '#6d3df2',
            'contact' => [
                'phone' => '+228 90 99 88 77',
                'whatsapp' => '+228 90 99 88 77',
                'email' => 'contact@sprint6.tg',
                'city' => 'Lomé',
            ],
        ]);

        $shop->ensureDefaultShippingZones();

        return $shop;
    }

    private function createProduct(Shop $shop, string $name = 'Robe Wax Royale', int $price = 18500): Product
    {
        $cat = Category::create([
            'shop_id' => $shop->id,
            'name' => 'Robes',
            'slug' => 'robes',
        ]);

        return Product::create([
            'shop_id' => $shop->id,
            'title' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => 'Superbe robe wax cousue à la main.',
            'price' => $price,
            'stock' => 20,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    public function test_merchant_can_access_advertising_pages(): void
    {
        $merchant = $this->createMerchant();
        $shop = $this->createShop($merchant);
        $product = $this->createProduct($shop);

        $response = $this->actingAs($merchant)->get(route('ads.index'));
        $response->assertStatus(200);
        $response->assertSee('Bibliothèque de Publicités');

        $responseCreate = $this->actingAs($merchant)->get(route('ads.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Assistant Publicitaire IA');
        $responseCreate->assertSee('Robe Wax Royale');
    }

    public function test_ai_advertising_service_generates_copy_and_video_scripts_and_logs_audit(): void
    {
        $merchant = $this->createMerchant();
        $shop = $this->createShop($merchant);
        $product = $this->createProduct($shop, 'Costume Kente Prestige', 45000);

        $response = $this->actingAs($merchant)->post(route('ads.store'), [
            'product_id' => $product->id,
            'channel' => Advertisement::CHANNEL_WHATSAPP,
            'objective' => Advertisement::OBJECTIVE_NOUVEAUTE,
            'tone' => Advertisement::TONE_PREMIUM,
        ]);

        $this->assertDatabaseHas('advertisements', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'objective' => Advertisement::OBJECTIVE_NOUVEAUTE,
        ]);

        $ad = Advertisement::where('product_id', $product->id)->first();
        $this->assertNotNull($ad);
        $response->assertRedirect(route('ads.show', $ad));

        // CDC Module H validation: 3 copy variations (H-02) & 2 video scripts (H-04)
        $this->assertCount(3, $ad->generated_texts);
        $this->assertCount(2, $ad->generated_scripts);
        $this->assertNotNull($ad->visual_config);

        // Verification of strict AI pricing rule: 45 000 FCFA must be present, no hallucinated price
        $jsonCopies = json_encode($ad->generated_texts);
        $this->assertStringContainsString('45 000 FCFA', $jsonCopies);
        $this->assertStringContainsString('Costume Kente Prestige', $jsonCopies);

        // Module J-04: AI token audit log
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ad_text',
            'status' => 'success',
        ]);

        // Detail view display & Studio Vidéo IA verification
        $showResponse = $this->actingAs($merchant)->get(route('ads.show', $ad));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Studio Vidéo & Publicité IA');
        $showResponse->assertSee('ad-video-canvas');
        $showResponse->assertSee('Idées & Scripts de Vidéos TikTok', false);
        $showResponse->assertSee('45 000 FCFA');

        // Test logging video export
        $exportResponse = $this->actingAs($merchant)->postJson(route('ads.log_video_export', $ad), [
            'theme' => 'boutique',
            'script_index' => 0,
            'duration_seconds' => 14,
        ]);
        $exportResponse->assertStatus(200);
        $exportResponse->assertJson(['success' => true]);
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'ad_video_export',
            'step' => 'video_rendering',
        ]);
    }

    public function test_merchant_cannot_generate_ad_for_other_shop_product(): void
    {
        $merchantA = $this->createMerchant('merchant.a@test.com');
        $shopA = $this->createShop($merchantA, 'shop-a');

        $merchantB = $this->createMerchant('merchant.b@test.com');
        $shopB = $this->createShop($merchantB, 'shop-b');
        $productB = $this->createProduct($shopB, 'Produit B', 12000);

        // Merchant A tries to generate ad for Product B (RG-02 tenant isolation)
        $response = $this->actingAs($merchantA)->post(route('ads.store'), [
            'product_id' => $productB->id,
            'channel' => Advertisement::CHANNEL_WHATSAPP,
            'objective' => Advertisement::OBJECTIVE_NOUVEAUTE,
            'tone' => Advertisement::TONE_AMICAL,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('advertisements', [
            'product_id' => $productB->id,
            'shop_id' => $shopA->id,
        ]);
    }

    public function test_analytics_dashboard_displays_correct_metrics_and_csv_export(): void
    {
        $merchant = $this->createMerchant();
        $shop = $this->createShop($merchant);
        $product = $this->createProduct($shop, 'Sac Cuir Sahel', 25000);

        // Create an order
        $customer = Customer::create([
            'shop_id' => $shop->id,
            'name' => 'Ablanvi Mensah',
            'phone' => '+228 91 22 33 44',
            'email' => 'ablanvi@test.com',
            'total_spent' => 25000,
            'orders_count' => 1,
        ]);

        Order::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-TEST-001',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_method' => 'tmoney',
            'subtotal' => 25000,
            'shipping_cost' => 1500,
            'total' => 26500,
            'customer_name' => 'Ablanvi Mensah',
            'customer_phone' => '+228 91 22 33 44',
            'customer_email' => 'ablanvi@test.com',
            'delivery_address' => 'Adidogomé, Lomé',
            'delivery_zone_name' => 'Lomé',
        ]);

        $response = $this->actingAs($merchant)->get(route('analytics.index'));
        $response->assertStatus(200);
        $response->assertSee('Statistiques & Performance', false);
        $response->assertSee('26 500 FCFA'); // Total GMV
        $response->assertSee('Acheteurs Distincts');

        // CSV export
        $csvResponse = $this->actingAs($merchant)->get(route('analytics.export_csv'));
        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));
        $this->assertStringContainsString('ORD-TEST-001', $csvResponse->streamedContent());
        $this->assertStringContainsString('26500', $csvResponse->streamedContent());
        $this->assertStringContainsString('Ablanvi Mensah', $csvResponse->streamedContent());
    }

    public function test_non_admin_cannot_access_super_admin_routes(): void
    {
        $merchant = $this->createMerchant();
        $this->createShop($merchant);

        $this->actingAs($merchant)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($merchant)->get(route('admin.shops'))->assertStatus(403);
        $this->actingAs($merchant)->get(route('admin.ai_usage'))->assertStatus(403);
        $this->actingAs($merchant)->get(route('admin.plans'))->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_when_accessing_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_dashboard_and_manage_shops_and_suspension(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        if (! $admin) {
            $admin = User::create([
                'name' => 'Super Administrateur',
                'email' => 'admin.test@zanika.tg',
                'password' => bcrypt('admin123456'),
                'role' => User::ROLE_ADMIN,
                'country' => 'TG',
                'phone' => '+228 90 00 00 00',
            ]);
        }

        $merchant = $this->createMerchant();
        $shop = $this->createShop($merchant, 'boutique-a-suspendre');

        // Super Admin dashboard
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Super-Administration Zanika');

        // Super Admin shops list
        $shopsResponse = $this->actingAs($admin)->get(route('admin.shops'));
        $shopsResponse->assertStatus(200);
        $shopsResponse->assertSee('boutique-a-suspendre');

        // Storefront initially works
        $storefront = $this->get('/b/' . $shop->subdomain);
        $storefront->assertStatus(200);

        // Admin suspends shop (J-02)
        $suspendResponse = $this->actingAs($admin)->post(route('admin.shops.toggle_suspend', $shop));
        $suspendResponse->assertRedirect();

        $shop->refresh();
        $this->assertEquals(Shop::SUSPENDED, $shop->status);

        // Suspended storefront is blocked
        $blockedStorefront = $this->get('/b/' . $shop->subdomain);
        $blockedStorefront->assertStatus(403);

        // Admin re-activates shop
        $reactivateResponse = $this->actingAs($admin)->post(route('admin.shops.toggle_suspend', $shop));
        $reactivateResponse->assertRedirect();

        $shop->refresh();
        $this->assertEquals(Shop::PUBLISHED, $shop->status);

        // Storefront is active again
        $reactivatedStorefront = $this->get('/b/' . $shop->subdomain);
        $reactivatedStorefront->assertStatus(200);
    }

    public function test_super_admin_can_view_ai_usage_and_plans(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        if (! $admin) {
            $admin = User::create([
                'name' => 'Super Administrateur',
                'email' => 'admin.test2@zanika.tg',
                'password' => bcrypt('admin123456'),
                'role' => User::ROLE_ADMIN,
                'country' => 'TG',
                'phone' => '+228 90 00 00 00',
            ]);
        }

        // AI usage log
        $aiResponse = $this->actingAs($admin)->get(route('admin.ai_usage'));
        $aiResponse->assertStatus(200);
        $aiResponse->assertSee('Journal & Coûts IA', false);

        // Plans listing (CDC J-05 : Essentiel, Pro, Premium)
        $plansResponse = $this->actingAs($admin)->get(route('admin.plans'));
        $plansResponse->assertStatus(200);
        $plansResponse->assertSee("Formules d'Abonnement", false);
        $plansResponse->assertSee('Essentiel');
        $plansResponse->assertSee('Pro');
        $plansResponse->assertSee('Premium');
    }
}
