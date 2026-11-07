<?php

namespace App\Services;

use App\Mail\NewMerchantRegisteredMail;
use App\Mail\ShopLaunchedMail;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminNotificationService
{
    public function __construct(
        protected WebPushService $webPush
    ) {}

    /**
     * Récupère la liste des utilisateurs administrateurs configurés
     */
    protected function getAdminUsers()
    {
        $adminEmails = config('platform.super_admin_emails', ['kougnimag@gmail.com']);

        return User::where('role', User::ROLE_ADMIN)
            ->orWhereIn('email', $adminEmails)
            ->get();
    }

    /**
     * Récupère la liste des adresses email des super-admins
     */
    protected function getAdminEmails(): array
    {
        $emails = config('platform.super_admin_emails', ['kougnimag@gmail.com']);
        if (empty($emails)) {
            $emails = ['kougnimag@gmail.com'];
        }
        return array_unique(array_filter($emails));
    }

    /**
     * Notifie l'inscription d'un nouveau commerçant (Push Super-Admin + Email + Audit Alert)
     */
    public function notifyMerchantRegistered(User $user, ?Shop $shop = null): void
    {
        try {
            $shopName = $shop?->name ?: 'Non encore créée';
            $country = $user->country ?? 'Non spécifié';

            // 1. Créer une alerte dans l'Audit & Control Center
            SystemAlert::create([
                'type' => 'merchant_registered',
                'severity' => 'info',
                'title' => 'Nouveau commerçant : ' . $user->name,
                'message' => "Le commerçant {$user->name} ({$user->email}) s'est inscrit sur Zanika depuis le pays {$country}.",
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'country' => $country,
                    'phone' => $user->phone,
                    'registered_at' => now()->toIso8601String(),
                    'action_url' => route('admin.shops'),
                ],
            ]);

            // 2. Envoyer un email aux super-administrateurs
            foreach ($this->getAdminEmails() as $email) {
                try {
                    Mail::to($email)->send(new NewMerchantRegisteredMail($user, $shop));
                } catch (\Throwable $e) {
                    Log::warning("[AdminNotification] Échec envoi email admin nouveau commerçant à {$email}: " . $e->getMessage());
                }
            }

            // 3. Envoyer une notification Push Mobile native aux super-admins
            $adminUsers = $this->getAdminUsers();
            foreach ($adminUsers as $admin) {
                $this->webPush->sendNotification(
                    $admin,
                    '🚀 Nouveau commerçant inscrit !',
                    "{$user->name} vient de rejoindre Zanika ({$country}).",
                    route('admin.dashboard'),
                    [
                        'tag' => 'merchant-registered-' . $user->id,
                        'user_id' => $user->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error("[AdminNotification] Erreur globale lors de la notification commerçant: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Notifie la publication d'une boutique en ligne (Push Super-Admin + Email + Audit Alert)
     */
    public function notifyShopLaunched(Shop $shop): void
    {
        try {
            $owner = $shop->owner;
            $ownerName = $owner?->name ?: 'Commerçant';
            $ownerEmail = $owner?->email ?: 'N/A';
            $subdomain = $shop->subdomain;
            $storeUrl = route('storefront.home', ['subdomain' => $subdomain]);

            // 1. Créer une alerte dans l'Audit & Control Center
            SystemAlert::create([
                'type' => 'shop_created',
                'severity' => 'info',
                'title' => "Boutique en ligne lancée : {$shop->name}",
                'message' => "La boutique {$shop->name} ({$subdomain}.zanika.store) a été publiée avec succès par {$ownerName}.",
                'data' => [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'subdomain' => $subdomain,
                    'owner_id' => $owner?->id,
                    'owner_email' => $ownerEmail,
                    'published_at' => now()->toIso8601String(),
                    'action_url' => $storeUrl,
                ],
            ]);

            // 2. Envoyer un email aux super-administrateurs
            foreach ($this->getAdminEmails() as $email) {
                try {
                    Mail::to($email)->send(new ShopLaunchedMail($shop));
                } catch (\Throwable $e) {
                    Log::warning("[AdminNotification] Échec envoi email admin boutique lancée à {$email}: " . $e->getMessage());
                }
            }

            // 3. Envoyer une notification Push Mobile native aux super-admins
            $adminUsers = $this->getAdminUsers();
            foreach ($adminUsers as $admin) {
                $this->webPush->sendNotification(
                    $admin,
                    '🏪 Nouvelle boutique en ligne !',
                    "{$shop->name} ({$subdomain}) est désormais accessible au public.",
                    $storeUrl,
                    [
                        'tag' => 'shop-launched-' . $shop->id,
                        'shop_id' => $shop->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error("[AdminNotification] Erreur globale lors de la notification boutique en ligne: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Notifie le commerçant lors d'une nouvelle commande passée sur sa vitrine (Mobile Push avec son)
     */
    public function notifyMerchantOrder(Order $order): void
    {
        try {
            $shop = $order->shop;
            $merchant = $shop?->owner;

            if (! $merchant) {
                return;
            }

            $amountFormatted = number_format($order->total, 0, ',', ' ') . ' FCFA';
            $customerName = $order->customer_name ?: 'Un client';
            $orderUrl = route('orders.show', $order->id);

            // Notification Push au commerçant sur son téléphone/ordinateur
            $this->webPush->sendNotification(
                $merchant,
                '💰 Nouvelle Commande ! (' . $amountFormatted . ')',
                "{$customerName} vient de commander (#{$order->order_number}) pour {$amountFormatted}.",
                $orderUrl,
                [
                    'tag' => 'order-' . $order->id,
                    'order_id' => $order->id,
                    'sound' => '/sounds/chaching.mp3',
                    'vibrate' => [200, 100, 200, 100, 300],
                    'badge' => '/images/brand/icon-512.png',
                ]
            );

            // Audit léger dans la console si montant élevé (> 100 000 FCFA) ou commande suspecte
            if ($order->total >= 100000) {
                SystemAlert::create([
                    'type' => 'order_fraud',
                    'severity' => 'info',
                    'title' => "Commande importante : {$amountFormatted} (#{$order->order_number})",
                    'message' => "Nouvelle commande de {$amountFormatted} passée sur {$shop->name} par {$customerName}.",
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total' => $order->total,
                        'shop_id' => $shop->id,
                        'customer_phone' => $order->customer_phone,
                        'action_url' => route('admin.shops'),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("[AdminNotification] Erreur push commande commerçant: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Alerte technique ou faille de sécurité pour l'Audit & Control Center
     */
    public function notifySystemIssue(string $title, string $message, string $severity = 'warning', array $data = []): void
    {
        try {
            $data['action_url'] = route('admin.audit');

            SystemAlert::create([
                'type' => 'system_health',
                'severity' => in_array($severity, ['info', 'warning', 'critical']) ? $severity : 'warning',
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            if ($severity === 'critical') {
                $adminUsers = $this->getAdminUsers();
                foreach ($adminUsers as $admin) {
                    $this->webPush->sendNotification(
                        $admin,
                        '🚨 Alerte Critique Système Zanika',
                        $title . ' : ' . substr($message, 0, 120),
                        route('admin.audit'),
                        [
                            'tag' => 'system-issue-' . time(),
                            'severity' => 'critical',
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::error("[AdminNotification] Erreur alerte système: " . $e->getMessage());
        }
    }

    /**
     * Alerte proactive spécifique : Solde IA faible ou épuisé (Déclenche Push Mobile + Email Super-Admin)
     */
    public function notifyLowAiCredits(string $title, string $message): void
    {
        try {
            // Anti-spam : vérifier si une alerte similaire a déjà été émise dans les 6 dernières heures
            $recentAlert = SystemAlert::where('type', 'system_health')
                ->where('title', 'like', '%OpenRouter%')
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();

            if ($recentAlert) {
                return;
            }

            SystemAlert::create([
                'type' => 'system_health',
                'severity' => 'critical',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'action_url' => 'https://openrouter.ai/settings/credits',
                    'alerted_at' => now()->toIso8601String(),
                ],
            ]);

            // 1. Notification Push Mobile au Super-Admin
            $adminUsers = $this->getAdminUsers();
            foreach ($adminUsers as $admin) {
                $this->webPush->sendNotification(
                    $admin,
                    '🚨 Alerte Trésorerie IA Zanika',
                    $title . ' : ' . substr($message, 0, 110),
                    route('admin.audit'),
                    [
                        'tag' => 'ai-credits-low-' . date('YmdH'),
                        'severity' => 'critical',
                    ]
                );
            }

            // 2. Email direct au Super-Admin
            foreach ($this->getAdminEmails() as $email) {
                try {
                    Mail::raw($message . "\n\nPour recharger votre solde : https://openrouter.ai/settings/credits\nOu ajoutez une clé Google Gemini gratuite (GEMINI_API_KEY) dans vos variables d'environnement.", function ($m) use ($email, $title) {
                        $m->to($email)->subject("[Zanika Alerte Critique] " . $title);
                    });
                } catch (\Throwable $e) {
                    Log::warning("[AdminNotification] Échec envoi email alerte solde: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error("[AdminNotification] Erreur notification solde bas: " . $e->getMessage());
        }
    }
}

