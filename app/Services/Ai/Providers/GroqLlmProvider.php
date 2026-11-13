<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqLlmProvider implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;
    protected string $fallbackModel;

    public function __construct(?string $apiKey = null, ?string $defaultModel = null, ?string $fallbackModel = null)
    {
        $rawKey = $apiKey
            ?: (config('services.groq.key')
                ?: env('GROQ_API_KEY'));

        $this->apiKey = trim((string) $rawKey, " \t\n\r\0\x0B\"'");

        $this->defaultModel = $defaultModel
            ?: (config('services.groq.model')
                ?: env('GROQ_MODEL', 'openai/gpt-oss-120b'));

        $this->fallbackModel = $fallbackModel
            ?: (config('services.groq.fallback_model')
                ?: env('GROQ_FALLBACK_MODEL', 'llama-3.3-70b-versatile'));
    }

    /**
     * Alias de compatibilité pour les services appelant chatCompletion().
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        return $this->chat($messages, [], $options);
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = (float) ($options['temperature'] ?? 0.6);
        $maxTokens = (int) ($options['max_tokens'] ?? 1000);
        $timeout = (int) ($options['timeout'] ?? 20);

        $formattedMessages = $this->normalizeMessages($messages);
        $formattedTools = $this->normalizeTools($tools);

        // 1. Moteur Principal : API Groq LPU Ultra-Rapide
        if (! empty($this->apiKey)) {
            $result = $this->sendGroqRequest($model, $formattedMessages, $formattedTools, $temperature, $maxTokens, $timeout, $options);
            if ($result !== null) {
                return $result;
            }

            // Repli interne Groq sur le modèle secondaire (ex: llama-3.3-70b-versatile) si le modèle principal a échoué
            if ($model !== $this->fallbackModel) {
                Log::info("GroqLlmProvider: Bascule automatique du modèle {$model} vers {$this->fallbackModel}.");
                $fallbackResult = $this->sendGroqRequest($this->fallbackModel, $formattedMessages, $formattedTools, $temperature, $maxTokens, $timeout, $options);
                if ($fallbackResult !== null) {
                    return $fallbackResult;
                }
            }

            // Si l'erreur provient d'un schéma d'outil (tool_use_failed), réessayer sans outils sur Groq pour garantir une réponse
            if (! empty($formattedTools)) {
                Log::warning('GroqLlmProvider: Nouvel essai Groq sans outils (mode conversation directe).');
                $noToolResult = $this->sendGroqRequest($this->fallbackModel, $formattedMessages, [], $temperature, $maxTokens, $timeout, $options);
                if ($noToolResult !== null) {
                    return $noToolResult;
                }
            }
        } else {
            Log::warning('GroqLlmProvider: Clé GROQ_API_KEY absente, bascule vers le fournisseur de secours.');
        }

        // 2. Cascade de Secours : OpenRouter / Gemini / Moteur Métier Local
        $fallbackProvider = new OpenRouterLlmProvider();
        return $fallbackProvider->chat($formattedMessages, $tools, array_merge($options, ['skip_groq_primary' => true]));
    }

    /**
     * Exécute un appel HTTP vers https://api.groq.com/openai/v1/chat/completions
     */
    protected function sendGroqRequest(
        string $model,
        array $messages,
        array $tools,
        float $temperature,
        int $maxTokens,
        int $timeout,
        array $options = []
    ): ?array {
        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ];

            if (! empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            }

            if (! empty($options['response_format'])) {
                $payload['response_format'] = $options['response_format'];
            }

            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($timeout);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://api.groq.com/openai/v1/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];

                $content = $choice['content'] ?? null;
                $toolCalls = $choice['tool_calls'] ?? null;

                $usage = $data['usage'] ?? [];
                $tokensInput = (int) ($usage['prompt_tokens'] ?? 0);
                $tokensOutput = (int) ($usage['completion_tokens'] ?? 0);

                return [
                    'content' => $content,
                    'tool_calls' => $toolCalls,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'model_used' => 'groq:' . ($data['model'] ?? $model),
                    'raw' => $data,
                ];
            }

            Log::warning("Groq API error ({$model}) HTTP " . $response->status() . ': ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning("GroqLlmProvider exception ({$model}): " . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalise les messages pour respecter strictement le contrat OpenAI/Groq.
     */
    protected function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $item = ['role' => $role];

            if ($role === 'assistant' && ! empty($msg['tool_calls'])) {
                $item['content'] = isset($msg['content']) ? (string) $msg['content'] : '';
                $cleanCalls = [];
                foreach ($msg['tool_calls'] as $tc) {
                    $fn = $tc['function'] ?? [];
                    $args = $fn['arguments'] ?? '{}';
                    if (is_array($args)) {
                        $args = json_encode($args, JSON_UNESCAPED_UNICODE);
                    }
                    $cleanCalls[] = [
                        'id' => (string) ($tc['id'] ?? ('call_' . uniqid())),
                        'type' => 'function',
                        'function' => [
                            'name' => (string) ($fn['name'] ?? ''),
                            'arguments' => (string) $args,
                        ],
                    ];
                }
                $item['tool_calls'] = $cleanCalls;
            } elseif ($role === 'tool') {
                $item['tool_call_id'] = (string) ($msg['tool_call_id'] ?? '');
                if (! empty($msg['name'])) {
                    $item['name'] = (string) $msg['name'];
                }
                $content = $msg['content'] ?? '{}';
                $item['content'] = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE);
            } else {
                $item['content'] = $msg['content'] ?? '';
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Normalise le schéma des outils au format OpenAI Function Calling attendu par Groq.
     */
    protected function normalizeTools(array $tools): array
    {
        if (empty($tools)) {
            return [];
        }

        $formattedTools = [];
        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function') {
                $fn = $tool['function'] ?? [];
                if (isset($fn['parameters']['properties']) && is_array($fn['parameters']['properties']) && empty($fn['parameters']['properties'])) {
                    $fn['parameters']['properties'] = (object) [];
                }
                $formattedTools[] = [
                    'type' => 'function',
                    'function' => $fn,
                ];
            } elseif (isset($tool['name'])) {
                $params = $tool['parameters'] ?? ['type' => 'object', 'properties' => (object) []];
                if (isset($params['properties']) && is_array($params['properties']) && empty($params['properties'])) {
                    $params['properties'] = (object) [];
                }
                $formattedTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'] ?? '',
                        'parameters' => $params,
                    ],
                ];
            }
        }

        return $formattedTools;
    }
}
