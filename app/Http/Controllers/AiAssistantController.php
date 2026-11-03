<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    /**
     * Affiche l'espace ECOM AI ASSISTANT pour le commerçant.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop) {
            abort(403, "Vous devez posséder une boutique pour accéder à l'assistant IA.");
        }

        $conversations = $shop->aiConversations()
            ->with(['latestMessage'])
            ->latest('updated_at')
            ->take(30)
            ->get();

        $activeConversationId = $request->query('c');
        $activeConversation = null;

        if ($activeConversationId) {
            $activeConversation = $shop->aiConversations()
                ->with(['messages'])
                ->find($activeConversationId);
        }

        // Si aucune conversation demandée spécifiquement, charger la plus récente avec messages
        if (! $activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
            $activeConversation->load('messages');
        }

        $aiCreditsMonthly = $shop->aiCreditsMonthly();
        $aiCreditsRemaining = $shop->aiCreditsRemaining();
        $aiCreditsUsed = $shop->aiCreditsUsedThisMonth();

        return view('ai.assistant', [
            'shop' => $shop,
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'aiCreditsMonthly' => $aiCreditsMonthly,
            'aiCreditsRemaining' => $aiCreditsRemaining,
            'aiCreditsUsed' => $aiCreditsUsed,
        ]);
    }

    /**
     * Récupère les messages d'une conversation spécifique en JSON (changement rapide sans rechargement).
     */
    public function showConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $shop = $request->user()->shop;
        if (! $shop || $conversation->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (AiMessage $m) {
                return [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'tool_name' => $m->tool_name,
                    'action_required' => $m->action_required,
                    'created_at' => $m->created_at->format('H:i'),
                ];
            });

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $messages,
        ]);
    }

    /**
     * Envoi d'un message à l'assistant IA avec Tool Calling orchestré.
     */
    public function sendMessage(Request $request, AiOrchestrator $orchestrator): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'conversation_id' => 'nullable|integer',
        ]);

        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $user = $request->user();
        $messageText = trim($request->input('message'));
        $conversationId = $request->input('conversation_id');

        $result = $orchestrator->sendMessage($shop, $user, $messageText, $conversationId);

        return response()->json($result);
    }

    /**
     * Approbation ou refus d'une action sensible proposée par l'IA.
     */
    public function approveAction(Request $request, AiOrchestrator $orchestrator): JsonResponse
    {
        $request->validate([
            'message_id' => 'required|integer',
            'approved' => 'required|boolean',
        ]);

        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $user = $request->user();
        $messageId = (int) $request->input('message_id');
        $approved = (bool) $request->input('approved');

        $result = $orchestrator->handleActionApproval($shop, $user, $messageId, $approved);

        return response()->json($result);
    }

    /**
     * Supprime une conversation.
     */
    public function destroyConversation(Request $request, AiConversation $conversation)
    {
        $shop = $request->user()->shop;
        if (! $shop || $conversation->shop_id !== $shop->id) {
            abort(403);
        }

        $conversation->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->route('ai.assistant')->with('success', 'Conversation supprimée.');
    }
}
