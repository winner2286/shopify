<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMerchant(string $email = 'merchant@test.com'): User
    {
        return User::create([
            'name' => 'Marchand Test',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => User::ROLE_MERCHANT,
            'country' => 'TG',
            'phone' => '+228 90 12 34 56',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);
    }

    private function createShop(User $user, string $subdomain = 'boutique-lome'): Shop
    {
        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique Lomé',
            'subdomain' => $subdomain,
            'status' => Shop::PUBLISHED,
            'business_category' => 'mode',
            'brand_color' => '#6d3df2',
            'contact' => [
                'phone' => '+228 90 12 34 56',
                'whatsapp' => '+228 90 12 34 56',
                'email' => 'contact@lome.tg',
                'city' => 'Lomé',
            ],
        ]);

        $shop->ensureDefaultShippingZones();

        return $shop;
    }

    public function test_checkout_redirects_if_cart_is_empty(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $response = $this->get("/b/{$shop->subdomain}/commander");

        $response->assertRedirect("/b/{$shop->subdomain}/panier");
        $response->assertSessionHas('error');
    }

    public function test_successful_order_placement_decrements_stock_and_creates_records(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Robe Africaine',
            'slug' => 'robe-africaine',
            'price' => 12000,
            'stock' => 10,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $zone = $shop->shippingZones()->where('is_pickup', false)->first();

        // 1. Ajouter 2 robes au panier
        $this->post("/b/{$shop->subdomain}/panier/ajouter", [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // 2. Passer la commande
        $response = $this->post("/b/{$shop->subdomain}/commander", [
            'customer_name' => 'Afi Lawson',
            'customer_phone' => '+228 91 22 33 44',
            'customer_email' => 'afi@test.com',
            'shipping_zone_id' => $zone->id,
            'delivery_address' => 'Tokoin Doumasséssé, près de la poste',
            'delivery_notes' => 'Appeler dès que vous êtes sur place',
            'payment_method' => 'cash_on_delivery',
        ]);

        $response->assertRedirect();

        // 3. Vérifications en base de données
        $order = Order::withoutGlobalScopes()->where('shop_id', $shop->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('Afi Lawson', $order->customer_name);
        $this->assertEquals(24000, $order->subtotal); // 12000 * 2
        $this->assertEquals($zone->price, $order->shipping_cost);
        $this->assertEquals(24000 + $zone->price, $order->total);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);

        // Ligne de commande
        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals(2, $order->items()->first()->quantity);

        // Décrémentation du stock
        $this->assertEquals(8, $product->fresh()->stock); // 10 - 2 = 8

        // Traçabilité du mouvement de stock
        $movement = StockMovement::withoutGlobalScopes()->where('product_id', $product->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity_change);
        $this->assertEquals('order', $movement->reason);

        // Fiche client créée (CDC E-04)
        $customer = Customer::withoutGlobalScopes()->where('phone', '+228 91 22 33 44')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Afi Lawson', $customer->name);
        $this->assertEquals(1, $customer->total_orders_count);

        // Panier vidé
        $this->assertEmpty(session("cart_{$shop->id}", []));
    }

    public function test_order_cannot_be_placed_if_stock_is_insufficient(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Sneakers Togo',
            'slug' => 'sneakers-togo',
            'price' => 15000,
            'stock' => 1,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $zone = $shop->shippingZones()->first();

        // On injecte 3 articles dans le panier
        session(["cart_{$shop->id}" => [
            $product->id => [
                'product_id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => $product->price,
                'quantity' => 3, // Demande 3 alors que stock = 1
                'image_url' => null,
                'stock' => 1,
            ],
        ]]);

        $response = $this->post("/b/{$shop->subdomain}/commander", [
            'customer_name' => 'Client Trop Gourmand',
            'customer_phone' => '+228 90 00 00 00',
            'shipping_zone_id' => $zone->id,
            'delivery_address' => 'Lomé',
            'payment_method' => 'cash_on_delivery',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, Order::withoutGlobalScopes()->count());
        $this->assertEquals(1, $product->fresh()->stock); // Stock non altéré
    }

    public function test_mobile_money_simulation_confirms_order_and_payment(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-20261026-9999',
            'customer_name' => 'Edoh Koudjo',
            'customer_phone' => '+228 92 11 22 33',
            'delivery_zone_name' => 'Lomé Intra-muros',
            'delivery_address' => 'Adéwui',
            'subtotal' => 15000,
            'shipping_cost' => 1000,
            'total' => 16000,
            'payment_method' => Order::PAYMENT_TMONEY,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->payments()->create([
            'shop_id' => $shop->id,
            'payment_reference' => 'PAY-TEST-TMONEY',
            'method' => Order::PAYMENT_TMONEY,
            'amount' => 16000,
            'status' => 'pending',
        ]);

        $response = $this->post("/b/{$shop->subdomain}/commandes/{$order->order_number}/simuler-paiement");
        $response->assertSessionHas('status');

        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_merchant_can_process_order_lifecycle(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-PROCESS-01',
            'customer_name' => 'Ama Sedjro',
            'customer_phone' => '+228 90 44 55 66',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Hédzranawoé',
            'subtotal' => 10000,
            'shipping_cost' => 1000,
            'total' => 11000,
            'payment_method' => Order::PAYMENT_CASH,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        // 1. Confirmer la commande
        $this->actingAs($user)->post(route('orders.update_status', $order), [
            'status' => Order::STATUS_CONFIRMED,
        ]);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);

        // 2. Marquer comme expédiée
        $this->actingAs($user)->post(route('orders.update_status', $order), [
            'status' => Order::STATUS_SHIPPED,
        ]);
        $this->assertEquals(Order::STATUS_SHIPPED, $order->fresh()->status);

        // 3. Marquer le paiement comme encaissé
        $this->actingAs($user)->post(route('orders.mark_paid', $order));
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->fresh()->payment_status);

        // 4. Marquer comme livrée
        $this->actingAs($user)->post(route('orders.update_status', $order), [
            'status' => Order::STATUS_DELIVERED,
        ]);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->fresh()->status);

        // 5. Vérifier que l'historique d'audit contient toutes les transitions
        $this->assertGreaterThanOrEqual(4, $order->statusHistories()->count());
    }

    public function test_order_cancellation_restores_inventory_automatically(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Montre Or',
            'slug' => 'montre-or',
            'price' => 50000,
            'stock' => 5, // Stock actuel après achat
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-CANCEL-01',
            'customer_name' => 'Kodjo Test',
            'customer_phone' => '+228 90 11 22 33',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Bè',
            'subtotal' => 100000,
            'shipping_cost' => 0,
            'total' => 100000,
            'payment_method' => Order::PAYMENT_CASH,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'unit_price' => 50000,
            'quantity' => 2,
            'total_price' => 100000,
        ]);

        // Annulation de la commande par le commerçant avec motif obligatoire (CDC E-05)
        $resCancel = $this->actingAs($user)->post(route('orders.cancel', $order), [
            'cancellation_reason' => 'Demande d\'annulation par le client suite à un changement d\'adresse',
        ]);
        $resCancel->assertSessionHas('status');

        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);

        // RESTITUTION AUTOMATIQUE DU STOCK : 5 + 2 = 7 !
        $this->assertEquals(7, $product->fresh()->stock);

        // Traçabilité du mouvement de stock
        $movement = StockMovement::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('reason', 'cancellation')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(2, $movement->quantity_change);
    }

    public function test_tenant_isolation_prevents_viewing_other_merchant_orders(): void
    {
        $user1 = $this->createMerchant('marchand1@test.com');
        $shop1 = $this->createShop($user1, 'shop-un');

        $user2 = $this->createMerchant('marchand2@test.com');
        $shop2 = $this->createShop($user2, 'shop-deux');

        $order1 = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop1->id,
            'order_number' => 'CMD-ISOLATION-01',
            'customer_name' => 'Client Boutique 1',
            'customer_phone' => '+228 90 00 00 01',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Adidogomé',
            'subtotal' => 15000,
            'shipping_cost' => 1000,
            'total' => 16000,
            'payment_method' => Order::PAYMENT_CASH,
            'status' => Order::STATUS_PENDING,
        ]);

        // Marchand 2 tente d'accéder au détail de la commande de la Boutique 1 (RG-02)
        $response = $this->actingAs($user2)->get(route('orders.show', $order1));
        // BelongsToShopScope filtre automatiquement -> 404 (ressource inexistante pour lui)
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
