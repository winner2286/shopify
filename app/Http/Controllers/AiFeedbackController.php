<?php

namespace App\Http\Controllers;

use App\Models\AiFeedback;
use App\Models\AiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiFeedbackController extends Controller
{
    /**
     * Enregistre l'évaluation d'une réponse de l'assistant IA par un commerçant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required|integer|exists:ai_messages,id',
            'rating' => 'required|string|in:helpful,incorrect,incomplete,irrelevant',
            'comment' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:60',
        ]);

        $user = Auth::user();
        $message = AiMessage::with('conversation.shop')->findOrFail($validated['message_id']);

        // Vérification de sécurité multi-tenancy : le message doit appartenir à la boutique de l'utilisateur (ou être admin)
        $shop = $user->shop ?? ($user->isAdmin() ? $message->conversation?->shop : null);

        if (! $shop || ($message->conversation && $message->conversation->shop_id !== $shop->id && ! $user->isAdmin())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Action non autorisée sur ce message.',
            ], 403);
        }

        // Création ou mise à jour de l'évaluation
        $feedback = AiFeedback::withoutGlobalScopes()->updateOrCreate(
            [
                'ai_message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'shop_id' => $shop->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'category' => $validated['category'] ?? null,
            ]
        );

        $thankMessage = $validated['rating'] === 'helpful'
            ? 'Merci ! Votre retour positif a été enregistré.'
            : 'Merci pour votre signalement. Notre équipe analyse ce retour pour enrichir la base de connaissances de votre assistant.';

        return response()->json([
            'status' => 'success',
            'message' => $thankMessage,
            'feedback_id' => $feedback->id,
            'rating' => $feedback->rating,
        ]);
    }
}
