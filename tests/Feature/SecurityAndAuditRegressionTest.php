<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMerchantWithShop(string $email, string $subdomain, string $planSlug = 'essentiel'): array
    {
        $user = User::create([
            'name' => 'Marchand ' . $subdomain,
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => User::ROLE_MERCHANT,
            'country' => 'TG',
            'phone' => '+228 90 12 34 56',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique ' . $subdomain,
            'subdomain' => $subdomain,
            'status' => Shop::PUBLISHED,
            'business_category' => 'mode',
            'brand_color' => '#6d3df2',
            'contact' => [
                'phone' => '+228 90 12 34 56',
                'whatsapp' => '+228 90 12 34 56',
                'email' => $email,
                'city' => 'Lomé',
            ],
        ]);

        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $subscription = Subscription::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $shop->ensureDefaultShippingZones();

        return [$user, $shop, $subscription, $plan];
    }

    public function test_dangerous_dev_and_migration_routes_are_removed(): void
    {
        $this->get('/migrate-now')->assertStatus(404);
        $this->get('/dev-login-admin')->assertStatus(404);
    }

    public function test_action_token_service_fails_closed_without_app_key(): void
    {
        [$user, $shop] = $this->createMerchantWithShop('token@test.tg', 'shop-token');
        $service = app(ActionTokenService::class);
        $originalKey = config('app.key');

        try {
            $token = $service->generateToken($shop, $user, 'test_tool', ['a' => 1]);
            $this->assertNotEmpty($token);

            config(['app.key' => '']);

            $threwOnGenerate = false;
            try {
                $service->generateToken($shop, $user, 'test_tool', ['a' => 1]);
            } catch (\RuntimeException) {
                $threwOnGenerate = true;
            }
            $this->assertTrue($threwOnGenerate);

            $threwOnValidate = false;
            try {
                $service->validateToken($token, $shop, $user, 'test_tool', ['a' => 1]);
            } catch (\RuntimeException) {
                $threwOnValidate = true;
            }
            $this->assertTrue($threwOnValidate);
        } finally {
            config(['app.key' => $originalKey]);
        }
    }

    public function test_feexpay_webhook_verifies_remotely_and_blocks_tampering_and_replay(): void
    {
        config([
            'feexpay.token' => 'fake-feexpay-token',
            'feexpay.shop_id' => 'fake-shop-id',
        ]);

        [, $shop] = $this->createMerchantWithShop('feex@test.tg', 'shop-feex');
        $zone = $shop->shippingZones()->first();

        $order1 = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-20261112-0001',
            'customer_name' => 'Kossi Mensah',
            'customer_phone' => '+228 90 11 22 33',
            'shipping_zone_id' => $zone->id,
            'delivery_zone_name' => $zone->name,
            'delivery_address' => 'Lomé',
            'subtotal' => 15000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'total' => 15000,
            'payment_method' => Order::PAYMENT_FEEXPAY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        $order2 = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-20261112-0002',
            'customer_name' => 'Ama Koffi',
            'customer_phone' => '+228 90 44 55 66',
            'shipping_zone_id' => $zone->id,
            'delivery_zone_name' => $zone->name,
            'delivery_address' => 'Lomé',
            'subtotal' => 15000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'total' => 15000,
            'payment_method' => Order::PAYMENT_FEEXPAY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        Http::fake([
            '*REF-FAIL-001*' => Http::response([
                'status' => 'FAILED',
                'custom_id' => "ORDER:{$order1->id}",
                'amount' => 15000,
            ], 200),
            '*REF-LOW-AMT*' => Http::response([
                'status' => 'SUCCESSFUL',
                'custom_id' => "ORDER:{$order1->id}",
                'amount' => 500,
            ], 200),
            '*REF-MISMATCH*' => Http::response([
                'status' => 'SUCCESSFUL',
                'custom_id' => 'ORDER:999999',
                'amount' => 15000,
            ], 200),
            '*REF-VALID-001*' => Http::response([
                'status' => 'SUCCESSFUL',
                'custom_id' => "ORDER:{$order1->id}",
                'amount' => 15000,
                'phoneNumber' => '+228 90 11 22 33',
            ], 200),
        ]);

        // 1. Transaction non approuvée à distance
        $this->postJson(route('feexpay.webhook'), [
            'reference' => 'REF-FAIL-001',
            'custom_id' => "ORDER:{$order1->id}",
            'status' => 'SUCCESSFUL',
        ])->assertStatus(422);

        $this->assertSame(Order::PAYMENT_STATUS_PENDING, $order1->fresh()->payment_status);

        // 2. Montant inférieur manipulé
        $this->postJson(route('feexpay.webhook'), [
            'reference' => 'REF-LOW-AMT',
            'custom_id' => "ORDER:{$order1->id}",
            'status' => 'SUCCESSFUL',
        ])->assertStatus(422);

        // 3. custom_id différent sur l'API distante
        $this->postJson(route('feexpay.webhook'), [
            'reference' => 'REF-MISMATCH',
            'custom_id' => "ORDER:{$order1->id}",
            'status' => 'SUCCESSFUL',
        ])->assertStatus(422);

        // 4. Vérification valide -> commande 1 payée
        $this->postJson(route('feexpay.webhook'), [
            'reference' => 'REF-VALID-001',
            'custom_id' => "ORDER:{$order1->id}",
            'status' => 'SUCCESSFUL',
        ])->assertStatus(200);

        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order1->fresh()->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order1->id,
            'operator_reference' => 'REF-VALID-001',
            'status' => Payment::STATUS_PAID,
        ]);

        // 5. Rejeu de la même référence sur une autre commande -> bloqué
        $this->postJson(route('feexpay.webhook'), [
            'reference' => 'REF-VALID-001',
            'custom_id' => "ORDER:{$order2->id}",
            'status' => 'SUCCESSFUL',
        ])->assertStatus(422);

        $this->assertSame(Order::PAYMENT_STATUS_PENDING, $order2->fresh()->payment_status);
    }

    public function test_payment_simulation_blocks_production_live_mode_and_cross_tenant_idor(): void
    {
        [$merchantA, $shopA, $subA, $proPlan] = $this->createMerchantWithShop('sim-a@test.tg', 'sim-shop-a', 'pro');
        [$merchantB] = $this->createMerchantWithShop('sim-b@test.tg', 'sim-shop-b', 'pro');

        $zone = $shopA->shippingZones()->first();
        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'order_number' => 'CMD-20261112-SIM1',
            'customer_name' => 'Client Sim',
            'customer_phone' => '+228 90 99 88 77',
            'shipping_zone_id' => $zone->id,
            'delivery_zone_name' => $zone->name,
            'delivery_address' => 'Lomé',
            'subtotal' => 5000,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'total' => 5000,
            'payment_method' => Order::PAYMENT_TMONEY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        // Page de paiement abonnement fonctionne ($user->shop corrigé)
        $this->actingAs($merchantA)
            ->get(route('subscription.checkout', ['plan' => 'pro']))
            ->assertStatus(200);

        // IDOR abonnement : Merchant B ne peut pas simuler l'abonnement de Merchant A
        config(['feexpay.mode' => 'SANDBOX']);
        $this->actingAs($merchantB)
            ->post(route('subscription.simulate', [$subA, $proPlan]))
            ->assertStatus(403);

        // Mode LIVE : simulation FeexPay abonnement bloquée
        config(['feexpay.mode' => 'LIVE']);
        $this->actingAs($merchantA)
            ->post(route('subscription.simulate', [$subA, $proPlan]))
            ->assertStatus(403);

        // Environnement production : toutes simulations bloquées
        config(['feexpay.mode' => 'SANDBOX']);
        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->actingAs($merchantA)
                ->post(route('subscription.simulate', [$subA, $proPlan]))
                ->assertStatus(403);
            $this->post(route('storefront.order.simulate_mobile_money', [$shopA->subdomain, $order->order_number]))
                ->assertStatus(403);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }

    public function test_svg_xss_sanitized_and_dangerous_uploads_rejected(): void
    {
        Storage::fake('public');

        $placeholderResponse = $this->get('/api/placeholder-image?title=' . urlencode('<script>alert(1)</script>') . '&color=' . urlencode('red"onload="alert(2)') . '&cat=' . urlencode('<svg onload=x>'));
        $placeholderResponse->assertStatus(200);
        $placeholderResponse->assertHeader('Content-Type', 'image/svg+xml');
        $csp = (string) $placeholderResponse->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $body = $placeholderResponse->getContent();
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onload=', $body);

        [$merchant, $shop] = $this->createMerchantWithShop('upload@test.tg', 'shop-upload');

        // Rejet SVG sur upload logo
        $svgFile = UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml');
        $this->actingAs($merchant)
            ->post(route('onboarding.upload_logo'), ['custom_logo' => $svgFile])
            ->assertSessionHasErrors('custom_logo');

        $ad = Advertisement::create([
            'shop_id' => $shop->id,
            'channel' => 'tiktok',
            'objective' => 'promotion',
            'tone' => 'dynamique',
            'generated_texts' => ['A'],
        ]);

        // Rejet fichier PHP et HTML sur upload audio publicitaire
        $phpFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-httpd-php');
        $this->actingAs($merchant)
            ->postJson(route('ads.upload_custom_audio', $ad), [
                'audio' => $phpFile,
                'type' => 'voice',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');

        $htmlFile = UploadedFile::fake()->create('xss.html', 10, 'text/html');
        $this->actingAs($merchant)
            ->postJson(route('ads.upload_custom_audio', $ad), [
                'audio' => $htmlFile,
                'type' => 'voice',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');
    }

    public function test_shipping_zone_resource_route_parameter_binding_allows_update_and_delete(): void
    {
        [$merchantA, $shopA] = $this->createMerchantWithShop('ship-a@test.tg', 'shop-ship-a');
        [$merchantB] = $this->createMerchantWithShop('ship-b@test.tg', 'shop-ship-b');

        $zone = ShippingZone::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'name' => 'Zone Test',
            'price' => 1500,
            'delivery_estimate' => '24h',
            'is_pickup' => false,
            'is_active' => true,
            'display_order' => 10,
        ]);

        // Cross-shop bloqué (404 via BelongsToShopScope ou 403 contrôleur)
        $crossPut = $this->actingAs($merchantB)->put(route('shipping.update', $zone), [
            'name' => 'Pirate',
            'price' => 0,
            'delivery_estimate' => '1h',
        ]);
        $this->assertContains($crossPut->status(), [403, 404]);

        // Propriétaire autorisé (bug binding {livraison} vs $shippingZone corrigé)
        $this->actingAs($merchantA)
            ->put(route('shipping.update', $zone), [
                'name' => 'Zone Mise à Jour',
                'price' => 2000,
                'delivery_estimate' => '12h',
                'is_active' => true,
            ])
            ->assertRedirect(route('shipping.index'));

        $this->assertDatabaseHas('shipping_zones', [
            'id' => $zone->id,
            'name' => 'Zone Mise à Jour',
            'price' => 2000,
        ]);

        $this->actingAs($merchantA)
            ->delete(route('shipping.destroy', $zone))
            ->assertRedirect(route('shipping.index'));

        $this->assertDatabaseMissing('shipping_zones', ['id' => $zone->id]);
    }

    public function test_public_receipt_requires_valid_hmac_token_or_owner_auth(): void
    {
        [$merchantA, $shopA] = $this->createMerchantWithShop('rec-a@test.tg', 'shop-rec-a');
        [$merchantB] = $this->createMerchantWithShop('rec-b@test.tg', 'shop-rec-b');
        $zone = $shopA->shippingZones()->first();

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'order_number' => 'CMD-20261112-REC1',
            'customer_name' => 'Client Reçu',
            'customer_phone' => '+228 90 12 34 56',
            'shipping_zone_id' => $zone->id,
            'delivery_zone_name' => $zone->name,
            'delivery_address' => 'Lomé',
            'subtotal' => 9000,
            'discount_amount' => 0,
            'shipping_cost' => 1000,
            'total' => 10000,
            'payment_method' => Order::PAYMENT_CASH,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        // Invité sans token ou mauvais token -> 403
        $this->get("/recu/{$order->order_number}")->assertStatus(403);
        $this->get("/recu/{$order->order_number}?token=invalidtoken")->assertStatus(403);

        // Autre commerçant -> 403
        $this->actingAs($merchantB)->get("/recu/{$order->order_number}")->assertStatus(403);

        // Invité avec URL signée HMAC -> 200
        $this->app['auth']->forgetGuards();
        $this->get($order->publicReceiptUrl())->assertStatus(200);

        // Commerçant propriétaire sans token -> 200
        $this->actingAs($merchantA)->get("/recu/{$order->order_number}")->assertStatus(200);
    }

    public function test_multi_tenant_isolation_on_products_categories_and_media_serve(): void
    {
        [$merchantA, $shopA] = $this->createMerchantWithShop('iso-a@test.tg', 'shop-iso-a');
        [$merchantB, $shopB] = $this->createMerchantWithShop('iso-b@test.tg', 'shop-iso-b');

        $catA = Category::withoutGlobalScopes()->create(['shop_id' => $shopA->id, 'name' => 'Mode A', 'slug' => 'mode-a', 'display_order' => 1]);
        $catB = Category::withoutGlobalScopes()->create(['shop_id' => $shopB->id, 'name' => 'Tech B', 'slug' => 'tech-b', 'display_order' => 1]);

        $productA = Product::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'title' => 'Tissu Wax A',
            'slug' => 'tissu-wax-a',
            'price' => 8000,
            'stock' => 5,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // Isolement catégories (404 via BelongsToShopScope ou 403 contrôleur)
        $this->assertContains($this->actingAs($merchantB)->put(route('categories.update', $catA), ['name' => 'Pirate'])->status(), [403, 404]);
        $this->assertContains($this->actingAs($merchantB)->delete(route('categories.destroy', $catA))->status(), [403, 404]);

        // Isolement produits (edit, update, destroy)
        $this->assertContains($this->actingAs($merchantB)->get(route('products.edit', $productA))->status(), [403, 404]);
        $this->assertContains($this->actingAs($merchantB)->put(route('products.update', $productA), ['title' => 'Hack', 'price' => 100])->status(), [403, 404]);
        $this->assertContains($this->actingAs($merchantB)->delete(route('products.destroy', $productA))->status(), [403, 404]);

        // Validation category_ids inter-boutiques sur store
        $this->actingAs($merchantA)
            ->post(route('products.store'), [
                'title' => 'Produit Croisé',
                'price' => 5000,
                'category_ids' => [$catB->id],
            ])
            ->assertSessionHasErrors('category_ids.0');

        Storage::fake('public');

        // Path traversal et fuite inter-boutiques sur MediaController::serve (/storage/{path})
        $this->get('/storage/' . rawurlencode('..\\secret.txt'))->assertStatus(403);
        $this->get('/storage/' . rawurlencode('shops/1/..\\../.env'))->assertStatus(403);

        ProductImage::create([
            'product_id' => $productA->id,
            'path' => 'shops/1/unique-secret-photo.png',
            'sort_order' => 0,
            'size_bytes' => 14,
            'image_data' => base64_encode('fake-png-bytes'),
        ]);

        $servedShop1 = $this->get('/storage/shops/1/unique-secret-photo.png')->assertStatus(200);
        $shop1Bytes = $servedShop1->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($servedShop1->baseResponse->getFile()->getPathname())
            : $servedShop1->getContent();
        $this->assertSame('fake-png-bytes', $shop1Bytes);

        $servedShop999 = $this->get('/storage/shops/999/unique-secret-photo.png')->assertStatus(200);
        $shop999Bytes = $servedShop999->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($servedShop999->baseResponse->getFile()->getPathname())
            : $servedShop999->getContent();
        $this->assertNotSame('fake-png-bytes', $shop999Bytes);
    }

    public function test_ai_credits_quota_admin_recharge_and_ad_video_export_exclusion(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.tg',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        [$merchant, $shop] = $this->createMerchantWithShop('aiq@test.tg', 'shop-aiq', 'essentiel');

        // Essentiel possède 80 crédits mensuels : forcer l'épuisement exact
        $shop->update(['ai_credits_used' => 80]);
        $this->assertSame(0, $shop->fresh()->aiCreditsRemaining());

        // Quota épuisé -> HTTP 402 sur relation client et contrôle studio
        $this->actingAs($merchant)
            ->postJson(route('ai.customer.answer'), ['question' => 'Quel est le prix ?'])
            ->assertStatus(402);

        $this->actingAs($merchant)
            ->postJson(route('ai.products.verify_quality'), ['category_hint' => 'Chaussures'])
            ->assertStatus(402);

        // Recharge Admin +100 crédits bonus
        $this->actingAs($admin)
            ->post(route('admin.ai.recharge', $shop), [
                'action_type' => 'add',
                'bonus_credits' => 100,
            ])
            ->assertRedirect();

        $shop->refresh();
        $this->assertSame(-20, (int) $shop->ai_credits_used);
        $this->assertSame(100, $shop->aiCreditsRemaining());
        $this->assertSame(0, $shop->aiCreditsUsedThisMonth());

        // Remise à zéro admin
        $this->actingAs($admin)
            ->post(route('admin.ai.recharge', $shop), [
                'action_type' => 'reset_used',
                'bonus_credits' => 1,
            ])
            ->assertRedirect();

        $shop->refresh();
        $this->assertSame(0, (int) $shop->ai_credits_used);
        $this->assertSame(80, $shop->aiCreditsRemaining());

        // Export vidéo publicitaire n'entame pas le solde de crédits IA
        AiGeneration::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'user_id' => $merchant->id,
            'type' => 'ad_video_export',
            'model' => 'client-canvas',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'credits_cost' => 0,
        ]);

        $this->assertSame(80, $shop->fresh()->aiCreditsRemaining());
    }

    public function test_promo_code_lifecycle_checkout_redemption_and_stock_validation(): void
    {
        [$essentielMerchant, $essentielShop] = $this->createMerchantWithShop('promo-ess@test.tg', 'shop-promo-ess', 'essentiel');
        [$proMerchant, $proShop] = $this->createMerchantWithShop('promo-pro@test.tg', 'shop-promo-pro', 'pro');
        [$otherMerchant] = $this->createMerchantWithShop('promo-other@test.tg', 'shop-promo-other', 'pro');

        // Essentiel bloqué à la création
        $this->actingAs($essentielMerchant)
            ->post(route('discounts.store'), [
                'code' => 'NOEL10',
                'type' => 'percentage',
                'value' => 10,
            ])
            ->assertRedirect(route('discounts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('discount_codes', [
            'shop_id' => $essentielShop->id,
            'code' => 'NOEL10',
        ]);

        // Pro autorisé
        $this->actingAs($proMerchant)
            ->post(route('discounts.store'), [
                'code' => 'zanika20',
                'type' => 'percentage',
                'value' => 20,
                'max_uses' => 1,
            ])
            ->assertRedirect(route('discounts.index'))
            ->assertSessionHas('status');

        $discount = DiscountCode::withoutGlobalScopes()
            ->where('shop_id', $proShop->id)
            ->where('code', 'ZANIKA20')
            ->firstOrFail();

        // Cross-tenant IDOR bloqué sur toggle et destroy (404 via BelongsToShopScope ou 403 contrôleur)
        $this->assertContains($this->actingAs($otherMerchant)->post(route('discounts.toggle', $discount))->status(), [403, 404]);
        $this->assertContains($this->actingAs($otherMerchant)->delete(route('discounts.destroy', $discount))->status(), [403, 404]);

        // Ajout au panier et application du code promo au checkout
        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $proShop->id,
            'title' => 'Sac Cuir Premium',
            'slug' => 'sac-cuir-premium',
            'price' => 10000,
            'stock' => 5,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $pickupZone = ShippingZone::withoutGlobalScopes()->create([
            'shop_id' => $proShop->id,
            'name' => 'Retrait Boutique',
            'price' => 0,
            'delivery_estimate' => 'Immédiat',
            'is_pickup' => true,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->post("/b/{$proShop->subdomain}/panier/ajouter", [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->post(route('storefront.checkout.apply_promo', $proShop->subdomain), [
            'promo_code' => 'ZANIKA20',
        ])->assertSessionHas('promo_status');

        $this->get(route('storefront.checkout.show', $proShop->subdomain))
            ->assertStatus(200)
            ->assertSee('ZANIKA20')
            ->assertSee('-2 000 FCFA')
            ->assertSee('8 000 FCFA');

        $checkoutResp = $this->post(route('storefront.checkout.process', $proShop->subdomain), [
            'customer_name' => 'Koffi Mensah',
            'customer_phone' => '+228 90 10 20 30',
            'shipping_zone_id' => $pickupZone->id,
            'payment_method' => Order::PAYMENT_CASH,
            'promo_code' => 'ZANIKA20',
        ]);
        $checkoutResp->assertRedirect();

        $order = Order::withoutGlobalScopes()->where('shop_id', $proShop->id)->latest('id')->firstOrFail();
        $this->assertSame(10000, $order->subtotal);
        $this->assertSame('ZANIKA20', $order->discount_code);
        $this->assertSame(2000, $order->discount_amount);
        $this->assertSame(8000, $order->total);
        $this->assertSame(1, $discount->fresh()->used_count);
        $this->assertSame(4, $product->fresh()->stock);

        // Code promo épuisé (max_uses = 1) rejeté lors d'une nouvelle commande
        $this->post("/b/{$proShop->subdomain}/panier/ajouter", [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->post(route('storefront.checkout.process', $proShop->subdomain), [
            'customer_name' => 'Second Client',
            'customer_phone' => '+228 90 10 20 31',
            'shipping_zone_id' => $pickupZone->id,
            'payment_method' => Order::PAYMENT_CASH,
            'promo_code' => 'ZANIKA20',
        ])->assertSessionHas('error');

        $this->assertSame(1, Order::withoutGlobalScopes()->where('shop_id', $proShop->id)->count());

        // Commande bloquée si stock insuffisant au moment de la validation transactionnelle
        $this->post("/b/{$proShop->subdomain}/panier/vider");
        $this->post("/b/{$proShop->subdomain}/panier/ajouter", [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->post(route('storefront.checkout.process', $proShop->subdomain), [
            'customer_name' => 'Client Gourmand',
            'customer_phone' => '+228 90 10 20 32',
            'shipping_zone_id' => $pickupZone->id,
            'payment_method' => Order::PAYMENT_CASH,
        ])->assertSessionHas('error');

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, Order::withoutGlobalScopes()->where('shop_id', $proShop->id)->count());
    }

    public function test_feexpay_renewal_resets_monthly_ai_credits_used(): void
    {
        [$merchant, $shop, $sub, $proPlan] = $this->createMerchantWithShop('renew-credit@test.tg', 'renew-shop', 'pro');

        $shop->update(['ai_credits_used' => 250]);
        $this->assertSame(250, (int) $shop->fresh()->ai_credits_used);

        $feexPayService = app(\App\Services\FeexPayService::class);
        $feexPayService->handleSubscriptionPaymentSuccess($sub, 'TX-RENEW-CREDIT-01', $proPlan);

        $this->assertSame(0, (int) $shop->fresh()->ai_credits_used);
        $this->assertSame(350, $shop->fresh()->aiCreditsRemaining());
    }

    public function test_http_security_headers_and_geniuspay_webhook_protection(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');

        // Un webhook GeniusPay sans référence valide est rejeté
        $this->postJson(route('geniuspay.webhook'), [
            'reference' => '',
            'custom_id' => 'ORDER:999999',
        ])->assertStatus(400);
    }
}
