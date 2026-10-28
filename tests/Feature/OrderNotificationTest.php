<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMerchant(string $email = 'merchant.notif@test.com'): User
    {
        return User::create([
            'name' => 'Kofi Vendeur',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => User::ROLE_MERCHANT,
            'country' => 'TG',
            'phone' => '+228 90 11 22 33',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);
    }

    private function createShop(User $user, string $subdomain = 'notif-shop'): Shop
    {
        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Élégance Wax Lomé',
            'subdomain' => $subdomain,
            'status' => Shop::PUBLISHED,
            'business_category' => 'mode',
            'brand_color' => '#9e1b32',
            'contact' => [
                'phone' => '+228 90 11 22 33',
                'whatsapp' => '+228 90 11 22 33',
                'email' => 'contact@elegance.tg',
                'city' => 'Lomé',
            ],
        ]);

        $shop->ensureDefaultShippingZones();

        return $shop;
    }

    public function test_whatsapp_service_formats_complete_merchant_alert(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-WA-001',
            'customer_name' => 'Ablanvi Mensah',
            'customer_phone' => '+228 91 88 77 66',
            'customer_email' => 'ablanvi@test.tg',
            'delivery_zone_name' => 'Lomé Centre',
            'delivery_address' => 'Quartier Nyékonakpoè, Rue 12',
            'delivery_notes' => 'Livrer avant 14h',
            'subtotal' => 24000,
            'shipping_cost' => 1500,
            'total' => 25500,
            'payment_method' => 'tmoney',
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_title' => 'Robe Africaine Prestige',
            'unit_price' => 12000,
            'quantity' => 2,
            'total_price' => 24000,
        ]);

        $service = app(WhatsAppNotificationService::class);
        $message = $service->formatMerchantAlert($order);

        $this->assertStringContainsString('NOUVELLE COMMANDE REÇUE', $message);
        $this->assertStringContainsString('Élégance Wax Lomé', $message);
        $this->assertStringContainsString('ORD-WA-001', $message);
        $this->assertStringContainsString('Ablanvi Mensah', $message);
        $this->assertStringContainsString('+228 91 88 77 66', $message);
        $this->assertStringContainsString('Nyékonakpoè', $message);
        $this->assertStringContainsString('Livrer avant 14h', $message);
        $this->assertStringContainsString('2x *Robe Africaine Prestige*', $message);
        $this->assertStringContainsString('25 500 FCFA', $message);
        $this->assertStringContainsString('T-Money', $message);

        // URL directe WhatsApp vers le marchand
        $waUrl = $service->getMerchantWhatsAppUrl($order);
        $this->assertNotNull($waUrl);
        $this->assertStringStartsWith('https://wa.me/22890112233?text=', $waUrl);
    }

    public function test_guest_cannot_access_order_notifications_check(): void
    {
        $this->get(route('orders.notifications.check'))->assertRedirect(route('login'));
    }

    public function test_authenticated_merchant_polls_and_detects_new_orders(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        // 1. Initialement, aucune commande
        $response = $this->actingAs($user)->getJson(route('orders.notifications.check'));
        $response->assertOk();
        $response->assertJson([
            'has_new' => false,
            'count' => 0,
            'pending_total' => 0,
        ]);

        // 2. Création d'une commande
        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-LIVE-002',
            'customer_name' => 'Fofo Lawson',
            'customer_phone' => '+228 92 33 44 55',
            'delivery_zone_name' => 'Agoè-Nyivé',
            'delivery_address' => 'Près du camp',
            'subtotal' => 15000,
            'shipping_cost' => 1000,
            'total' => 16000,
            'payment_method' => 'cash_on_delivery',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_title' => 'Chemise Lin Homme',
            'unit_price' => 15000,
            'quantity' => 1,
            'total_price' => 15000,
        ]);

        // 3. Le polling avec since_id=0 doit détecter cette nouvelle commande
        $responseWithNew = $this->actingAs($user)->getJson(route('orders.notifications.check', ['since_id' => 0]));
        $responseWithNew->assertOk();
        $responseWithNew->assertJson([
            'has_new' => true,
            'count' => 1,
            'latest_id' => $order->id,
            'pending_total' => 1,
            'order' => [
                'id' => $order->id,
                'order_number' => 'ORD-LIVE-002',
                'customer_name' => 'Fofo Lawson',
                'total_formatted' => '16 000 FCFA',
                'delivery_zone' => 'Agoè-Nyivé',
                'items_count' => 1,
            ],
        ]);

        // 4. Avec since_id = $order->id, il ne doit plus indiquer de nouvelle commande
        $responseCaughtUp = $this->actingAs($user)->getJson(route('orders.notifications.check', ['since_id' => $order->id]));
        $responseCaughtUp->assertOk();
        $responseCaughtUp->assertJson([
            'has_new' => false,
            'count' => 0,
            'latest_id' => $order->id,
            'pending_total' => 1,
        ]);
    }

    public function test_multi_tenant_isolation_in_order_notifications(): void
    {
        // Commerçant A
        $userA = $this->createMerchant('merchantA@test.com');
        $shopA = $this->createShop($userA, 'shop-a');

        // Commerçant B
        $userB = $this->createMerchant('merchantB@test.com');
        $shopB = $this->createShop($userB, 'shop-b');

        // Une commande est passée pour la boutique A uniquement
        $orderA = Order::withoutGlobalScopes()->create([
            'shop_id' => $shopA->id,
            'order_number' => 'ORD-SHOP-A',
            'customer_name' => 'Client Boutique A',
            'customer_phone' => '+228 90 00 00 01',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Boulevard circulaire',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'total' => 10000,
            'payment_method' => 'tmoney',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);

        // Commerçant B vérifie ses notifications : il ne doit RIEN recevoir sur la commande de A
        $responseB = $this->actingAs($userB)->getJson(route('orders.notifications.check'));
        $responseB->assertOk();
        $responseB->assertJson([
            'has_new' => false,
            'count' => 0,
            'pending_total' => 0,
            'order' => null,
        ]);
    }
}
