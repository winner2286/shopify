<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountLockedMail extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Alerte de sécurité : connexion bloquée');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-locked');
    }
}
