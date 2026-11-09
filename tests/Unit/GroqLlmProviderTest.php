<?php

namespace Tests\Unit;

use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\GroqLlmProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqLlmProviderTest extends TestCase
{
    public function test_container_resolves_groq_provider_by_default(): void
    {
        config(['services.ai.provider' => 'groq']);
        $provider = app(LlmProviderInterface::class);

        $this->assertInstanceOf(GroqLlmProvider::class, $provider);
    }

    public function test_groq_provider_sends_chat_completion_and_returns_response(): void
    {
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-groq-test',
                'model' => 'openai/gpt-oss-120b',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Réponse ultra-rapide depuis Groq LPU !',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 42,
                    'completion_tokens' => 18,
                ],
            ], 200),
        ]);

        $provider = new GroqLlmProvider('gsk_test_key', 'openai/gpt-oss-120b', 'llama-3.3-70b-versatile');
        $result = $provider->chat([
            ['role' => 'user', 'content' => 'Bonjour Zanika AI'],
        ]);

        $this->assertSame('Réponse ultra-rapide depuis Groq LPU !', $result['content']);
        $this->assertSame('groq:openai/gpt-oss-120b', $result['model_used']);
        $this->assertSame(42, $result['tokens_input']);
        $this->assertSame(18, $result['tokens_output']);
    }

    public function test_groq_provider_automatically_falls_back_to_secondary_groq_model(): void
    {
        Http::fakeSequence('api.groq.com/openai/v1/chat/completions')
            ->push(['error' => ['message' => 'Model overloaded']], 429)
            ->push([
                'id' => 'chatcmpl-groq-fallback',
                'model' => 'llama-3.3-70b-versatile',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Réponse depuis le modèle de repli Llama 3.3 70B !',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 30,
                    'completion_tokens' => 12,
                ],
            ], 200);

        $provider = new GroqLlmProvider('gsk_test_key', 'openai/gpt-oss-120b', 'llama-3.3-70b-versatile');
        $result = $provider->chat([
            ['role' => 'user', 'content' => 'Analyse mes ventes'],
        ]);

        $this->assertSame('Réponse depuis le modèle de repli Llama 3.3 70B !', $result['content']);
        $this->assertSame('groq:llama-3.3-70b-versatile', $result['model_used']);
    }
}
