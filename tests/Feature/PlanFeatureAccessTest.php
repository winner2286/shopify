<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ToolRegistry;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_essentiel_can_open_marketing_texts_but_cannot_open_or_submit_reserved_features(): void
    {
        [$merchant, $shop] = $this->merchantWithPlan('essentiel');
        $ad = Advertisement::create(['shop_id' => $shop->id, 'channel' => 'whatsapp', 'objective' => 'nouveaute', 'tone' => 'dynamique', 'generated_texts' => []]);

        $this->actingAs($merchant)->get(route('dashboard'))->assertOk()
            ->assertSee('Textes marketing')
            ->assertDontSee('id="nav-analytics"', false)
            ->assertDontSee('id="nav-discounts"', false)
            ->assertDontSee('Voir stats');
        $this->get(route('ai.marketing'))->assertOk()
            ->assertDontSee(route('ai.automations'), false)
            ->assertDontSee(route('ai.studio'), false);
        $this->get(route('products.create'))->assertOk()->assertSee('Studio photo produit');

        foreach (['ads.index', 'ads.create', 'ads.flyers', 'discounts.index', 'analytics.index', 'analytics.advisor', 'ai.automations', 'ai.studio', 'orders.export_csv', 'analytics.export_csv'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        foreach (['ads.studio.store', 'discounts.store', 'ai.automations.run_now', 'ai.studio.generate', 'analytics.advisor.briefing'] as $name) {
            $this->postJson(route($name))->assertForbidden();
        }
        $this->postJson(route('ads.studio.ai', $ad))->assertForbidden();

        $tools = app(ToolRegistry::class);
        $names = collect($tools->getToolsSchema($shop, $merchant))->pluck('function.name');
        $this->assertContains('generate_marketing_copy', $names);
        $this->assertNotContains('generate_video_ad_script', $names);
        $this->assertNotContains('get_inventory_alerts', $names);
        $this->assertSame('error', $tools->executeFromModel('get_sales_analytics', $shop, [], $merchant)['status']);
    }

    public function test_pro_can_use_creative_promotions_and_statistics_but_csv_is_reserved_for_premium(): void
    {
        [$merchant] = $this->merchantWithPlan('pro');

        $this->actingAs($merchant)->get(route('dashboard'))->assertOk()
            ->assertSee('id="nav-analytics"', false)
            ->assertSee('id="nav-discounts"', false)
            ->assertSee(route('ads.index'), false);
        $this->get(route('ads.create'))->assertOk();
        $this->get(route('discounts.index'))->assertOk();
        $this->get(route('ai.automations'))->assertOk();
        $this->get(route('analytics.index'))->assertOk()->assertDontSee(route('analytics.export_csv'), false);
        $this->get(route('orders.index'))->assertOk()->assertDontSee(route('orders.export_csv'), false);
        $this->get(route('analytics.export_csv'))->assertForbidden();
        $this->get(route('orders.export_csv'))->assertForbidden();
    }

    public function test_premium_sees_and_can_download_both_csv_exports(): void
    {
        [$merchant] = $this->merchantWithPlan('premium');

        $this->actingAs($merchant)->get(route('analytics.index'))->assertOk()->assertSee(route('analytics.export_csv'), false);
        $this->get(route('orders.index'))->assertOk()->assertSee(route('orders.export_csv'), false);
        $this->get(route('analytics.export_csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->get(route('orders.export_csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /** @return array{User, Shop} */
    private function merchantWithPlan(string $slug): array
    {
        $this->seed(PlanSeeder::class);
        $merchant = User::factory()->create(['role' => User::ROLE_MERCHANT]);
        $shop = $merchant->shops()->create(['name' => 'Boutique de test', 'status' => Shop::DRAFT]);
        $shop->subscriptions()->create([
            'plan_id' => Plan::where('slug', $slug)->firstOrFail()->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$merchant, $shop];
    }
}
