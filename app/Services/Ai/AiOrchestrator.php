<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiGeneration;
use App\Models\AiMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\OpenRouterLlmProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiOrchestrator
{
    protected LlmProviderInterface $llm;
    protected ToolRegistry $tools;

    public function __construct(?LlmProviderInterface $llm = null, ?ToolRegistry $tools = null)
    {
        $this->llm = $llm ?? new OpenRouterLlmProvider();
        $this->tools = $tools ?? new ToolRegistry();
    }

    /**
     * Traite un message utilisateur dans le cadre d'une conversation et orchestre le Tool Calling.
     */
    public function sendMessage(Shop $shop, User $user, string $userMessage, ?int $conversationId = null, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. Vérification des crédits IA de la boutique
        $remainingCredits = $shop->aiCreditsRemaining();
        if ($remainingCredits <= 0) {
            return [
                'status' => 'quota_exceeded',
                'message' => "Votre forfait actuel ne dispose plus de crédits IA disponibles pour ce mois. Veuillez passer à un forfait supérieur pour continuer à utiliser votre assistant ECOM AI.",
                'remaining_credits' => 0,
            ];
        }

        // 2. Récupération ou création de la conversation
        $conversation = null;
        if ($conversationId) {
            $conversation = AiConversation::where('shop_id', $shop->id)->find($conversationId);
        }

        if (! $conversation) {
            $conversation = AiConversation::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'title' => Str::limit(trim($userMessage), 50, '...'),
                'last_message_at' => now(),
            ]);
        }

        // 3. Enregistrement du message utilisateur
        $userMsgRecord = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 4. Construction du System Prompt contextualisé pour la boutique
        $systemPrompt = $this->buildSystemPrompt($shop);

        // 5. Assemblage de l'historique des messages récents (12 derniers pour conserver du contexte)
        $history = $conversation->messages()
            ->where('id', '!=', $userMsgRecord->id)
            ->latest('id')
            ->take(12)
            ->get()
            ->reverse();

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $h) {
            $item = ['role' => $h->role];
            if ($h->content !== null) {
                $item['content'] = $h->content;
            }
            if (! empty($h->tool_calls)) {
                $item['tool_calls'] = $h->tool_calls;
            }
            if (! empty($h->tool_call_id)) {
                $item['tool_call_id'] = $h->tool_call_id;
            }
            if (! empty($h->tool_name)) {
                $item['name'] = $h->tool_name;
            }
            $messagesPayload[] = $item;
        }

        $messagesPayload[] = ['role' => 'user', 'content' => $userMessage];

        // 6. Premier appel LLM avec déclaration des outils
        $toolsSchema = $this->tools->getToolsSchema();
        $response = $this->llm->chat($messagesPayload, $toolsSchema, $options);

        $totalTokensInput = $response['tokens_input'] ?? 0;
        $totalTokensOutput = $response['tokens_output'] ?? 0;
        $modelUsed = $response['model_used'] ?? 'openrouter';

        // 7. Résolution du Tool Calling si le LLM le demande
        $actionRequired = null;

        if (! empty($response['tool_calls'])) {
            // Sauvegarder la décision d'appel d'outil
            $assistantToolMsg = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'] ?: null,
                'tool_calls' => $response['tool_calls'],
                'tokens_input' => $response['tokens_input'],
                'tokens_output' => $response['tokens_output'],
            ]);

            $messagesPayload[] = [
                'role' => 'assistant',
                'content' => $response['content'] ?: null,
                'tool_calls' => $response['tool_calls'],
            ];

            // Exécuter chaque outil demandé
            foreach ($response['tool_calls'] as $toolCall) {
                $function = $toolCall['function'] ?? [];
                $toolName = $function['name'] ?? '';
                $rawArgs = $function['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? json_decode($rawArgs, true) : (array) $rawArgs;
                $callId = $toolCall['id'] ?? ('call_' . uniqid());

                $toolInstance = $this->tools->get($toolName);
                $toolResult = $this->tools->execute($toolName, $shop, $args ?: [], $user);

                // Si l'outil signale une action sensible nécessitant confirmation
                if (($toolResult['status'] ?? '') === 'action_required') {
                    $actionRequired = $toolResult;
                }

                $toolOutputJson = json_encode($toolResult, JSON_UNESCAPED_UNICODE);

                AiMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => 'tool',
                    'content' => $toolOutputJson,
                    'tool_call_id' => $callId,
                    'tool_name' => $toolName,
                ]);

                $messagesPayload[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'name' => $toolName,
                    'content' => $toolOutputJson,
                ];
            }

            // Deuxième appel LLM pour générer la synthèse finale avec les données des outils
            $secondResponse = $this->llm->chat($messagesPayload, [], $options);

            $finalContent = $secondResponse['content'] ?? "J'ai traité votre demande à l'aide des outils de votre boutique.";
            $totalTokensInput += ($secondResponse['tokens_input'] ?? 0);
            $totalTokensOutput += ($secondResponse['tokens_output'] ?? 0);

            $finalAssistantMsg = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $finalContent,
                'tokens_input' => $secondResponse['tokens_input'] ?? 0,
                'tokens_output' => $secondResponse['tokens_output'] ?? 0,
                'action_required' => $actionRequired,
            ]);
        } else {
            // Réponse directe sans outil
            $finalAssistantMsg = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'] ?? "Désolé, je n'ai pas pu générer de réponse.",
                'tokens_input' => $response['tokens_input'],
                'tokens_output' => $response['tokens_output'],
            ]);
        }

        // 8. Journalisation de la consommation dans ai_generations (pour décompte des crédits)
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $costEstimatedFcfa = (int) ceil(($totalTokensInput * 0.0003 + $totalTokensOutput * 0.001) * 600); // barème FCFA indicatif

        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_assistant_chat',
            'step' => 'chat_turn',
            'model_used' => $modelUsed,
            'prompt_tokens' => $totalTokensInput,
            'completion_tokens' => $totalTokensOutput,
            'cost_estimated_fcfa' => $costEstimatedFcfa,
            'duration_ms' => $durationMs,
            'status' => 'success',
            'input_summary' => Str::limit($userMessage, 120),
            'output_data' => [
                'conversation_id' => $conversation->id,
                'has_tools' => ! empty($response['tool_calls']),
            ],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return [
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'message' => [
                'id' => $finalAssistantMsg->id,
                'role' => 'assistant',
                'content' => $finalAssistantMsg->content,
                'action_required' => $finalAssistantMsg->action_required,
                'created_at' => $finalAssistantMsg->created_at->format('H:i'),
            ],
            'remaining_credits' => $shop->aiCreditsRemaining(),
        ];
    }

    /**
     * Approuve ou refuse une action sensible demandée par l'IA.
     */
    public function handleActionApproval(Shop $shop, User $user, int $messageId, bool $approved): array
    {
        $message = AiMessage::find($messageId);
        if (! $message || ! $message->conversation || $message->conversation->shop_id !== $shop->id) {
            return ['status' => 'error', 'message' => 'Action non trouvée ou non autorisée.'];
        }

        $action = $message->action_required;
        if (empty($action) || ($action['status'] ?? '') !== 'action_required') {
            return ['status' => 'error', 'message' => 'Cette action n\'est plus en attente d\'approbation.'];
        }

        if (! $approved) {
            $action['status'] = 'rejected';
            $message->update(['action_required' => $action]);

            AiMessage::create([
                'ai_conversation_id' => $message->ai_conversation_id,
                'role' => 'assistant',
                'content' => "🚫 Modification annulée à votre demande. Aucune donnée n'a été altérée.",
            ]);

            return ['status' => 'success', 'message' => 'Action refusée.'];
        }

        // Exécution de l'action confirmée
        $toolName = $action['action_type'] ?? '';
        $args = [
            'product_id' => $action['product_id'] ?? null,
            'new_price' => $action['changes']['new_price'] ?? null,
            'new_stock' => $action['changes']['new_stock'] ?? null,
            'confirmed_by_user' => true,
        ];

        $result = $this->tools->execute($toolName, $shop, $args, $user);

        $action['status'] = 'approved';
        $action['execution_result'] = $result;
        $message->update(['action_required' => $action]);

        $summaryText = $result['summary'] ?? "Modification appliquée avec succès.";

        AiMessage::create([
            'ai_conversation_id' => $message->ai_conversation_id,
            'role' => 'assistant',
            'content' => "✅ **Modification confirmée et appliquée !**\n\n{$summaryText}",
        ]);

        return ['status' => 'success', 'message' => 'Action validée et exécutée.', 'result' => $result];
    }

    /**
     * Génère le System Prompt adapté au contexte temps réel de la boutique.
     */
    protected function buildSystemPrompt(Shop $shop): string
    {
        $shopName = $shop->name ?: 'Votre boutique';
        $currency = $shop->currency ?: 'FCFA';
        $category = $shop->business_category ?: 'Commerce de détail';
        $subdomain = $shop->subdomain ? "{$shop->subdomain}.zanika.shop" : 'Non configuré';

        return <<<PROMPT
Tu es **ECOM AI ASSISTANT**, le copilote de direction commerciale et opérationnelle propulsé par l'IA de la plateforme Zanika.
Tu travailles directement pour le commerçant gérant la boutique : "{$shopName}".

Informations sur la boutique :
- Nom commercial : {$shopName}
- Secteur d'activité : {$category}
- Devise monétaire : {$currency}
- URL de la boutique : {$subdomain}
- Marché cible : Afrique de l'Ouest (Togo, Bénin, Côte d'Ivoire, Sénégal...), avec forte prévalence du Paiement à la Livraison (COD - Cash on Delivery) et du Mobile Money (T-Money, Flooz).

RÈGLES D'OR DE COMPORTEMENT :
1. **Données Réelles Uniquement** : Tu as accès à des outils intégrés (`get_catalog_overview`, `search_products`, `get_orders_summary`, `get_order_details`, `get_sales_analytics`, `get_inventory_alerts`, `draft_customer_message`, `update_product_stock_or_price`). N'invente JAMAIS de faux chiffres, de fausses commandes ou de fausses ruptures de stocks. Quand on te pose une question sur les ventes, les stocks ou les commandes, invoque immédiatement l'outil adéquat.
2. **Pragmatisme E-Commerce** : Sois concis, direct, stratégique et axé sur les résultats (ventes, réduction des annulations COD, marge).
3. **Formatage Visuel & Lisible** : Utilise des puces markdown, des tableaux synthétiques et du gras pour mettre en avant les chiffres clés et les actions prioritaires.
4. **Langue** : Exprime-toi en français soigné, professionnel et engageant.
5. **Protection des Données Sensibles** : N'annule ni ne modifie jamais un produit ou une commande sans passer par l'outil dédié qui déclenchera la demande d'autorisation formelle du commerçant.
PROMPT;
    }
}
