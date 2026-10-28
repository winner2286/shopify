<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre mot de passe');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
