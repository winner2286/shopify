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
    protected AiRagService $rag;
    protected AiShopMemoryService $memory;
    protected AiProactiveAdvisorService $proactiveAdvisor;

    public function __construct(
        ?LlmProviderInterface $llm = null,
        ?ToolRegistry $tools = null,
        ?AiRagService $rag = null,
        ?AiShopMemoryService $memory = null,
        ?AiProactiveAdvisorService $proactiveAdvisor = null
    ) {
        $this->llm = $llm ?? new OpenRouterLlmProvider();
        $this->tools = $tools ?? new ToolRegistry();
        $this->rag = $rag ?? new AiRagService();
        $this->memory = $memory ?? new AiShopMemoryService();
        $this->proactiveAdvisor = $proactiveAdvisor ?? new AiProactiveAdvisorService();
    }

    /**
     * Traite un message utilisateur dans le cadre d'une conversation et orchestre le Tool Calling, le RAG et la mémoire.
     */
    public function sendMessage(Shop $shop, User $user, string $userMessage, ?int $conversationId = null, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. Vérification des crédits IA de la boutique (Illimité pour le Super-Admin)
        $isAdmin = $user->isAdmin();
        $remainingCredits = $isAdmin ? 9999 : $shop->aiCreditsRemaining();
        if (! $isAdmin && $remainingCredits <= 0) {
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

        // 4. Recherche de connaissances RAG pertinentes pour la question
        $ragChunks = $this->rag->search($userMessage, null, 3);

        // 5. Construction du System Prompt enrichi (RAG + Mémoire + Rôle)
        $systemPrompt = $this->buildSystemPrompt($shop, $userMessage, $ragChunks);

        // 6. Assemblage de l'historique des messages récents (12 derniers pour conserver du contexte)
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

        // 7. Premier appel LLM avec déclaration des outils
        $toolsSchema = $this->tools->getToolsSchema();
        $response = $this->llm->chat($messagesPayload, $toolsSchema, $options);

        $totalTokensInput = $response['tokens_input'] ?? 0;
        $totalTokensOutput = $response['tokens_output'] ?? 0;
        $modelUsed = $response['model_used'] ?? 'openrouter';

        // 8. Résolution du Tool Calling si le LLM le demande
        $actionRequired = null;

        if (! empty($response['tool_calls'])) {
            // Sauvegarder la décision d'appel d'outil
            AiMessage::create([
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
            $finalContent = $response['content'] ?? "Désolé, je n'ai pas pu générer de réponse.";
            $finalAssistantMsg = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $finalContent,
                'tokens_input' => $response['tokens_input'],
                'tokens_output' => $response['tokens_output'],
            ]);
        }

        // 9. Apprentissage continu : extraction automatique et mise à jour de la mémoire boutique
        try {
            $this->memory->extractAndSaveFacts($shop, $userMessage, $finalContent);
        } catch (\Throwable $e) {
            Log::warning("AiOrchestrator: échec extraction mémoire boutique : " . $e->getMessage());
        }

        // 10. Journalisation de la consommation dans ai_generations (pour décompte des crédits)
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        // Coût réel Gemini Flash / OpenRouter : ~0.15$ / 1M tokens entrée et ~0.60$ / 1M tokens sortie (1 USD ≈ 650 FCFA)
        $costInputUsd = ($totalTokensInput / 1000000) * 0.15;
        $costOutputUsd = ($totalTokensOutput / 1000000) * 0.60;
        $costEstimatedFcfa = max(1, (int) round(($costInputUsd + $costOutputUsd) * 650));

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
                'has_rag' => $ragChunks->isNotEmpty(),
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
        $args = array_merge([
            'product_id' => $action['product_id'] ?? null,
            'confirmed_by_user' => true,
        ], $action['changes'] ?? []);

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
     * Assemble le System Prompt enrichi avec le profil boutique, la mémoire long terme, les alertes proactives et les documents RAG.
     */
    protected function buildSystemPrompt(Shop $shop, string $userMessage, $ragChunks): string
    {
        $shopName = $shop->name ?: 'Votre boutique';
        $currency = $shop->currency ?: 'FCFA';
        $category = $shop->business_category ?: 'Commerce de détail';
        $subdomain = $shop->subdomain ? "{$shop->subdomain}.zanika.shop" : 'Non configuré';

        // 1. Récupération de la mémoire persistante de la boutique
        $memoryText = $this->memory->formatForPrompt($shop);

        // 2. Formatage des chunks de connaissances RAG
        $ragText = $this->rag->formatForPrompt($ragChunks);

        // 3. Détection des alertes proactives du moment
        $proactiveText = $this->proactiveAdvisor->formatForPrompt($shop);

        return <<<PROMPT
Tu es **ZANIKA AI**, le copilote de direction commerciale et opérationnelle intégré à la plateforme E-Commerce Zanika.
Tu travailles en binôme direct avec le commerçant propriétaire de la boutique **"{$shopName}"**.

IDENTITÉ & POSTURE RELATIONNELLE NATURELLE :
- Tu es un véritable collaborateur d'affaires professionnel, chaleureux, accessible et clairvoyant.
- Tu n'es PAS un chatbot robotique aux formules prévisibles ni un simple répondeur automatique.
- Tu es transparent sur le fait que tu es une intelligence artificielle spécialisée dans le commerce électronique, mais ta conversation est naturelle, fluide, intelligente et humaine.
- **Distingue le bavardage informel des demandes opérationnelles** :
  * Si le commerçant te salue simplement (« Bonjour », « Salut l'équipe », « Ça va ? »), réponds chaleureusement en 1 à 2 phrases naturelles et bienveillantes sans déverser de rapport technique.
  * Si la question touche à la gestion ou à la stratégie, apporte une réponse claire, structurée et orientée résultats.
- Fais preuve d'empathie sincère quand le commerçant rencontre un problème (rupture de stock, colis refusé, chiffre en baisse).
- Adapte la longueur de tes réponses : concis et direct pour les questions du quotidien (1 à 3 phrases), approfondi et bienveillant pour la stratégie.
- Si tu ne possèdes pas une information dans la base de données, admets-le avec simplicité plutôt que d'inventer.
- Pose une question ciblée de clarification lorsque la demande du commerçant est incomplète, au lieu de faire des suppositions hasardeuses.

INFORMATIONS TEMPS RÉEL SUR LA BOUTIQUE :
- Nom commercial : {$shopName}
- Secteur d'activité : {$category}
- Devise monétaire officielle : {$currency}
- URL publique vitrine : https://{$subdomain}
- Marché principal : Afrique de l'Ouest (Togo, Bénin, Côte d'Ivoire, Sénégal...), avec forte prévalence du Paiement à la Livraison (Cash on Delivery - COD) et du Mobile Money.

{$memoryText}

{$proactiveText}

{$ragText}

RÈGLES D'OR DU MÉTIER E-COMMERCE AFRIQUE :
1. **Spécificités Pays & Réalités Locales (sans amalgame)** :
   - **Togo** : Monnaie FCFA (XOF), paiements T-Money (Mixx by Yas) et Flooz, coursiers zemidjans sur Lomé et périphérie (Agoè, Baguida, Adidogomé).
   - **Bénin** : Monnaie FCFA (XOF), paiements MTN Mobile Money (*880#) et Moov Flooz, conducteurs kékénons/motos (Cotonou, Calavi, Porto-Novo).
   - **Côte d'Ivoire** : Monnaie FCFA (XOF), Wave (frais 1%), Orange Money, MTN MoMo, coursiers express Abidjan (Cocody, Yopougon, Marcory, Plateau).
   - **Sénégal** : Monnaie FCFA (XOF), Wave (ultra-dominant) et Orange Money, livraisons express Dakar (Plateau, Almadies, Pikine).
2. **Gestion du Cash on Delivery (COD)** : Recommande toujours le protocole d'appel ou message vocal WhatsApp dans les 30 minutes suivant la commande pour reconfirmer la présence du client avec le montant exact et un repère géographique clair.
3. **Persuasion & Négociation sur WhatsApp** : Sur WhatsApp, ne brade jamais la marge unitaire d'un produit face à une demande de rabais. Propose plutôt un pack avantageux (ex: 2 articles avec remise globale) ou offre la livraison locale en geste commercial valorisant.
4. **Diagnostic de Rentabilité Approfondi** :
   Quand un commerçant s'interroge sur ses marges ou ses bénéfices faibles (« Beaucoup de commandes mais peu de bénéfices ») :
   - Examine les données chiffrées via tes outils (`get_sales_analytics`, `get_weekly_sales_report`).
   - Analyse les 4 causes racines : frais de coursier non répercutés, taux de refus COD après expédition, marge unitaire trop basse, remises excessives.
   - Propose un plan d'action hiérarchisé et concret.
5. **Garde-fous d'Écriture** : Toute modification de données (prix, stock, promo) doit passer par l'outil dédié qui déclenche la demande de confirmation explicite du commerçant (`action_required`). Aucune action sensible n'est appliquée sans validation explicite.
PROMPT;
    }
}
