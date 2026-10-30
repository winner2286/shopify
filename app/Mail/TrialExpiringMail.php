<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TrialExpiringMail extends Mailable
{
    use Queueable;

    public function __construct(
        public User $user,
        public Shop $shop,
        public Subscription $subscription,
        public int $daysRemaining = 3
    ) {}

    public function envelope(): Envelope
    {
        $daysText = $this->daysRemaining <= 1 ? "1 jour" : "{$this->daysRemaining} jours";
        return new Envelope(
            subject: "⚠️ Il ne vous reste que {$daysText} d'essai gratuit sur Zanika !"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-expiring'
        );
    }
}
