<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Shop;
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
}
