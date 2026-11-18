<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\ToolRegistry;
use App\Services\AiAdvertisingService;
use App\Services\AiGenerationService;
use App\Services\CartService;
use App\Services\FeexPayService;
use App\Services\ImageService;
use App\Services\OrderService;
use App\Services\PublicMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditSecurityFixTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'merchant']);
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Audit', 'subdomain' => 'audit', 'status' => 'published']);
        $product = $shop->products()->create(['title' => 'Article', 'slug' => 'article', 'price' => 100, 'stock' => 10, 'status' => 'active']);

        return [$user, $shop, $product];
    }

    private function createOrder(Shop $shop, string $status = 'pending', string $paymentStatus = 'pending', int $total = 100): Order
    {
        return $shop->orders()->create([
            'order_number' => 'AUDIT-'.bin2hex(random_bytes(8)), 'customer_name' => 'Audit customer',
            'customer_phone' => '+22890000001', 'delivery_zone_name' => 'Audit', 'delivery_address' => 'Audit address',
            'subtotal' => $total, 'shipping_cost' => 0, 'total' => $total, 'status' => $status,
            'payment_status' => $paymentStatus, 'payment_method' => 'feexpay', 'currency' => 'XOF',
        ]);
    }

    public function test_model_cannot_supply_its_own_confirmation(): void
    {
        [$user, $shop, $product] = $this->fixture();
        $registry = app(ToolRegistry::class);
        $proposal = $registry->execute('update_product_stock_or_price', $shop, ['product_id' => $product->id, 'new_price' => 1], $user);

        $result = $registry->executeFromModel('update_product_stock_or_price', $shop, ['product_id' => $product->id, 'new_price' => 1,
            'confirmed_by_user' => true, 'action_token' => $proposal['action_token']], $user);

        $this->assertSame('action_required', $result['status']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 100]);
    }

    public function test_direct_confirmation_requires_signed_token(): void
    {
        [$user, $shop, $product] = $this->fixture();

        $result = app(ToolRegistry::class)->execute('update_product_stock_or_price', $shop,
            ['product_id' => $product->id, 'new_price' => 1, 'confirmed_by_user' => true], $user);

        $this->assertSame('security_rejection', $result['status']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 100]);
    }

    public function test_approved_price_change_executes_once(): void
    {
        [$user, $shop, $product] = $this->fixture();
        $registry = app(ToolRegistry::class);
        $args = ['product_id' => $product->id, 'new_price' => 1];
        $proposal = $registry->execute('update_product_stock_or_price', $shop, $args, $user);
        $args += ['confirmed_by_user' => true, 'action_token' => $proposal['action_token']];

        $result = $registry->execute('update_product_stock_or_price', $shop, $args, $user);
        $replay = $registry->execute('update_product_stock_or_price', $shop, $args, $user);

        $this->assertSame('success', $result['status']);
        $this->assertSame('security_rejection', $replay['status']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 1]);
    }

    public function test_suspended_merchant_cannot_republish(): void
    {
        [$user, $shop] = $this->fixture();
        $shop->update(['status' => 'suspended']);

        $this->actingAs($user)->post(route('shop.publish'))->assertForbidden();

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'status' => 'suspended']);
    }

    public function test_guest_cannot_checkout_paused_shop(): void
    {
        [, $shop] = $this->fixture();
        $shop->update(['status' => 'paused']);

        $this->post(route('storefront.checkout.process', $shop->subdomain))->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_subscription_does_not_grant_premium_rights(): void
    {
        [$user, $shop] = $this->fixture();
        $plan = Plan::create(['slug' => 'audit-premium', 'name' => 'Premium', 'monthly_price' => 30000,
            'max_products' => 1000, 'storage_mb' => 1000, 'ai_credits' => 500, 'full_generations' => 3, 'active' => true]);
        Subscription::create(['shop_id' => $shop->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subMonths(2), 'ends_at' => now()->subDay()]);

        $this->assertSame('essentiel', $shop->currentPlan()->slug);
        $this->assertSame('essentiel', $user->currentPlan()->slug);
    }

    public function test_subscription_payment_cannot_be_replayed_after_cache_loss(): void
    {
        [, $shop] = $this->fixture();
        $plan = Plan::where('slug', 'essentiel')->firstOrFail();
        $subscription = Subscription::create(['shop_id' => $shop->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
        $service = app(FeexPayService::class);
        $service->handleSubscriptionPaymentSuccess($subscription, 'AUDIT-REPLAY', $plan);
        $end = $subscription->fresh()->ends_at->toDateTimeString();
        Cache::flush();

        $service->handleSubscriptionPaymentSuccess($subscription->fresh(), 'AUDIT-REPLAY', $plan);

        $this->assertSame($end, $subscription->fresh()->ends_at->toDateTimeString());
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_rejected_image_is_not_written_to_public_storage(): void
    {
        [$user, $shop] = $this->fixture();
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('invalid.jpg', '<html>audit</html>');

        $this->actingAs($user)->postJson(route('ai.products.analyze_photo'), ['photo' => $file])->assertUnprocessable();

        $this->assertSame([], Storage::disk('public')->allFiles('shops/'.$shop->id));
    }

    public function test_same_generation_request_is_idempotent(): void
    {
        [, $shop] = $this->fixture();
        $data = ['description' => 'Boutique audit', 'business_category' => 'mode', 'visual_style' => 'moderne'];
        $service = app(AiGenerationService::class);
        $first = $service->generateShop($shop, $data);

        $second = $service->generateShop($shop, $data);

        $this->assertSame(array_column($first['products'], 'id'), array_column($second['products'], 'id'));
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('ai_generations', 1);
    }

    public function test_storage_limit_rejects_write_without_creating_file(): void
    {
        [, $shop] = $this->fixture();
        Storage::fake('public');
        $shop->currentPlan()->update(['storage_mb' => 0]);

        try {
            app(PublicMediaStorage::class)->put('shops/'.$shop->id.'/products/oversized.jpg', 'image');
            $this->fail('Storage quota must reject this write.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('storage', $exception->errors());
        }

        Storage::disk('public')->assertMissing('shops/'.$shop->id.'/products/oversized.jpg');
    }

    public function test_quota_reservation_rejects_stale_balance_and_refunds_exception(): void
    {
        [, $shop] = $this->fixture();
        $shop->update(['ai_credits_used' => $shop->aiCreditsMonthly() - 1]);
        $stale = $shop->fresh();
        $shop->consumeAiCredits(1);

        try {
            $stale->consumeAiCredits(1);
            $this->fail('A stale balance must not allow overdrawing credits.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credits', $exception->errors());
        }
        $this->assertSame(0, $shop->fresh()->aiCreditsRemaining());

        $shop->update(['ai_credits_used' => 0]);
        try {
            $shop->withAiCredits(1, fn () => throw new \RuntimeException('provider failed'));
            $this->fail('Expected provider failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('provider failed', $exception->getMessage());
        }
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
    }

    #[DataProvider('invalidTransactionResponses')]
    public function test_payment_requires_exact_verified_target_amount_and_currency(array $override): void
    {
        [, $shop] = $this->fixture();
        config(['feexpay.shop_id' => 'audit-merchant']);
        $order = $this->createOrder($shop);
        $verified = array_replace(['status' => 'SUCCESSFUL', 'custom_id' => 'ORDER:'.$order->id,
            'amount' => 100, 'currency' => 'XOF', 'shop_id' => 'audit-merchant'], $override);
        $this->mock(FeexPayService::class, function ($mock) use ($verified) {
            $mock->shouldReceive('checkTransactionStatus')->once()->andReturn($verified);
        });

        $this->postJson(route('feexpay.webhook'), ['reference' => 'AUDIT-INVALID', 'custom_id' => 'ORDER:'.$order->id])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public static function invalidTransactionResponses(): array
    {
        return [
            'missing target' => [['custom_id' => null]],
            'wrong target' => [['custom_id' => 'ORDER:999999']],
            'missing currency' => [['currency' => null]],
            'wrong currency' => [['currency' => 'MAD']],
            'underpayment' => [['amount' => 99]],
            'overpayment' => [['amount' => 101]],
            'missing beneficiary' => [['shop_id' => null]],
            'wrong beneficiary' => [['shop_id' => 'another-merchant']],
        ];
    }

    public function test_second_cancellation_cannot_restore_stock_again(): void
    {
        [, $shop, $product] = $this->fixture();
        $product->update(['stock' => 9, 'track_stock' => true]);
        $order = $this->createOrder($shop);
        $order->items()->create(['product_id' => $product->id, 'product_title' => $product->title,
            'unit_price' => 100, 'quantity' => 1, 'total_price' => 100]);
        $staleOrder = $order->fresh();
        $service = app(OrderService::class);
        $service->cancelOrder($order, 'First request');

        try {
            $service->cancelOrder($staleOrder, 'Second request');
            $this->fail('A cancelled order must not be cancelled again.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('annul', $exception->getMessage());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 10]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_cancelled_order_cannot_be_marked_delivered(): void
    {
        [$user, $shop] = $this->fixture();
        $order = $this->createOrder($shop, 'cancelled');

        $this->actingAs($user)->post(route('orders.update_status', $order), ['status' => 'delivered'])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_analytics_revenue_and_average_use_paid_non_cancelled_orders(): void
    {
        [$user, $shop] = $this->fixture();
        $shop->subscriptions()->create(['plan_id' => Plan::where('slug', 'pro')->firstOrFail()->id, 'status' => 'active']);
        $this->createOrder($shop, 'pending', 'pending', 100);
        $this->createOrder($shop, 'cancelled', 'paid', 300);
        $this->createOrder($shop, 'delivered', 'paid', 200);

        $this->actingAs($user)->get(route('analytics.index'))->assertOk()
            ->assertViewHas('totalRevenue', 200)->assertViewHas('averageCart', 200);
    }

    public function test_failed_subscription_activation_rolls_back_payment_journal_and_dates(): void
    {
        [, $shop] = $this->fixture();
        $plan = Plan::where('slug', 'essentiel')->firstOrFail();
        $end = now()->addMonth()->startOfSecond();
        $subscription = Subscription::create(['shop_id' => $shop->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(), 'ends_at' => $end]);
        Log::shouldReceive('info')->once()->andThrow(new \RuntimeException('activation failed'));

        try {
            app(FeexPayService::class)->handleSubscriptionPaymentSuccess($subscription, 'AUDIT-ROLLBACK', $plan);
            $this->fail('Expected activation failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('activation failed', $exception->getMessage());
        }

        $this->assertSame($end->toDateTimeString(), $subscription->fresh()->ends_at->toDateTimeString());
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_changed_cart_price_is_rejected_without_creating_order(): void
    {
        [, $shop, $product] = $this->fixture();
        $shop->ensureDefaultShippingZones();
        app(CartService::class)->add($shop, $product->id);
        $product->update(['price' => 900]);

        $this->post(route('storefront.checkout.process', $shop->subdomain), [
            'customer_name' => 'Audit customer', 'customer_phone' => '+22890000001',
            'shipping_zone_id' => $shop->shippingZones()->first()->id, 'payment_method' => 'cash_on_delivery',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 10]);
    }

    public function test_uploaded_logo_and_full_resolution_product_can_be_restored(): void
    {
        [$user, $shop, $product] = $this->fixture();
        Storage::fake('public');
        $this->actingAs($user)->post(route('onboarding.upload_logo'), [
            'custom_logo' => UploadedFile::fake()->image('logo.png', 800, 600),
        ])->assertRedirect();
        $shop->refresh();
        Storage::disk('public')->delete($shop->logo_path);

        $this->get('/storage/'.$shop->logo_path)->assertOk();
        $processed = app(ImageService::class)->processAndStore(
            UploadedFile::fake()->image('product.png', 1000, 900), 'shops/'.$shop->id.'/products', 'original');
        $dimensions = getimagesizefromstring(base64_decode($processed['image_data']));
        $this->assertSame(1000, $dimensions[0]);
        $product->images()->create(['path' => $processed['path'], 'image_data' => $processed['image_data'], 'sort_order' => 0]);
        Storage::disk('public')->delete($processed['path']);
        $this->get('/storage/'.$processed['path'])->assertOk();
        $this->assertSame(base64_decode($processed['image_data']), Storage::disk('public')->get($processed['path']));
    }

    public function test_advertising_debits_credit_and_records_provider_usage(): void
    {
        [, $shop, $product] = $this->fixture();
        config(['services.groq.key' => 'fake-groq-key', 'services.groq.model' => 'audit-model',
            'services.openrouter.key' => '', 'services.gemini.key' => '']);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['scripts' => [
                ['title' => 'Audit', 'scenes' => [['title' => 'Produit', 'subtitle' => 'Collection', 'voiceover' => 'Découvrez notre collection.', 'duration' => 4, 'text' => 'Collection', 'visual' => 'product']]],
            ]])]]],
            'usage' => ['prompt_tokens' => 123, 'completion_tokens' => 45, 'cost' => 0.01],
        ])]);

        app(AiAdvertisingService::class)->generate($shop, ['channel' => 'whatsapp', 'product_id' => $product->id]);

        $generation = AiGeneration::where('type', 'ad_text')->where('step', 'ad_generation')->firstOrFail();
        $this->assertSame('groq:audit-model', $generation->model_used);
        $this->assertSame(123, $generation->prompt_tokens);
        $this->assertSame(45, $generation->completion_tokens);
        $this->assertSame(7, $generation->cost_estimated_fcfa);
        $this->assertSame(1, $shop->fresh()->ai_credits_used);
    }

    #[DataProvider('proxyRequests')]
    public function test_forwarded_headers_are_only_accepted_from_trusted_proxies(string $remote, string $expectedIp, bool $secure): void
    {
        Route::get('/audit/request-info', fn (Request $request) => response()->json([
            'ip' => $request->ip(), 'secure' => $request->isSecure(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => $remote])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.10', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/audit/request-info')->assertOk()->assertExactJson(['ip' => $expectedIp, 'secure' => $secure]);
    }

    public static function proxyRequests(): array
    {
        return [
            'public untrusted client' => ['203.0.113.20', '203.0.113.20', false],
            'local trusted proxy' => ['127.0.0.1', '203.0.113.10', true],
        ];
    }

    public function test_production_rejects_unconfigured_host(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->get('http://attacker.invalid/')->assertBadRequest();
        } finally {
            $this->app->instance('env', 'testing');
            Request::setTrustedHosts([]);
        }
    }
}
