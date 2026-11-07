<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function __construct(
        protected WebPushService $webPush
    ) {}

    /**
     * Retourne la clé publique VAPID pour le navigateur
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('services.webpush.public_key'),
        ]);
    }

    /**
     * Enregistre ou met à jour un abonnement Web Push pour l'utilisateur connecté
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:1000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aesgcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'ip_address' => $request->ip(),
                'device_label' => $this->detectDevice($request->userAgent()),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Appareil enregistré avec succès pour les notifications push.',
            'id' => $sub->id,
        ]);
    }

    /**
     * Supprime un abonnement Web Push (désactivation manuelle)
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        PushSubscription::where('endpoint', $request->input('endpoint'))->delete();

        return response()->json([
            'success' => true,
            'message' => 'Abonnement supprimé.',
        ]);
    }

    /**
     * Envoie une notification push de test immédiate à l'utilisateur connecté
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceCount = $user->pushSubscriptions()->count();

        if ($deviceCount === 0) {
            return response()->json([
                'success' => false,
                'device_count' => 0,
                'message' => 'Aucun appareil enregistré. Activez d\'abord les notifications push sur votre téléphone ou navigateur.',
            ], 422);
        }

        $sent = $this->webPush->sendNotification(
            $user,
            '🔔 Test Notification Zanika',
            'Vos notifications mobiles et web fonctionnent parfaitement ! Prêt pour recevoir les alertes.',
            $user->isAdmin() ? route('admin.audit') : route('dashboard'),
            [
                'tag' => 'test-push-' . time(),
                'vibrate' => [100, 50, 100],
            ]
        );

        return response()->json([
            'success' => $sent,
            'device_count' => $deviceCount,
            'message' => $sent
                ? "Notification envoyée avec succès sur vos {$deviceCount} appareil(s) !"
                : "Échec de l'envoi de la notification. Vérifiez la configuration VAPID.",
        ]);
    }

    /**
     * Détecte le type d'appareil à partir du User-Agent
     */
    protected function detectDevice(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Inconnu';
        }

        $ua = strtolower($userAgent);
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            return 'iOS Mobile';
        }
        if (str_contains($ua, 'android')) {
            return 'Android Mobile';
        }
        if (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) {
            return 'Mac Desktop';
        }
        if (str_contains($ua, 'windows')) {
            return 'Windows Desktop';
        }
        if (str_contains($ua, 'linux')) {
            return 'Linux Desktop';
        }

        return 'Navigateur Web';
    }
}
