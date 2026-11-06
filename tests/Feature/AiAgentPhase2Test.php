<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeChunk;
use App\Models\AiShopMemory;
use App\Models\DiscountCode;
use App\Models\Plan;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\AiRagService;
use App\Services\Ai\AiShopMemoryService;
use App\Services\Ai\Tools\ManageDiscountCodesTool;
use App\Services\Ai\Tools\ManageShippingZonesTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected Shop $shop;
    protected User $user;
    protected Plan $essentielPlan;
    protected Plan $proPlan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->essentielPlan = Plan::firstOrCreate(
            ['slug' => 'essentiel'],
            [
                'name' => 'Essentiel',
                'price_monthly' => 5000,
                'price_yearly' => 50000,
                'features' => ['products_limit' => 20],
                'is_active' => true,
            ]
        );

        $this->proPlan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Professionnel',
                'price_monthly' => 15000,
                'price_yearly' => 150000,
                'features' => ['products_limit' => 200, 'promo_codes' => true],
                'is_active' => true,
            ]
        );

        $this->user = User::factory()->create([
            'email' => 'merchant_phase2@zanika.shop',
            'role' => 'merchant',
            'email_verified_at' => now(),
        ]);

        $this->shop = Shop::create([
            'user_id' => $this->user->id,
            'name' => 'Boutique Épices d\'Afrique',
            'slug' => 'epices-afrique',
            'subdomain' => 'epices-afrique',
            'currency' => 'FCFA',
            'is_active' => true,
        ]);

        $this->subscription = Subscription::create([
            'shop_id' => $this->shop->id,
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
        ]);
    }

    public function test_shipping_zones_tool_lists_and_proposes_creation_with_hmac(): void
    {
        $tool = new ManageShippingZonesTool();

        // 1. Lister les zones (initialise les zones par défaut si vide)
        $listResult = $tool->execute($this->shop, ['action' => 'list'], $this->user);
        $this->assertEquals('success', $listResult['status']);
        $this->assertGreaterThan(0, $listResult['zones_count']);

        // 2. Proposer l'ajout d'une nouvelle zone
        $createProposal = $tool->execute($this->shop, [
            'action' => 'create',
            'name' => 'Baguida Plage & Avepozo',
            'price' => 2500,
            'free_shipping_threshold' => 35000,
            'delivery_estimate' => 'Dans la journée en 4h',
        ], $this->user);

        $this->assertEquals('action_required', $createProposal['status']);
        $this->assertNotEmpty($createProposal['action_token']);
        $this->assertStringContainsString('Baguida Plage & Avepozo', $createProposal['message']);

        // 3. Valider avec le jeton HMAC
        $confirmPayload = array_merge($createProposal['shipping_payload'], [
            'confirmed_by_user' => true,
            'action_token' => $createProposal['action_token'],
        ]);

        $confirmResult = $tool->execute($this->shop, $confirmPayload, $this->user);
        $this->assertEquals('success', $confirmResult['status']);

        $zone = ShippingZone::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('name', 'Baguida Plage & Avepozo')
            ->first();

        $this->assertNotNull($zone);
        $this->assertEquals(2500, $zone->price);
        $this->assertEquals(35000, $zone->free_shipping_threshold);
    }

    public function test_discount_codes_tool_respects_plan_subscription_quota(): void
    {
        // Forcer le plan sur Essentiel (qui ne possède pas la feature promo_codes)
        $this->subscription->update(['plan_id' => $this->essentielPlan->id]);
        $this->shop->unsetRelation('subscription');

        $tool = new ManageDiscountCodesTool();
        $result = $tool->execute($this->shop, [
            'action' => 'create',
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
        ], $this->user);

        $this->assertEquals('feature_restricted', $result['status']);
        $this->assertStringContainsString('réservée aux formules Pro et Premium', $result['error']);
    }

    public function test_discount_codes_tool_creates_code_with_hmac_confirmation(): void
    {
        // Sur le plan Pro
        $this->subscription->update(['plan_id' => $this->proPlan->id]);
        $this->shop->unsetRelation('subscription');
        $tool = new ManageDiscountCodesTool();

        // 1. Proposition
        $proposal = $tool->execute($this->shop, [
            'action' => 'create',
            'code' => 'RAMADAN20',
            'type' => 'percentage',
            'value' => 20,
            'min_order_amount' => 25000,
            'max_uses' => 100,
            'days_valid' => 30,
        ], $this->user);

        $this->assertEquals('action_required', $proposal['status']);
        $this->assertNotEmpty($proposal['action_token']);

        // 2. Confirmation avec jeton
        $confirmArgs = array_merge($proposal['promo_payload'], [
            'confirmed_by_user' => true,
            'action_token' => $proposal['action_token'],
        ]);

        $execResult = $tool->execute($this->shop, $confirmArgs, $this->user);
        $this->assertEquals('success', $execResult['status']);
        $this->assertEquals('RAMADAN20', $execResult['code']);

        // Vérifier l'enregistrement en base
        $discount = DiscountCode::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('code', 'RAMADAN20')
            ->first();

        $this->assertNotNull($discount);
        $this->assertEquals('percentage', $discount->type);
        $this->assertEquals(20, $discount->value);
        $this->assertEquals(25000, $discount->min_order_amount);
        $this->assertTrue($discount->isValid(30000));
        $this->assertFalse($discount->isValid(10000)); // inférieur au min_order_amount
        $this->assertEquals(6000, $discount->calculateDiscount(30000)); // 20% de 30000 = 6000
    }

    public function test_discount_codes_tool_rejects_excessive_percentage(): void
    {
        $this->subscription->update(['plan_id' => $this->proPlan->id]);
        $this->shop->unsetRelation('subscription');
        $tool = new ManageDiscountCodesTool();

        $result = $tool->execute($this->shop, [
            'action' => 'create',
            'code' => 'CRAZY95',
            'type' => 'percentage',
            'value' => 95,
        ], $this->user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('ne peut pas excéder 90%', $result['error']);
    }

    public function test_ai_shop_memory_service_isolates_and_manages_4_axes(): void
    {
        $memoryService = new AiShopMemoryService();

        // Créer une 2ème boutique pour tester l'isolation multi-tenant
        $otherUser = User::factory()->create(['email' => 'other_merchant@zanika.shop']);
        $otherShop = Shop::create([
            'user_id' => $otherUser->id,
            'name' => 'Autre Boutique',
            'slug' => 'autre-boutique',
            'subdomain' => 'autre-boutique',
            'currency' => 'FCFA',
            'plan_id' => $this->proPlan->id,
            'is_active' => true,
        ]);

        // Extraire des faits pour la boutique 1
        $memoryService->extractAndSaveFacts(
            $this->shop,
            "Bonjour, je vends des chaussures de marque et je livre principalement à Lomé. Mon objectif est d'atteindre 500000 FCFA ce mois-ci. Réponds court s'il te plaît.",
            "Très bien, c'est noté !"
        );

        $shopMemories = $memoryService->listByAxis($this->shop);
        $this->assertGreaterThanOrEqual(4, $shopMemories->count());

        // Vérifier l'isolation : la boutique 2 ne doit avoir aucune mémoire
        $otherMemories = $memoryService->listByAxis($otherShop);
        $this->assertCount(0, $otherMemories);

        // Vérifier les 4 axes sur boutique 1
        $profile = $memoryService->listByAxis($this->shop, AiShopMemory::CAT_PROFILE);
        $this->assertCount(1, $profile);
        $this->assertStringContainsString('Chaussures', $profile->first()->memory_value);

        $habit = $memoryService->listByAxis($this->shop, AiShopMemory::CAT_HABIT);
        $this->assertTrue($habit->contains(fn($m) => str_contains($m->memory_value, 'Lomé')));

        $goal = $memoryService->listByAxis($this->shop, AiShopMemory::CAT_GOAL);
        $this->assertTrue($goal->contains(fn($m) => str_contains($m->memory_value, '500 000')));

        $preference = $memoryService->listByAxis($this->shop, AiShopMemory::CAT_PREFERENCE);
        $this->assertTrue($preference->contains(fn($m) => str_contains($m->memory_value, 'concises')));

        // Tester le droit à l'oubli souverain
        $deleted = $memoryService->forgetMemory($this->shop, 'style_reponse');
        $this->assertTrue($deleted);
        $this->assertNull($memoryService->getFact($this->shop, 'style_reponse'));

        // Tout oublier
        $count = $memoryService->forgetAllMemories($this->shop);
        $this->assertGreaterThan(0, $count);
        $this->assertCount(0, $memoryService->listByAxis($this->shop));
    }

    public function test_ai_rag_hybrid_retrieval_and_anti_injection_formatting(): void
    {
        AiKnowledgeChunk::query()->delete();

        // 1. Insérer des chunks de test
        AiKnowledgeChunk::create([
            'slug' => 'guide-livraison-cod',
            'title' => 'Gestion de la livraison et Paiement à la Livraison (COD)',
            'category' => 'logistics',
            'content' => 'Le paiement à la livraison (Cash on Delivery) nécessite une confirmation téléphonique préalable pour réduire le taux de refus.',
            'keywords' => ['livraison', 'cod', 'transporteur', 'lome'],
            'target_routes' => 'orders.index, shipping.index',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        AiKnowledgeChunk::create([
            'slug' => 'guide-codes-promo',
            'title' => 'Configuration des codes promo et remises',
            'category' => 'marketing',
            'content' => 'Les codes promotionnels permettent d\'accorder un rabais en pourcentage ou un montant fixe. Accessible dès le plan Pro.',
            'keywords' => ['promo', 'reduction', 'solde', 'rabais'],
            'target_routes' => 'discounts.index',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $ragService = new AiRagService();

        // 2. Recherche hybride avec faute de frappe ("livraion" au lieu de "livraison")
        $fuzzyResults = $ragService->search('comment gerer la livraion cod');
        $this->assertNotEmpty($fuzzyResults);
        $this->assertEquals('guide-livraison-cod', $fuzzyResults->first()->slug);

        // 3. Formatage anti-prompt-injection
        $formatted = $ragService->formatForPrompt($fuzzyResults);
        $this->assertStringContainsString('<reference_document', $formatted);
        $this->assertStringContainsString('CONSIGNE DE SÉCURITÉ IMPORTANTE', $formatted);
        $this->assertStringContainsString('STRICTEMENT PASSIVES', $formatted);
        $this->assertStringContainsString('orders.index', $formatted);
    }
}
