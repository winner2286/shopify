<?php

namespace Tests\Audit;

use App\Http\Controllers\FeexPayController;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\ToolRegistry;
use App\Services\AiGenerationService;
use App\Services\CartService;
use App\Services\FeexPayService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Audit probes: passing assertions document existing defects, not secure behavior. */
class AuditVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake();
        Storage::fake('public');
        config(['services.groq.key' => '', 'services.openrouter.key' => '',
            'services.photoroom.key' => '', 'services.whatsapp.token' => '',
            'services.webpush.public_key' => '', 'services.webpush.private_key' => '']);
    }

    private function fixture(string $status = Shop::PUBLISHED): array
    {
        $user = User::factory()->create(['role' => 'merchant']);
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Audit boutique',
            'subdomain' => 'audit-boutique', 'status' => $status, 'currency' => 'MAD',
            'contact' => ['email' => 'audit@example.test'], 'brand_color' => '#123456']);
        $product = Product::withoutGlobalScopes()->create(['shop_id' => $shop->id,
            'title' => 'Audit product', 'slug' => 'audit-product', 'price' => 100,
            'stock' => 10, 'track_stock' => true, 'status' => 'active']);
        $shop->ensureDefaultShippingZones();

        return [$user, $shop, $product];
    }

    private function orderData(Shop $shop): array
    {
        return ['customer_name' => 'Audit customer', 'customer_phone' => '+212600000001',
            'shipping_zone_id' => $shop->shippingZones()->first()->id,
            'delivery_address' => 'Audit address', 'payment_method' => 'cash_on_delivery'];
    }

    public function test_ai_registry_mutates_price_without_signed_token(): void
    {
        [$user, $shop, $product] = $this->fixture();
        $result = app(ToolRegistry::class)->execute('update_product_stock_or_price', $shop,
            ['product_id' => $product->id, 'new_price' => 1, 'confirmed_by_user' => true], $user);
        $this->assertSame('success', $result['status']);
        $this->assertEquals(1, $product->fresh()->price);
    }

    public function test_suspended_merchant_can_republish_shop(): void
    {
        [$user, $shop] = $this->fixture(Shop::SUSPENDED);
        $this->actingAs($user)->post(route('shop.publish'))->assertRedirect();
        $this->assertSame(Shop::PUBLISHED, $shop->fresh()->status);
    }

    public function test_expired_subscription_keeps_premium_rights(): void
    {
        [$user, $shop] = $this->fixture();
        $plan = Plan::create(['name' => 'Audit premium', 'slug' => 'audit-premium',
            'monthly_price' => 30000, 'max_products' => 1000, 'ai_credits' => 500,
            'storage_mb' => 1024, 'full_generations' => 10, 'active' => true, 'max_shops' => 5]);
        Subscription::create(['shop_id' => $shop->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);
        $this->assertSame($plan->id, $shop->fresh()->currentPlan()->id);
        $this->assertSame($plan->id, $user->currentPlan()->id);
    }

    public function test_guest_can_checkout_paused_shop(): void
    {
        [, $shop, $product] = $this->fixture(Shop::PAUSED);
        $cart = [$product->id => ['product_id' => $product->id, 'title' => $product->title,
            'price' => 100, 'quantity' => 1]];
        $this->withSession(['cart_'.$shop->id => $cart])
            ->post(route('storefront.checkout.process', $shop->subdomain), $this->orderData($shop))
            ->assertRedirect();
        $this->assertSame(1, Order::withoutGlobalScopes()->where('shop_id', $shop->id)->count());
    }

    public function test_checkout_charges_stale_cart_price_and_xof_for_mad_shop(): void
    {
        [, $shop, $product] = $this->fixture();
        app(CartService::class)->add($shop, $product->id, 1);
        $product->update(['price' => 900]);
        $order = app(OrderService::class)->createFromCart($shop, $this->orderData($shop));
        $this->assertEquals(100, $order->items()->first()->unit_price);
        $this->assertSame('XOF', $order->payments()->first()->currency);
    }

    public function test_two_stale_order_instances_restore_stock_twice(): void
    {
        [, $shop, $product] = $this->fixture();
        app(CartService::class)->add($shop, $product->id, 1);
        $order = app(OrderService::class)->createFromCart($shop, $this->orderData($shop));
        $staleCopy = Order::withoutGlobalScopes()->findOrFail($order->id);
        app(OrderService::class)->cancelOrder($order, 'audit first');
        app(OrderService::class)->cancelOrder($staleCopy, 'audit second');
        $this->assertEquals(11, $product->fresh()->stock);
    }

    public function test_cancelled_order_can_be_marked_delivered_via_merchant_route(): void
    {
        [$user, $shop, $product] = $this->fixture();
        app(CartService::class)->add($shop, $product->id, 1);
        $order = app(OrderService::class)->createFromCart($shop, $this->orderData($shop));
        app(OrderService::class)->cancelOrder($order, 'audit');
        $this->actingAs($user)->post(route('orders.update_status', $order), ['status' => 'delivered'])
            ->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_payment_verification_accepts_missing_target_and_currency(): void
    {
        $this->mock(FeexPayService::class, function ($mock) {
            $mock->shouldReceive('checkTransactionStatus')->once()
                ->with('audit-reference')->andReturn(['status' => 'SUCCESSFUL', 'amount' => 1000]);
        });
        $method = new \ReflectionMethod(FeexPayController::class, 'verifyApprovedTransaction');
        $result = $method->invoke(app(FeexPayController::class), 'audit-reference', 'SUB:999:999', 1000);
        $this->assertNotNull($result);
    }

    public function test_subscription_reference_can_be_replayed_after_cache_loss(): void
    {
        [, $shop] = $this->fixture();
        $plan = Plan::create(['name' => 'Audit', 'slug' => 'audit', 'monthly_price' => 1000,
            'max_products' => 50, 'storage_mb' => 100, 'ai_credits' => 10,
            'full_generations' => 1, 'active' => true]);
        $subscription = Subscription::create(['shop_id' => $shop->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $service = app(FeexPayService::class);
        $service->handleSubscriptionPaymentSuccess($subscription, 'audit-paid-reference', $plan);
        $firstEnd = $subscription->fresh()->ends_at;
        Cache::forget('feexpay_sub_tx:audit-paid-reference');
        $service->handleSubscriptionPaymentSuccess($subscription->fresh(), 'audit-paid-reference', $plan);
        $this->assertTrue($subscription->fresh()->ends_at->greaterThan($firstEnd));
    }

    public function test_repeated_onboarding_generation_duplicates_products(): void
    {
        [, $shop] = $this->fixture();
        $service = app(AiGenerationService::class);
        $data = ['description' => 'Boutique audit', 'business_category' => 'mode',
            'visual_style' => 'moderne', 'suggested_name' => 'Audit boutique'];
        $service->generateShop($shop, $data);
        $firstCount = $shop->products()->count();
        $service->generateShop($shop, $data);
        $this->assertSame($firstCount + 2, $shop->products()->count());
    }

    public function test_invalid_image_is_still_written_to_public_storage(): void
    {
        [$user, $shop] = $this->fixture();
        $file = UploadedFile::fake()->createWithContent('invalid.jpg', '<html>audit</html>');
        $this->actingAs($user)->postJson(route('ai.products.analyze_photo'), ['photo' => $file])
            ->assertStatus(422);
        $files = Storage::disk('public')->allFiles("shops/{$shop->id}/products");
        $this->assertCount(1, $files);
        $this->assertSame('<html>audit</html>', Storage::disk('public')->get($files[0]));
    }
}
