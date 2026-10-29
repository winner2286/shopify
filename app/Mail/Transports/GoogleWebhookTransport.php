<?php

namespace App\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class GoogleWebhookTransport extends AbstractTransport
{
    public function __construct(
        private string $webhookUrl,
        private string $token = 'ZanikaSecureMailToken2026'
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (empty($this->webhookUrl)) {
            throw new \RuntimeException('GOOGLE_MAIL_WEBHOOK_URL n\'est pas configurée.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        
        $toAddresses = array_map(fn($addr) => $addr->getAddress(), $email->getTo());
        $to = implode(', ', $toAddresses);
        $subject = $email->getSubject() ?? 'Message de Zanika';
        $html = $email->getHtmlBody() ?: nl2br(htmlspecialchars($email->getTextBody() ?? ''));
        $fromName = config('mail.from.name', 'Zanika');

        Log::info("Envoi email via Google Webhook vers: {$to} | Sujet: {$subject}");

        $response = Http::timeout(25)->post($this->webhookUrl, [
            'token' => $this->token,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'from_name' => $fromName,
        ]);

        if (! $response->successful()) {
            Log::error("Erreur HTTP Google Webhook [{$response->status()}]: " . $response->body());
            throw new \RuntimeException("Google Webhook HTTP {$response->status()}: " . $response->body());
        }

        $resJson = $response->json();
        if (is_array($resJson) && isset($resJson['success']) && $resJson['success'] === false) {
            $err = $resJson['error'] ?? 'Erreur inconnue renvoyée par Google Apps Script';
            Log::error("Erreur Google Apps Script: " . $err);
            throw new \RuntimeException("Erreur Google Apps Script: " . $err);
        }

        Log::info("✅ Email envoyé avec succès via Google Webhook vers: {$to}");
    }

    public function __toString(): string
    {
        return 'google_webhook';
    }
}
