<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
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

        // Vérifier que l'appel JSON retourne un 403 propre sans RouteNotFoundException ni TypeError
        $jsonResponse = $this->actingAs($user)->postJson('/boutiques', [
            'name' => 'Seconde Boutique JSON',
            'country' => 'TG',
            'currency' => 'XOF',
        ]);
        $jsonResponse->assertStatus(403);
        $jsonResponse->assertJsonFragment(['error' => 'limit_reached']);
        $this->assertStringContainsString('/boutiques/creer', (string) $jsonResponse->json('upgrade_url'));
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

    public function test_creation_boutique_protege_contre_les_sous_domaines_reserves(): void
    {
        [$user, $shop, $plan] = $this->createMerchantWithPlan('pro');

        $postResponse = $this->actingAs($user)->post('/boutiques', [
            'name' => 'Admin',
            'country' => 'TG',
            'currency' => 'XOF',
        ]);

        $postResponse->assertRedirect('/tableau-de-bord');
        $created = Shop::where('user_id', $user->id)->where('name', 'Admin')->firstOrFail();
        $this->assertNotSame('admin', $created->subdomain);
        $this->assertStringStartsWith('admin-', $created->subdomain);
    }

    public function test_commutation_de_boutique_active_et_isolation_belongs_to_shop_scope(): void
    {
        [$user, $shop1, $plan] = $this->createMerchantWithPlan('pro');

        $shop2 = Shop::create([
            'user_id' => $user->id,
            'name' => 'Deuxième Boutique',
            'subdomain' => 'boutique-2-' . uniqid(),
            'status' => Shop::PUBLISHED,
            'currency' => 'XOF',
        ]);

        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop1->id,
            'title' => 'Article Boutique 1',
            'slug' => 'article-boutique-1-' . uniqid(),
            'price' => 5000,
            'stock' => 10,
            'status' => Product::STATUS_ACTIVE,
        ]);

        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop2->id,
            'title' => 'Article Boutique 2',
            'slug' => 'article-boutique-2-' . uniqid(),
            'price' => 8000,
            'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // Basculer sur $shop1 (bien que $shop2 soit plus récente)
        $response = $this->actingAs($user)->post("/boutiques/{$shop1->id}/basculer");
        $response->assertRedirect('/tableau-de-bord');
        $response->assertSessionHas('active_shop_id', $shop1->id);

        $user->unsetRelations();
        $this->assertSame($shop1->id, $user->activeShop()->id);
        $this->assertSame($shop1->id, $user->shop->id);

        // BelongsToShopScope et ProductController::index doivent refléter $shop1 uniquement
        $this->assertSame(1, Product::count());
        $this->assertSame('Article Boutique 1', Product::first()->title);

        // L'accès multi-boutiques au suivi de commande sur $shop2 reste autorisé même avec $shop1 active
        $orderShop2 = Order::withoutGlobalScopes()->create([
            'shop_id' => $shop2->id,
            'order_number' => 'ORD-MULTI-' . uniqid(),
            'customer_name' => 'Koffi',
            'customer_phone' => '90112233',
            'delivery_zone_name' => 'Lomé',
            'delivery_address' => 'Tokoin Doumasséssé',
            'subtotal' => 8000,
            'shipping_cost' => 1000,
            'total' => 9000,
            'status' => Order::STATUS_PENDING,
        ]);

        $orderView = $this->actingAs($user)->get("/b/{$shop2->subdomain}/commandes/{$orderShop2->order_number}");
        $orderView->assertOk();

        // L'abonnement sur $shop2 peut être payé/simulé en sandbox même si $shop1 est active
        config(['feexpay.mode' => 'SANDBOX']);
        $subShop2 = Subscription::create([
            'shop_id' => $shop2->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        $simResponse = $this->actingAs($user)->post("/abonnement/simulate/{$subShop2->id}/{$plan->id}");
        $simResponse->assertRedirect('/tableau-de-bord');

        // Un tiers ne peut jamais simuler le paiement d'une boutique qui ne lui appartient pas
        $stranger = User::factory()->create(['role' => User::ROLE_MERCHANT]);
        $forbiddenSim = $this->actingAs($stranger)->post("/abonnement/simulate/{$subShop2->id}/{$plan->id}");
        $forbiddenSim->assertStatus(403);

        // La checklist dynamique reflète la création d'une publicité
        Advertisement::withoutGlobalScopes()->create([
            'shop_id' => $shop1->id,
            'channel' => 'whatsapp',
            'objective' => 'promotion',
            'tone' => 'dynamique',
            'generated_texts' => ['Texte test'],
        ]);
        $dashboard = $this->actingAs($user)->get('/tableau-de-bord');
        $dashboard->assertOk();

        // Un utilisateur super-admin non persisté en BDD ne déclenche pas d'écriture SQL lors de isAdmin()
        $unsavedAdmin = new User(['email' => 'kougnima@icloud.com', 'role' => User::ROLE_MERCHANT]);
        $this->assertTrue($unsavedAdmin->isAdmin());
        $this->assertFalse($unsavedAdmin->exists);
    }
}

