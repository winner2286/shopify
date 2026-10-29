<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '🔐 '.$this->code.' est votre code de confirmation Zanika');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verification-code');
    }
}
