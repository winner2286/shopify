<?php

namespace Tests\Feature;

use App\Models\AiProactiveInsight;
use App\Models\AiShopMemory;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiProactiveAdvisorService;
use App\Services\Ai\AiShopMemoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiAgentPhase4Test extends TestCase
{
    use RefreshDatabase;

    protected Shop $shop;
    protected User $user;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate(
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

        $this->user = User::factory()->create([
            'email' => 'merchant_phase4@zanika.shop',
            'role' => 'merchant',
            'email_verified_at' => now(),
        ]);

        $this->shop = Shop::create([
            'user_id' => $this->user->id,
            'name' => 'Boutique Wax & Chic',
            'slug' => 'wax-chic',
            'subdomain' => 'wax-chic',
            'currency' => 'FCFA',
            'is_active' => true,
        ]);
    }

    public function test_morning_briefing_command_dry_run_does_not_persist(): void
    {
        // Créer une commande passée hier
        Order::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-BRIEF-01',
            'customer_name' => 'Koffi Mensah',
            'customer_phone' => '+22890001122',
            'delivery_address' => 'Lomé Agbalépédogan',
            'delivery_zone_name' => 'Lomé Intra-muros',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 15000,
            'delivery_price' => 1500,
            'total' => 16500,
            'status' => Order::STATUS_PENDING,
            'created_at' => Carbon::yesterday()->setHour(14),
        ]);

        // Exécuter en mode dry-run
        $exitCode = Artisan::call('ai:morning-briefing', [
            '--shop' => $this->shop->id,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('ZANIKA AI COPILOT', $output);
        $this->assertStringContainsString('Boutique Wax & Chic', $output);
        $this->assertStringContainsString('Mode DRY-RUN', $output);

        // Vérifier qu'aucun insight n'a été persisté en base en mode dry-run
        $insight = AiProactiveInsight::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('insight_type', 'morning_briefing')
            ->first();

        $this->assertNull($insight);
    }

    public function test_morning_briefing_command_persists_insight_and_memory(): void
    {
        // 1. Commande de la veille (validée)
        $yesterdayOrder = Order::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-YEST-01',
            'customer_name' => 'Ablavi Doe',
            'customer_phone' => '+22891223344',
            'delivery_address' => 'Lomé Hedzranawoé',
            'delivery_zone_name' => 'Lomé Intra-muros',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 20000,
            'delivery_price' => 1000,
            'total' => 21000,
            'status' => Order::STATUS_DELIVERED,
        ]);
        $yesterdayOrder->created_at = Carbon::yesterday()->setHour(16);
        $yesterdayOrder->saveQuietly();

        // 2. Commande en attente (à expédier aujourd'hui)
        Order::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-TODAY-01',
            'customer_name' => 'Fofo Lawson',
            'customer_phone' => '+22892334455',
            'delivery_address' => 'Lomé Bè',
            'delivery_zone_name' => 'Lomé Intra-muros',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 10000,
            'delivery_price' => 1000,
            'total' => 11000,
            'status' => Order::STATUS_PENDING,
            'created_at' => Carbon::now(),
        ]);

        // 3. Produit en rupture de stock
        Product::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'title' => 'Tissu Kente Cérémonial',
            'slug' => 'tissu-kente-ceremonial',
            'price' => 35000,
            'stock' => 0,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // Exécuter l'artisan command sans dry-run
        $exitCode = Artisan::call('ai:morning-briefing', [
            '--shop' => $this->shop->id,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('21 000 FCFA', $output);
        $this->assertStringContainsString('1 commande(s) en attente', $output);
        $this->assertStringContainsString('1 produit(s) en rupture totale', $output);

        // Vérifier la persistance dans AiProactiveInsight
        $insight = AiProactiveInsight::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('insight_type', 'morning_briefing')
            ->first();

        $this->assertNotNull($insight);
        $this->assertEquals(AiProactiveInsight::SEVERITY_WARNING, $insight->severity);
        $this->assertStringContainsString('Briefing matinal', $insight->title);
        $this->assertStringContainsString('21 000 FCFA', $insight->explanation);

        // Vérifier la persistance dans AiShopMemory
        $memory = AiShopMemory::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('memory_key', 'dernier_briefing_matinal')
            ->first();

        $this->assertNotNull($memory);
        $this->assertStringContainsString('Briefing', $memory->memory_value);
    }
}
