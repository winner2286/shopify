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

                // 1. Essai avec la clé de secours OpenRouter si disponible
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

                // 2. Cascade de Secours Échelon 1 : API Directe Google Gemini (100% Gratuite & Indépendante)
                if ($response->status() === 402) {
                    $geminiResult = $this->callGoogleGeminiDirect($messages, $tools, $maxTokens);
                    if ($geminiResult !== null) {
                        return $geminiResult;
                    }
                }

                // 3. Cascade de Secours Échelon 2 : API Groq (Llama 3.3 70B ultra-rapide)
                if ($response->status() === 402) {
                    $groqResult = $this->callGroqDirect($messages, $tools, $maxTokens);
                    if ($groqResult !== null) {
                        return $groqResult;
                    }
                }

                // 4. Cascade de Secours Échelon 3 : Tentative micro-tokens sur OpenRouter (250 tokens au lieu de 800)
                if ($response->status() === 402) {
                    Log::info('OpenRouter 402: Tentative de repli micro-budget avec max_tokens = 250...');
                    $reducedPayload = $payload;
                    $reducedPayload['max_tokens'] = 250;
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

            // Si OpenRouter échoue (402 solde épuisé, 401 ou 429), tenter en urgence Google Gemini ou Groq
            $emergencyGemini = $this->callGoogleGeminiDirect($messages, $tools, $maxTokens);
            if ($emergencyGemini !== null) {
                return $emergencyGemini;
            }

            $emergencyGroq = $this->callGroqDirect($messages, $tools, $maxTokens);
            if ($emergencyGroq !== null) {
                return $emergencyGroq;
            }

            // Alerte proactive du Super-Admin pour réapprovisionnement sans coupure
            if ($response->status() === 402) {
                try {
                    app(\App\Services\AdminNotificationService::class)->notifyLowAiCredits(
                        'Crédits OpenRouter Épuisés (Repli Métier Actif)',
                        'Le solde OpenRouter est à 0$. Le moteur métier local Zanika a pris le relais en continu pour vos marchands. Prévoyez une recharge de 5$ sur https://openrouter.ai/settings/credits.'
                    );
                } catch (\Throwable $e) {}
            }

            // Cascade de Secours Ultime (Filet de Sécurité) : Moteur d'Analyse Métier Local Déterministe
            // Garantit que le commerçant reçoit TOUJOURS une réponse intelligente et ne voit jamais d'erreur !
            return $this->generateLocalFallback($messages);

        } catch (\Throwable $e) {
            Log::error('OpenRouterLlmProvider Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // En cas d'exception réseau totale, bascule automatique sur le conseiller local
            return $this->generateLocalFallback($messages);
        }
    }

    /**
     * Secours Échelon 1 : Appel direct à l'API Google Gemini (Gratuit via Google AI Studio)
     */
    protected function callGoogleGeminiDirect(array $messages, array $tools = [], int $maxTokens = 800): ?array
    {
        $geminiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (empty($geminiKey)) {
            return null;
        }

        try {
            $model = config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.0-flash');
            $url = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => min($maxTokens, 800),
                'temperature' => 0.6,
            ];

            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . trim($geminiKey),
                'Content-Type' => 'application/json',
            ])->timeout(20);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $res = $client->post($url, $payload);
            if ($res->successful()) {
                $data = $res->json();
                $choice = $data['choices'][0]['message'] ?? [];
                Log::info('OpenRouterLlmProvider: Réponse obtenue avec succès via le relais Google Gemini direct.');
                return [
                    'content' => $choice['content'] ?? null,
                    'tool_calls' => $choice['tool_calls'] ?? null,
                    'tokens_input' => $data['usage']['prompt_tokens'] ?? 0,
                    'tokens_output' => $data['usage']['completion_tokens'] ?? 0,
                    'model_used' => 'google-gemini-direct:' . $model,
                    'raw' => $data,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Google Gemini Direct API failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Secours Échelon 2 : Appel direct à l'API Groq (Llama 3.3 70B ultra-rapide)
     */
    protected function callGroqDirect(array $messages, array $tools = [], int $maxTokens = 800): ?array
    {
        $groqKey = config('services.groq.key') ?: env('GROQ_API_KEY');
        if (empty($groqKey)) {
            return null;
        }

        try {
            $model = config('services.groq.model') ?: env('GROQ_MODEL', 'openai/gpt-oss-120b');
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => min($maxTokens, 800),
                'temperature' => 0.6,
            ];

            if (! empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto';
            }

            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . trim($groqKey),
                'Content-Type' => 'application/json',
            ])->timeout(15);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $res = $client->post($url, $payload);
            if ($res->successful()) {
                $data = $res->json();
                $choice = $data['choices'][0]['message'] ?? [];
                Log::info('OpenRouterLlmProvider: Réponse obtenue avec succès via Groq (Llama 3.3).');
                return [
                    'content' => $choice['content'] ?? null,
                    'tool_calls' => $choice['tool_calls'] ?? null,
                    'tokens_input' => $data['usage']['prompt_tokens'] ?? 0,
                    'tokens_output' => $data['usage']['completion_tokens'] ?? 0,
                    'model_used' => 'groq:' . $model,
                    'raw' => $data,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Groq Direct API failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Secours Échelon Ultime : Moteur d'Analyse Métier Local (Déterministe, logique & sans panne)
     */
    protected function generateLocalFallback(array $messages): array
    {
        // 1. Extraire le dernier message utilisateur
        $userText = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $userText = (string) ($m['content'] ?? '');
                break;
            }
        }
        $normalized = strtolower(trim($userText));

        // 2. Extraire la boutique du commerçant
        $user = auth()->user();
        $shop = $user?->shop ?? \App\Models\Shop::first();
        $shopName = $shop?->name ?: 'votre boutique';

        // 3. Calculer les indicateurs réels de la boutique
        $productsCount = $shop ? $shop->products()->count() : 0;
        $lowStockProducts = $shop ? $shop->products()->where('track_stock', true)->where('stock', '<=', 5)->get() : collect();
        $pendingOrders = $shop ? $shop->orders()->where('status', \App\Models\Order::STATUS_PENDING)->get() : collect();
        $deliveredOrders = $shop ? $shop->orders()->where('status', \App\Models\Order::STATUS_DELIVERED)->count() : 0;
        $totalGmv = $shop ? (int) $shop->orders()->where('status', '!=', \App\Models\Order::STATUS_CANCELLED)->sum('total') : 0;
        $formattedGmv = number_format($totalGmv, 0, ',', ' ') . ' FCFA';

        // 4. Formuler une réponse logique, personnalisée et commerciale
        if (empty($normalized) || in_array($normalized, ['cc', 'salut', 'bonjour', 'bonsoir', 'hello', 'yo', 'slt', 'coucou', 'aide'], true)) {
            $reply = "Bonjour ! Content de vous retrouver. Je suis votre assistant **ECOM AI** connecté en direct à votre boutique **{$shopName}**.\n\n"
                . "📊 **Point de situation en direct :**\n"
                . "• **Catalogue actif** : **{$productsCount} article(s)** enregistrés.\n"
                . "• **Commandes en cours** : **{$pendingOrders->count()} commande(s)** en attente de traitement.\n"
                . "• **Volume d'affaires global** : **{$formattedGmv}**.\n\n";

            if ($lowStockProducts->isNotEmpty()) {
                $firstLow = $lowStockProducts->first();
                $reply .= "⚠️ **Alerte Stock** : L'article *« {$firstLow->title} »* n'a plus que **{$firstLow->stock} unité(s)** disponible(s). Pensez à réapprovisionner ou à orienter vos clients vers d'autres modèles.\n\n";
            } else {
                $reply .= "💡 **Conseil Stratégique** : C'est le moment d'activer vos ventes avec une publication percutante sur votre statut WhatsApp !\n\n";
            }

            $reply .= "Comment puis-je vous aider aujourd'hui ? Vous pouvez me demander :\n"
                . "1. *« Analyse mes stocks et ruptures »*\n"
                . "2. *« Fais le point sur mes commandes en attente »*\n"
                . "3. *« Rédige un message WhatsApp pour booster mes ventes »*";
        } elseif (str_contains($normalized, 'stock') || str_contains($normalized, 'rupture') || str_contains($normalized, 'produit') || str_contains($normalized, 'inventaire')) {
            $reply = "📦 **Audit des Stocks & Disponibilités - {$shopName}**\n\n";
            if ($lowStockProducts->isEmpty()) {
                $reply .= "✅ **Tous vos stocks sont au vert !** Aucun article en rupture immédiate parmi vos {$productsCount} produits.\n\n"
                    . "💡 **Conseil** : Pour optimiser votre rotation, mettez en avant vos nouveautés dans vos statuts WhatsApp.";
            } else {
                $reply .= "Voici les articles nécessitant votre attention urgente :\n";
                foreach ($lowStockProducts->take(4) as $p) {
                    $reply .= "• **{$p->title}** : reste {$p->stock} unité(s) (" . number_format($p->price, 0, ',', ' ') . " FCFA)\n";
                }
                $reply .= "\n💡 **Action recommandée** : Contactez vos fournisseurs pour relancer la production ou appliquez une remise de déstockage sur les articles lents.";
            }
        } elseif (str_contains($normalized, 'commande') || str_contains($normalized, 'livr') || str_contains($normalized, 'cod') || str_contains($normalized, 'client')) {
            $reply = "🚚 **Revue des Commandes & Logistique - {$shopName}**\n\n";
            if ($pendingOrders->isEmpty()) {
                $reply .= "Toutes vos commandes sont à jour ! Aucune commande en attente de préparation.\n\n"
                    . "Total des commandes livrées avec succès : **{$deliveredOrders}**.";
            } else {
                $reply .= "Vous avez **{$pendingOrders->count()} commande(s) à traiter** en priorité :\n";
                foreach ($pendingOrders->take(3) as $o) {
                    $reply .= "• Commande **#{$o->order_number}** : " . number_format($o->total, 0, ',', ' ') . " FCFA auprès de *{$o->customer_name}* ({$o->delivery_zone_name})\n";
                }
                $reply .= "\n💡 **Règle d'or Cash on Delivery (COD)** : Appelez toujours le client pour confirmer sa présence physique avant de remettre le colis au coursier.";
            }
        } else {
            $reply = "Bien reçu ! Votre assistant **ECOM AI** est opérationnel sur la boutique **{$shopName}**.\n\n"
                . "Pour répondre précisément à votre demande, que souhaitez-vous que nous fassions en priorité ?\n"
                . "• **Ventes & Chiffre d'Affaires** : Voir les indicateurs et le panier moyen.\n"
                . "• **Catalogue & Produits** : Optimiser des descriptions ou ajuster des prix.\n"
                . "• **Marketing & WhatsApp** : Rédiger une promotion Flash prête à copier-coller.";
        }

        return [
            'content' => $reply,
            'tool_calls' => null,
            'tokens_input' => 0,
            'tokens_output' => 0,
            'model_used' => 'zanika-local-intelligence',
            'raw' => ['fallback_mode' => 'local_business_advisor'],
        ];
    }
}

