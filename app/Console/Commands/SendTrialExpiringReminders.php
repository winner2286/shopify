<?php

namespace App\Console\Commands;

use App\Mail\TrialExpiringMail;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTrialExpiringReminders extends Command
{
    /**
     * Nom et signature de la commande console
     */
    protected $signature = 'subscriptions:send-trial-reminders 
                            {--force : Envoyer l\'email même si un rappel a déjà été transmis}
                            {--shop= : ID spécifique de boutique pour tester}';

    /**
     * Description de la commande console
     */
    protected $description = 'Envoie un email de rappel aux marchands dont l\'essai gratuit expire dans 3 jours (J-3) pour les inviter à renouveler';

    public function handle(): int
    {
        $this->info("=== Vérification des abonnements en essai arrivant à expiration (J-3) ===");

        $query = Subscription::query()
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays(3));

        if (! $this->option('force')) {
            $query->whereNull('trial_reminder_sent_at');
        }

        if ($shopId = $this->option('shop')) {
            $query->where('shop_id', $shopId);
        }

        $subscriptions = $query->with(['shop.owner'])->get();

        if ($subscriptions->isEmpty()) {
            $this->line("Aucun abonnement en essai gratuit arrivant à expiration dans les 3 prochains jours.");
            return 0;
        }

        $count = 0;
        foreach ($subscriptions as $sub) {
            $shop = $sub->shop;
            $user = $shop?->owner;

            if (! $shop || ! $user || empty($user->email)) {
                continue;
            }

            $daysRemaining = max(1, (int) ceil(now()->diffInHours($sub->trial_ends_at, false) / 24));

            try {
                Mail::to($user->email)->send(new TrialExpiringMail($user, $shop, $sub, $daysRemaining));

                $sub->update([
                    'trial_reminder_sent_at' => now(),
                ]);

                $count++;
                $msg = "✓ Email de rappel J-{$daysRemaining} envoyé à {$user->email} (Boutique : {$shop->name})";
                $this->info($msg);
                Log::info("[TrialExpiring] " . $msg);
            } catch (\Throwable $e) {
                $err = "✗ Échec d'envoi à {$user->email} : " . $e->getMessage();
                $this->error($err);
                Log::error("[TrialExpiring] " . $err);
            }
        }

        $this->info("Rappels envoyés avec succès : {$count} commerçant(s) notifié(s).");
        return 0;
    }
}
