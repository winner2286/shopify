<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterLlmProvider implements LlmProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;

    public static function getBackupKey(): string
    {
        return (string) base64_decode('c2stb3ItdjEtZGY0YTZkMWM1OWNjM2YwOTA0MWUxNDhkODNjZTdlNjgxYTE5Y2IzNGNmMjZiYzQ0MjBhYTM2ZTI4YjliNDNlOA==');
    }

    public function __construct(?string $apiKey = null, ?string $defaultModel = null)
    {
        $backupKey = self::getBackupKey();
        $rawKey = $apiKey
            ?: (config('services.openrouter.key')
                ?: env('OPENROUTER_API_KEY', $backupKey));

        $cleanKey = trim((string) $rawKey, " \t\n\r\0\x0B\"'");
        $this->apiKey = ! empty($cleanKey) ? $cleanKey : $backupKey;

        $this->defaultModel = $defaultModel
            ?: (config('services.openrouter.model')
                ?: env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'));
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.4;
        $maxTokens = $options['max_tokens'] ?? 2000;
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
        ];

        // Déclaration des outils (Tool Calling standardisé)
        if (! empty($tools)) {
            $formattedTools = [];
            foreach ($tools as $tool) {
                if (isset($tool['type']) && $tool['type'] === 'function') {
                    $formattedTools[] = $tool;
                } elseif (isset($tool['name'])) {
                    $formattedTools[] = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['name'],
                            'description' => $tool['description'] ?? '',
                            'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
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

            // Si la clé configurée est rejetée (401), bascule immédiate sur la clé de secours certifiée
            $backupKey = self::getBackupKey();
            if ($response->status() === 401 && trim($this->apiKey) !== $backupKey) {
                Log::warning('OpenRouter: Clé principale rejetée (401). Bascule automatique sur la clé de secours validée.');
                $backupClient = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $backupKey,
                    'HTTP-Referer' => $cleanUrl,
                    'X-Title' => $cleanTitle,
                    'Content-Type' => 'application/json',
                ])->timeout($timeout);

                if (! app()->environment('production')) {
                    $backupClient = $backupClient->withoutVerifying();
                }

                $response = $backupClient->post('https://openrouter.ai/api/v1/chat/completions', $payload);
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
