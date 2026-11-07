<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewMerchantRegisteredMail extends Mailable
{
    use Queueable;

    public function __construct(
        public User $merchant,
        public ?Shop $shop = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "👤 Nouveau commerçant inscrit sur Zanika : {$this->merchant->name}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new_merchant'
        );
    }
}
