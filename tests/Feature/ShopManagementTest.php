<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchantWithPlan(string $planSlug = 'essentiel'): array
    {
        $user = User::factory()->create([
            'email' => 'merchant_' . uniqid() . '@example.test',
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
            'country' => 'TG',
        ]);

        $plan = Plan::firstOrCreate(
            ['slug' => $planSlug],
            [
                'name' => ucfirst($planSlug),
                'monthly_price' => $planSlug === 'pro' ? 15000 : 10000,
                'max_products' => 50,
                'max_shops' => $planSlug === 'pro' ? 3 : 1,
                'storage_mb' => 2048,
                'ai_credits' => 100,
                'full_generations' => 1,
                'active' => true,
            ]
        );

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique 1',
            'subdomain' => 'boutique-1-' . uniqid(),
            'status' => Shop::DRAFT,
            'currency' => 'XOF',
        ]);

        Subscription::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$user, $shop, $plan];
    }

    public function test_forfait_essentiel_bloque_la_creation_au_dela_de_1_boutique(): void
    {
        [$user, $shop, $plan] = $this->createMerchantWithPlan('essentiel');

        $this->assertSame(1, $user->maxShops());
        $this->assertFalse($user->canCreateShop());

        // La vue de création affiche la limite atteinte
        $response = $this->actingAs($user)->get('/boutiques/creer');
        $response->assertOk();
        $response->assertSee('Limite de boutiques atteinte');

        // La tentative de soumission POST est bloquée
        $postResponse = $this->actingAs($user)->post('/boutiques', [
            'name' => 'Seconde Boutique',
            'country' => 'TG',
            'currency' => 'XOF',
        ]);

        $postResponse->assertRedirect('/boutiques/creer');
        $this->assertDatabaseMissing('shops', ['name' => 'Seconde Boutique']);
    }

    public function test_forfait_pro_autorise_jusqua_3_boutiques(): void
    {
        [$user, $shop, $plan] = $this->createMerchantWithPlan('pro');

        $this->assertSame(3, $user->maxShops());
        $this->assertTrue($user->canCreateShop());

        // La page de création est accessible normalement
        $response = $this->actingAs($user)->get('/boutiques/creer');
        $response->assertOk();
        $response->assertSee('Créer une nouvelle boutique');

        // Création de la 2e boutique
        $postResponse = $this->actingAs($user)->post('/boutiques', [
            'name' => 'Deuxième Boutique Pro',
            'country' => 'CI',
            'currency' => 'XOF',
        ]);

        $postResponse->assertRedirect('/tableau-de-bord');
        $this->assertDatabaseHas('shops', ['name' => 'Deuxième Boutique Pro']);

        // Vérifier que le nombre de boutiques de l'utilisateur est maintenant de 2
        $this->assertSame(2, $user->shopsCount());
        $this->assertTrue($user->canCreateShop()); // Il peut encore en créer 1 (limite = 3)
    }

    public function test_commutation_de_boutique_active(): void
    {
        [$user, $shop1, $plan] = $this->createMerchantWithPlan('pro');

        $shop2 = Shop::create([
            'user_id' => $user->id,
            'name' => 'Deuxième Boutique',
            'subdomain' => 'boutique-2-' . uniqid(),
            'status' => Shop::DRAFT,
            'currency' => 'XOF',
        ]);

        $response = $this->actingAs($user)->post("/boutiques/{$shop2->id}/basculer");
        $response->assertRedirect('/tableau-de-bord');
        $response->assertSessionHas('active_shop_id', $shop2->id);

        $this->assertSame($shop2->id, $user->activeShop()->id);
    }
}
