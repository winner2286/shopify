<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    protected ?WebPush $webPush = null;
    protected string $publicKey;
    protected string $privateKey;
    protected string $subject;

    public function __construct()
    {
        $this->publicKey = (string) (config('services.webpush.public_key') ?: env('VAPID_PUBLIC_KEY', ''));
        $this->privateKey = (string) (config('services.webpush.private_key') ?: env('VAPID_PRIVATE_KEY', ''));
        $this->subject = (string) (config('services.webpush.subject') ?: env('VAPID_SUBJECT', 'mailto:kougnimag@gmail.com'));

        if (! empty($this->publicKey) && ! empty($this->privateKey)) {
            try {
                $auth = [
                    'VAPID' => [
                        'subject' => $this->subject,
                        'publicKey' => $this->publicKey,
                        'privateKey' => $this->privateKey,
                    ],
                ];
                $this->webPush = new WebPush($auth);
                $this->webPush->setReuseVAPIDHeaders(true);
            } catch (\Throwable $e) {
                Log::error('WebPushService Initialization Error: ' . $e->getMessage());
            }
        }
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Envoie une notification push native avec signature directe (titre, message, URL, options)
     */
    public function sendNotification(User $user, string $title, string $body, ?string $url = null, array $options = []): int
    {
        return $this->sendToUser($user, array_merge([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: route('dashboard'),
        ], $options));
    }

    /**
     * Envoie une notification push native à tous les appareils enregistrés d'un utilisateur.
     */
    public function sendToUser(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Envoie une notification push native à tous les super-administrateurs de la plateforme.
     */
    public function sendToAdmins(array $payload): int
    {
        $superAdminEmails = config('platform.super_admin_emails', ['kougnimag@gmail.com', 'kougnima@icloud.com']);
        $adminUsers = User::where('role', User::ROLE_ADMIN)
            ->orWhereIn('email', $superAdminEmails)
            ->with('pushSubscriptions')
            ->get();

        $subscriptions = $adminUsers->flatMap->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Envoie à une collection de PushSubscription et nettoie les terminaux expirés (404/410 Gone).
     */
    public function sendToSubscriptions($subscriptions, array $payload): int
    {
        if (! $this->webPush) {
            Log::warning('WebPushService: Impossible d\'envoyer le push (Service non initialisé).');
            return 0;
        }

        $formattedPayload = json_encode([
            'title' => $payload['title'] ?? 'Zanika',
            'body' => $payload['body'] ?? '',
            'icon' => $payload['icon'] ?? asset('images/brand/icon-192.png'),
            'badge' => $payload['badge'] ?? asset('favicon-32x32.png'),
            'url' => $payload['url'] ?? route('dashboard'),
            'tag' => $payload['tag'] ?? ('zanika-' . uniqid()),
            'data' => [
                'url' => $payload['url'] ?? route('dashboard'),
                'timestamp' => now()->timestamp,
            ],
            'vibrate' => [200, 100, 200],
        ], JSON_UNESCAPED_UNICODE);

        $sentCount = 0;
        $staleIds = [];

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                ]);

                $this->webPush->queueNotification($subscription, $formattedPayload);
            } catch (\Throwable $e) {
                Log::warning('WebPushService Queue Error: ' . $e->getMessage());
            }
        }

        try {
            foreach ($this->webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if ($report->isSuccess()) {
                    $sentCount++;
                } else {
                    $response = $report->getResponse();
                    $statusCode = $response ? $response->getStatusCode() : 0;
                    Log::warning("WebPush Delivery Failed for {$endpoint}: HTTP {$statusCode} - {$report->getReason()}");

                    // 404 ou 410 = Terminal désinscrit ou notification refusée
                    if ($statusCode === 404 || $statusCode === 410) {
                        $staleSub = PushSubscription::where('endpoint', $endpoint)->first();
                        if ($staleSub) {
                            $staleIds[] = $staleSub->id;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WebPushService Flush Error: ' . $e->getMessage());
        }

        if (! empty($staleIds)) {
            PushSubscription::whereIn('id', $staleIds)->delete();
        }

        return $sentCount;
    }
}
