<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Contracts\LlmProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiConversationThreadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => User::ROLE_MERCHANT]);
        $this->shop = Shop::create([
            'user_id' => $this->user->id,
            'name' => 'Boutique Test Thread',
            'subdomain' => 'test-thread',
            'status' => Shop::PUBLISHED,
            'currency' => 'XOF',
        ]);
    }

    public function test_clean_unwanted_greetings_strips_hallucinated_pleasantry(): void
    {
        $orchestrator = new AiOrchestrator();

        // Exact situation from the user screenshot:
        $userMsg = "Tu peux mettre une promotion en place ??";
        $llmOutput = "Bonjour ! Je vais très bien, merci, et ravi de te retrouver aussi !\n\nOui, absolument. Je peux t'aider à mettre en place une promotion.";

        $cleaned = $orchestrator->cleanUnwantedGreetings($llmOutput, $userMsg, 1);

        $this->assertStringNotContainsString('Bonjour', $cleaned);
        $this->assertStringNotContainsString('Je vais très bien', $cleaned);
        $this->assertStringNotContainsString('ravi de te retrouver', $cleaned);
        $this->assertStringStartsWith("Oui, absolument. Je peux t'aider", $cleaned);
    }

    public function test_clean_unwanted_greetings_preserves_legitimate_greeting(): void
    {
        $orchestrator = new AiOrchestrator();

        $userMsg = "bonjour";
        $llmOutput = "Bonjour ! Comment puis-je vous aider aujourd'hui sur votre boutique ?";

        $cleaned = $orchestrator->cleanUnwantedGreetings($llmOutput, $userMsg, 0);

        $this->assertStringStartsWith("Bonjour !", $cleaned);
    }

    public function test_clean_unwanted_greetings_preserves_how_are_you_reply_when_asked(): void
    {
        $orchestrator = new AiOrchestrator();

        $userMsg = "Salut, comment vas-tu ?";
        $llmOutput = "Bonjour ! Je vais très bien, merci. Comment puis-je t'aider ?";

        $cleaned = $orchestrator->cleanUnwantedGreetings($llmOutput, $userMsg, 0);

        $this->assertStringContainsString("Je vais très bien", $cleaned);
    }

    public function test_clean_unwanted_greetings_removes_greetings_on_turn_2_even_if_llm_greets(): void
    {
        $orchestrator = new AiOrchestrator();

        // Conversation already in progress (turn 2)
        $userMsg = "Et pour la livraison express ?";
        $llmOutput = "Bonjour ! Concernant la livraison express, nous pouvons configurer une zone.";

        $cleaned = $orchestrator->cleanUnwantedGreetings($llmOutput, $userMsg, 2);

        $this->assertStringNotContainsString('Bonjour', $cleaned);
        $this->assertStringStartsWith("Concernant la livraison express", $cleaned);
    }

    public function test_active_conversation_endpoint_returns_active_conversation(): void
    {
        $conv = AiConversation::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'title' => 'Discussion Active',
            'last_message_at' => now(),
        ]);

        AiMessage::create([
            'ai_conversation_id' => $conv->id,
            'role' => 'user',
            'content' => 'Premier message',
        ]);

        AiMessage::create([
            'ai_conversation_id' => $conv->id,
            'role' => 'assistant',
            'content' => 'Réponse du copilote',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('ai.conversations.active'));

        $response->assertStatus(200);
        $response->assertJson([
            'active' => true,
            'id' => $conv->id,
            'title' => 'Discussion Active',
        ]);
        $this->assertCount(2, $response->json('messages'));
    }

    public function test_orchestrator_reuses_recent_conversation_when_conversation_id_is_null(): void
    {
        $existingConv = AiConversation::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'title' => 'Discussion Précédente',
            'last_message_at' => now()->subMinutes(10),
        ]);

        // Mock LLM
        $mockLlm = new class implements LlmProviderInterface {
            public function chat(array $messages, array $tools = [], array $options = []): array {
                return [
                    'content' => "Bien sûr, je m'en occupe.",
                    'tool_calls' => null,
                    'tokens_input' => 10,
                    'tokens_output' => 10,
                    'model_used' => 'test-model',
                    'raw' => [],
                ];
            }
        };

        $orchestrator = new AiOrchestrator($mockLlm);

        // Appel sans conversation_id
        $result = $orchestrator->sendMessage($this->shop, $this->user, "Tu peux mettre une promotion en place ??", null);

        $this->assertEquals('success', $result['status']);
        // Doit avoir réutilisé la conversation existante active
        $this->assertEquals($existingConv->id, $result['conversation_id']);
    }

    public function test_orchestrator_creates_new_conversation_when_force_new_is_true(): void
    {
        $existingConv = AiConversation::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'title' => 'Discussion Précédente',
            'last_message_at' => now()->subMinutes(10),
        ]);

        $mockLlm = new class implements LlmProviderInterface {
            public function chat(array $messages, array $tools = [], array $options = []): array {
                return [
                    'content' => "Nouvelle discussion démarrée.",
                    'tool_calls' => null,
                    'tokens_input' => 10,
                    'tokens_output' => 10,
                    'model_used' => 'test-model',
                    'raw' => [],
                ];
            }
        };

        $orchestrator = new AiOrchestrator($mockLlm);

        $result = $orchestrator->sendMessage(
            $this->shop,
            $this->user,
            "Recommençons à zéro.",
            null,
            ['force_new_conversation' => true]
        );

        $this->assertEquals('success', $result['status']);
        $this->assertNotEquals($existingConv->id, $result['conversation_id']);
    }

    public function test_orchestrator_system_prompt_injects_plan_credits_features_and_strips_leaked_react_tags(): void
    {
        $essentielPlan = Plan::firstOrCreate(
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

        Subscription::create([
            'shop_id' => $this->shop->id,
            'plan_id' => $essentielPlan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->shop->update(['ai_credits_used' => 12]);
        $this->shop->unsetRelation('subscription');

        $spyLlm = new class implements LlmProviderInterface {
            public string $capturedSystemPrompt = '';

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->capturedSystemPrompt = (string) ($messages[0]['content'] ?? '');

                return [
                    'content' => "Thought: Je vérifie les chiffres.\nAction: get_orders_summary\nAnswer: Voici votre résumé du jour.",
                    'tool_calls' => null,
                    'tokens_input' => 20,
                    'tokens_output' => 15,
                    'model_used' => 'test-model',
                    'raw' => [],
                ];
            }
        };

        $orchestrator = new AiOrchestrator($spyLlm);
        $result = $orchestrator->sendMessage($this->shop, $this->user, 'Fais-moi un résumé');

        $this->assertEquals('success', $result['status']);
        $this->assertSame('Voici votre résumé du jour.', $result['message']['content']);
        $this->assertStringNotContainsString('Thought:', $result['message']['content']);
        $this->assertStringNotContainsString('Answer:', $result['message']['content']);

        $prompt = $spyLlm->capturedSystemPrompt;
        $this->assertStringContainsString('Boutique Test Thread', $prompt);
        $this->assertStringContainsString('Forfait actuel : Essentiel (essentiel)', $prompt);
        $this->assertStringContainsString('Crédits IA restants : 68 / 80', $prompt);
        $this->assertStringContainsString('Fonctionnalités réellement actives :', $prompt);
        $this->assertStringContainsString('Gestion du catalogue & produits (0/50 produits utilisés)', $prompt);
        $this->assertStringContainsString('Fonctionnalités NON disponibles', $prompt);
        $this->assertStringContainsString('Codes promotionnels et réductions (réservés aux formules Pro et Premium)', $prompt);
        $this->assertStringContainsString('Scripts vidéo TikTok / Instagram Reels', $prompt);
        $this->assertStringContainsString('Exports comptables PDF automatisés', $prompt);
        $this->assertStringContainsString('Contrôle des crédits', $prompt);
        $this->assertStringContainsString('Anti-Hallucination & Respect du forfait', $prompt);
        $this->assertStringContainsString('Confidentialité & Sécurité', $prompt);
    }

    public function test_orchestrator_system_prompt_unlocks_pro_features_on_pro_plan(): void
    {
        $proPlan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'monthly_price' => 15000,
                'max_products' => 300,
                'storage_mb' => 8192,
                'ai_credits' => 350,
                'full_generations' => 2,
                'active' => true,
            ]
        );

        Subscription::create([
            'shop_id' => $this->shop->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->shop->unsetRelation('subscription');

        $spyLlm = new class implements LlmProviderInterface {
            public string $capturedSystemPrompt = '';

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->capturedSystemPrompt = (string) ($messages[0]['content'] ?? '');

                return [
                    'content' => 'Vos outils Pro sont prêts.',
                    'tool_calls' => null,
                    'tokens_input' => 15,
                    'tokens_output' => 10,
                    'model_used' => 'test-model',
                    'raw' => [],
                ];
            }
        };

        $orchestrator = new AiOrchestrator($spyLlm);
        $result = $orchestrator->sendMessage($this->shop, $this->user, 'Quelles options sont actives ?');

        $this->assertEquals('success', $result['status']);

        $prompt = $spyLlm->capturedSystemPrompt;
        $this->assertStringContainsString('Forfait actuel : Pro (pro)', $prompt);
        $this->assertStringContainsString('Crédits IA restants : 350 / 350', $prompt);
        $this->assertMatchesRegularExpression('/^- Fonctionnalités réellement actives :[^\n]*Codes promotionnels et réductions \(\/codes-promo\)/m', $prompt);
        $this->assertMatchesRegularExpression('/^- Fonctionnalités réellement actives :[^\n]*Scripts vidéo TikTok \/ Instagram Reels/m', $prompt);
        $this->assertDoesNotMatchRegularExpression('/^- Fonctionnalités NON disponibles[^\n]*Codes promotionnels/m', $prompt);
        $this->assertMatchesRegularExpression('/^- Fonctionnalités NON disponibles[^\n]*Exports comptables PDF automatisés/m', $prompt);
        $this->assertMatchesRegularExpression('/^- Fonctionnalités NON disponibles[^\n]*Mode précommande automatique/m', $prompt);
    }

    public function test_short_greeting_cc_is_recognized_without_dumping_stock_alerts_or_preorder(): void
    {
        Product::withoutGlobalScopes()->create([
            'shop_id' => $this->shop->id,
            'title' => 'Mules à Talon Bordeaux',
            'slug' => 'mules-talon-bordeaux',
            'price' => 15000,
            'stock' => 0,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $spyLlm = new class implements LlmProviderInterface {
            public string $capturedSystemPrompt = '';

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->capturedSystemPrompt = (string) ($messages[0]['content'] ?? '');

                return [
                    'content' => 'Coucou ! Comment puis-je t’aider pour la boutique Boutique Test Thread aujourd’hui ?',
                    'tool_calls' => null,
                    'tokens_input' => 10,
                    'tokens_output' => 12,
                    'model_used' => 'test-model',
                    'raw' => [],
                ];
            }
        };

        $orchestrator = new AiOrchestrator($spyLlm);
        $result = $orchestrator->sendMessage($this->shop, $this->user, 'CC');

        $this->assertEquals('success', $result['status']);
        // Le nettoyeur ne doit pas couper "Coucou" quand l'utilisateur dit "CC"
        $this->assertStringStartsWith('Coucou !', $result['message']['content']);

        // Sur une salutation simple ("CC"), le prompt ne doit pas déverser les alertes de rupture de stock ni proposer de précommande
        $prompt = $spyLlm->capturedSystemPrompt;
        $this->assertStringContainsString('BAVARDAGE / SALUTATION SIMPLE', $prompt);
        $this->assertStringNotContainsString('DIRECTIVE DE RÉPONSE DIRECTE SANS PRÉAMBULE', $prompt);
        $this->assertStringNotContainsString('ALERTES COMMERCIALES PROACTIVES DU MOMENT', $prompt);
        $this->assertStringNotContainsString('Mules à Talon Bordeaux', $prompt);
        $this->assertStringNotContainsString('restock_or_preorder', $prompt);
    }

    public function test_multi_turn_history_is_passed_in_chronological_order_and_greetings_do_not_repeat(): void
    {
        $localService = new \App\Services\Ai\AiLocalIntelligenceService();
        $localLlm = new class($localService, $this->shop) implements LlmProviderInterface {
            public array $capturedMessages = [];

            public function __construct(
                protected \App\Services\Ai\AiLocalIntelligenceService $local,
                protected Shop $shop
            ) {}

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->capturedMessages = $messages;
                return $this->local->respond($messages, $this->shop);
            }
        };

        $orchestrator = new AiOrchestrator($localLlm);

        // Tour 1 : l'utilisateur envoie "cc"
        $turn1 = $orchestrator->sendMessage($this->shop, $this->user, 'cc');
        $convId = $turn1['conversation_id'];
        $reply1 = $turn1['message']['content'];

        $this->assertStringStartsWith('Coucou !', $reply1);
        $this->assertStringNotContainsString('Je vais très bien', $reply1);

        // Tour 2 : l'utilisateur envoie "bonjour" dans la même conversation (scénario exact de la capture d'écran)
        $turn2 = $orchestrator->sendMessage($this->shop, $this->user, 'bonjour', $convId);
        $reply2 = $turn2['message']['content'];

        $this->assertStringStartsWith('Bonjour !', $reply2);
        $this->assertStringNotContainsString('Je vais très bien', $reply2);
        $this->assertNotSame($reply1, $reply2, 'Le copilote ne doit jamais répéter mot pour mot la même phrase sur deux tours consécutifs.');

        // Tour 3 : l'utilisateur répond "1" pour suivre la proposition sur les stocks
        $turn3 = $orchestrator->sendMessage($this->shop, $this->user, '1', $convId);
        $reply3 = $turn3['message']['content'];

        $this->assertStringContainsString('État des Stocks', $reply3);

        // Vérifier que l'historique transmis au LLM est bien dans l'ordre chronologique (cc -> reply1 -> bonjour -> reply2 -> 1)
        $nonSystemRoles = array_values(array_filter(
            $localLlm->capturedMessages,
            fn($m) => ($m['role'] ?? '') !== 'system'
        ));
        $this->assertCount(5, $nonSystemRoles);
        $this->assertSame('cc', $nonSystemRoles[0]['content']);
        $this->assertSame($reply1, $nonSystemRoles[1]['content']);
        $this->assertSame('bonjour', $nonSystemRoles[2]['content']);
        $this->assertSame($reply2, $nonSystemRoles[3]['content']);
        $this->assertSame('1', $nonSystemRoles[4]['content']);
    }
}
