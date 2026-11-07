<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ShopLaunchedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public Shop $shop,
        public User $merchant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚀 Nouvelle boutique en ligne sur Zanika : « {$this->shop->name} »"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.shop_launched'
        );
    }
}
