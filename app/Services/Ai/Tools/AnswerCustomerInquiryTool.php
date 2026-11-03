<?php

namespace App\Services\Ai\Tools;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiCustomerService;
use App\Services\Ai\Contracts\AiToolInterface;

class AnswerCustomerInquiryTool implements AiToolInterface
{
    public function name(): string
    {
        return 'answer_customer_inquiry';
    }

    public function description(): string
    {
        return 'Prépare une réponse de service client professionnelle et bienveillante pour un acheteur (information produit, disponibilité du stock, suivi de colis, ou réclamation) prête à envoyer sur WhatsApp.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['question'],
            'properties' => [
                'question' => [
                    'type' => 'string',
                    'description' => 'La question, réclamation ou message envoyé par le client.',
                ],
                'customer_phone' => [
                    'type' => 'string',
                    'description' => 'Numéro de téléphone du client si disponible pour générer le lien WhatsApp direct.',
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $question = trim($arguments['question'] ?? '');
        $phone = $arguments['customer_phone'] ?? null;

        $service = app(AiCustomerService::class);
        return $service->answerCustomerInquiry($shop, $question, $phone);
    }
}
