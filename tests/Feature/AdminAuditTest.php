<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\Shop;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_audit_page_with_data(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $merchant = User::factory()->create([
            'role' => User::ROLE_MERCHANT,
        ]);

        $shop = Shop::create([
            'user_id' => $merchant->id,
            'name' => 'Test Shop',
            'subdomain' => 'test-shop',
            'status' => Shop::PUBLISHED,
            'currency' => 'XOF',
        ]);

        Order::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'order_number' => 'CMD-001',
            'customer_name' => 'Client Test',
            'customer_phone' => '90000000',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Adidogomé',
            'subtotal' => 10000,
            'shipping_cost' => 1000,
            'total' => 11000,
            'status' => Order::STATUS_CONFIRMED,
        ]);

        PushSubscription::create([
            'user_id' => $admin->id,
            'endpoint' => 'https://push.example.com/admin',
            'public_key' => 'key_admin',
            'auth_token' => 'auth_admin',
        ]);

        PushSubscription::create([
            'user_id' => $merchant->id,
            'endpoint' => 'https://push.example.com/merchant',
            'public_key' => 'key_merchant',
            'auth_token' => 'auth_merchant',
        ]);

        SystemAlert::create([
            'type' => 'merchant_registered',
            'severity' => 'info',
            'title' => 'Nouveau commerçant inscrit',
            'message' => 'Le commerçant Test s\'est inscrit.',
            'data' => ['user_id' => $merchant->id, 'action_url' => 'https://example.com'],
            'is_read' => false,
        ]);

        SystemAlert::create([
            'type' => 'order_fraud',
            'severity' => 'critical',
            'title' => 'Commande anormale détectée',
            'message' => 'Gros montant.',
            'data' => ['order_id' => 1],
            'is_read' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/audit');

        if ($response->status() !== 200) {
            dump($response->getContent());
        }

        $response->assertStatus(200);
        $response->assertSee('Audit, Contrôle & Alertes Système');
        $response->assertSee('Nouveau commerçant inscrit');
        $response->assertSee('Commande anormale détectée');
    }

    public function test_health_check_endpoint(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->post('/admin/audit/test-sante');
        $response->assertRedirect();
        $response->assertSessionHas('status');
    }
}
