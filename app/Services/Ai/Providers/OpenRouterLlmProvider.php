<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterLlmProvider implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;

    protected ?string $backupKey;

    public function __construct(?string $apiKey = null, ?string $defaultModel = null, ?string $backupKey = null)
    {
        $rawKey = $apiKey
            ?: (config('services.openrouter.key')
                ?: env('OPENROUTER_API_KEY'));

        $this->apiKey = trim((string) $rawKey, " \t\n\r\0\x0B\"'");

        $rawBackup = $backupKey
            ?: (config('services.openrouter.backup_key')
                ?: env('OPENROUTER_BACKUP_API_KEY'));
        $this->backupKey = ! empty($rawBackup) ? trim((string) $rawBackup, " \t\n\r\0\x0B\"'") : null;

        $this->defaultModel = $defaultModel
            ?: (config('services.openrouter.model')
                ?: env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'));
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.6;
        $maxTokens = $options['max_tokens'] ?? 800;
        $timeout = $options['timeout'] ?? 30;

        if (empty($this->apiKey)) {
            Log::error('OpenRouterLlmProvider: Clé API manquante.');
            return [
                'content' => "Désolé, le service IA n'est pas configuré (clé d'accès manquante). Contactez le support.",
                'tool_calls' => null,
                'tokens_input' => 0,
                'tokens_output' => 0,
                'model_used' => $model,
                'raw' => [],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'frequency_penalty' => $options['frequency_penalty'] ?? 0.3,
            'presence_penalty' => $options['presence_penalty'] ?? 0.2,
        ];

        // Déclaration des outils (Tool Calling standardisé)
        if (! empty($tools)) {
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
            if (! empty($formattedTools)) {
                $payload['tools'] = $formattedTools;
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            }
        }

        try {
            $rawUrl = config('app.url', 'https://zanika.shop');
            $cleanUrl = trim(preg_replace('/[\r\n\t]+/', '', (string) $rawUrl));
            if (empty($cleanUrl) || ! str_starts_with($cleanUrl, 'http')) {
                $cleanUrl = 'https://zanika.shop';
            }
            $cleanTitle = trim(preg_replace('/[\r\n\t]+/', '', (string) config('platform.name', 'Zanika'))) . ' AI Assistant';

            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . trim($this->apiKey),
                'HTTP-Referer' => $cleanUrl,
                'X-Title' => $cleanTitle,
                'Content-Type' => 'application/json',
            ])->timeout($timeout);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            // Si la clé configurée est rejetée (401), bascule immédiate sur la clé de secours si configurée
            if ($response->status() === 401 && ! empty($this->backupKey) && trim($this->apiKey) !== $this->backupKey) {
                Log::warning('OpenRouter: Clé principale rejetée (401). Bascule automatique sur la clé de secours configurée.');
                $backupClient = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->backupKey,
                    'HTTP-Referer' => $cleanUrl,
                    'X-Title' => $cleanTitle,
                    'Content-Type' => 'application/json',
                ])->timeout($timeout);

                if (! app()->environment('production')) {
                    $backupClient = $backupClient->withoutVerifying();
                }

                $response = $backupClient->post('https://openrouter.ai/api/v1/chat/completions', $payload);
            }

            // Si OpenRouter renvoie 402 (Payment Required / balance faible / réservation de tokens dépassée)
            if ($response->status() === 402) {
                Log::warning('OpenRouter API returned 402: ' . $response->body());

                // 1. Essai avec la clé de secours si disponible
                if (! empty($this->backupKey) && trim($this->apiKey) !== $this->backupKey) {
                    Log::info('OpenRouter 402: Tentative avec la clé de secours...');
                    $backupClient = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->backupKey,
                        'HTTP-Referer' => $cleanUrl,
                        'X-Title' => $cleanTitle,
                        'Content-Type' => 'application/json',
                    ])->timeout($timeout);

                    if (! app()->environment('production')) {
                        $backupClient = $backupClient->withoutVerifying();
                    }

                    $response = $backupClient->post('https://openrouter.ai/api/v1/chat/completions', $payload);
                }

                // 2. Si toujours 402, tentative d'ajustement automatique avec max_tokens réduit (ex: 500 tokens)
                if ($response->status() === 402 && ($payload['max_tokens'] ?? 0) > 500) {
                    Log::info('OpenRouter 402: Tentative de repli avec max_tokens réduit à 500...');
                    $reducedPayload = $payload;
                    $reducedPayload['max_tokens'] = 500;
                    $response = $client->post('https://openrouter.ai/api/v1/chat/completions', $reducedPayload);
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];

                $content = $choice['content'] ?? null;
                $toolCalls = $choice['tool_calls'] ?? null;

                $usage = $data['usage'] ?? [];
                $tokensInput = $usage['prompt_tokens'] ?? 0;
                $tokensOutput = $usage['completion_tokens'] ?? 0;

                return [
                    'content' => $content,
                    'tool_calls' => $toolCalls,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'model_used' => $data['model'] ?? $model,
                    'raw' => $data,
                ];
            }

            Log::warning('OpenRouter API returned error ' . $response->status() . ': ' . $response->body());

            if ($response->status() === 402) {
                return [
                    'content' => "⚠️ **Crédits OpenRouter épuisés** : Le solde de crédits de votre compte OpenRouter est insuffisant pour traiter cette requête (Code HTTP 402 : Payment Required). Veuillez recharger votre compte sur [OpenRouter](https://openrouter.ai/settings/credits) ou mettre à jour votre clé API.",
                    'tool_calls' => null,
                    'tokens_input' => 0,
                    'tokens_output' => 0,
                    'model_used' => $model,
                    'raw' => ['error' => $response->body()],
                ];
            }

            if ($response->status() === 401) {
                return [
                    'content' => "⚠️ **Clé OpenRouter invalide ou révoquée** (Code HTTP 401 : Unauthorized). Veuillez vérifier votre clé API dans la configuration.",
                    'tool_calls' => null,
                    'tokens_input' => 0,
                    'tokens_output' => 0,
                    'model_used' => $model,
                    'raw' => ['error' => $response->body()],
                ];
            }

            return [
                'content' => "Une erreur temporaire est survenue lors de la communication avec le modèle IA (Code HTTP " . $response->status() . "). Veuillez réessayer.",
                'tool_calls' => null,
                'tokens_input' => 0,
                'tokens_output' => 0,
                'model_used' => $model,
                'raw' => ['error' => $response->body()],
            ];

        } catch (\Throwable $e) {
            Log::error('OpenRouterLlmProvider Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'content' => "Le service IA a mis trop de temps à répondre ou est momentanément indisponible. Erreur : " . $e->getMessage(),
                'tool_calls' => null,
                'tokens_input' => 0,
                'tokens_output' => 0,
                'model_used' => $model,
                'raw' => ['error' => $e->getMessage()],
            ];
        }
    }
}
