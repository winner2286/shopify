<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\Shop;
use Carbon\Carbon;

class AiOrderIntelligenceService
{
    /**
     * Analyse le niveau de risque d'une commande (particulièrement en Cash on Delivery - COD).
     */
    public function analyzeOrderRisk(Order $order): array
    {
        $shop = $order->shop;
        $isCod = $order->payment_method === Order::PAYMENT_CASH;
        $phone = trim($order->customer_phone);

        // 1. Analyse de l'historique du client avec ce numéro dans cette boutique
        $previousOrders = Order::withoutGlobalScopes()
            ->where('shop_id', $order->shop_id)
            ->where('customer_phone', $phone)
            ->where('id', '!=', $order->id)
            ->get();

        $totalPrevious = $previousOrders->count();
        $deliveredCount = $previousOrders->where('status', Order::STATUS_DELIVERED)->count();
        $cancelledCount = $previousOrders->where('status', Order::STATUS_CANCELLED)->count();

        $riskScore = 15; // Score de base modéré pour tout nouvel acheteur COD
        $riskFactors = [];
        $positivePoints = [];

        // Évaluation historique client
        if ($totalPrevious > 0) {
            $cancellationRate = round(($cancelledCount / $totalPrevious) * 100);
            if ($cancelledCount > 0 && $cancellationRate >= 40) {
                $riskScore += 40;
                $riskFactors[] = "Historique à risque : {$cancelledCount} commande(s) annulée(s) sur {$totalPrevious} ({$cancellationRate}% d'annulation).";
            } elseif ($deliveredCount >= 2) {
                $riskScore = max(0, $riskScore - 25);
                $positivePoints[] = "Client habitué et fiable : {$deliveredCount} commande(s) déjà livrée(s) avec succès.";
            }
        } else {
            $positivePoints[] = "Premier achat sur la boutique.";
        }

        // Évaluation du montant (Panier élevé sur paiement à la livraison)
        if ($isCod && $order->total >= 35000) {
            $riskScore += 20;
            $riskFactors[] = "Montant COD important (" . number_format($order->total, 0, ',', ' ') . " {$shop->currency}) : un acompte ou une validation téléphonique préalable est recommandée.";
        }

        // Évaluation de l'adresse de livraison
        $address = trim($order->delivery_address);
        if (strlen($address) < 12) {
            $riskScore += 15;
            $riskFactors[] = "Adresse de livraison sommaire ou imprécise (\"{$address}\") : risque d'échec pour le coursier.";
        } else {
            $positivePoints[] = "Adresse de livraison bien renseignée.";
        }

        // Évaluation du délai en attente
        if ($order->status === Order::STATUS_PENDING && $order->created_at->diffInHours(now()) >= 6) {
            $hours = (int) $order->created_at->diffInHours(now());
            $riskScore += 10;
            $riskFactors[] = "En attente depuis {$hours} heures : plus le délai augmente, plus le client risque d'acheter ailleurs ou d'annuler.";
        }

        // Détermination du palier de risque
        $riskScore = min(100, max(0, $riskScore));
        $riskLevel = match (true) {
            $riskScore <= 25 => 'faible',
            $riskScore <= 60 => 'modéré',
            default => 'élevé',
        };

        // Recommandations stratégiques concrètes
        $recommendedAction = match ($riskLevel) {
            'faible' => "Commande sûre. Vous pouvez valider et assigner le livreur sans attendre.",
            'modéré' => "Envoyez un message WhatsApp de confirmation de disponibilité avant de confier le colis au coursier.",
            default => "Risque élevé de refus à la livraison. Contactez le client par téléphone ou proposez un règlement partiel (frais de livraison) par Mobile Money.",
        };

        // Message WhatsApp pré-formaté sur-mesure
        $formattedTotal = number_format($order->total, 0, ',', ' ') . ' ' . $shop->currency;
        $shopName = $shop->name ?: 'Notre boutique';

        $waMessage = "Bonjour {$order->customer_name} ! 👋\n\nNous préparons votre commande *{$order->order_number}* passée sur *{$shopName}*.\n\n📦 *Articles* : {$order->items->count()} article(s)\n💵 *Montant à régler à la livraison* : *{$formattedTotal}*\n📍 *Lieu de livraison* : {$order->delivery_zone_name} ({$order->delivery_address})\n\nPour que notre livreur se mette en route, merci de nous confirmer votre disponibilité par un simple *\"OUI\"*. Merci pour votre confiance ! ✨";

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $waUrl = ! empty($cleanPhone)
            ? 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($waMessage)
            : null;

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'is_cod' => $isCod,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'positive_points' => $positivePoints,
            'recommended_action' => $recommendedAction,
            'suggested_whatsapp_message' => $waMessage,
            'whatsapp_click_url' => $waUrl,
            'customer_history' => [
                'total_orders' => $totalPrevious + 1,
                'delivered' => $deliveredCount,
                'cancelled' => $cancelledCount,
            ],
        ];
    }

    /**
     * Bilan global des commandes en attente et en cours d'acheminement COD pour la boutique.
     */
    public function getPendingCodDashboard(Shop $shop): array
    {
        $orders = Order::where('shop_id', $shop->id)
            ->where('payment_method', Order::PAYMENT_CASH)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED])
            ->with(['items'])
            ->latest()
            ->get();

        $pendingConfirmation = $orders->where('status', Order::STATUS_PENDING);
        $shippedInTransit = $orders->where('status', Order::STATUS_SHIPPED);

        $totalCashInField = $orders->sum('total');

        $analyzedOrders = $orders->map(fn($o) => $this->analyzeOrderRisk($o));

        $highRiskCount = $analyzedOrders->where('risk_level', 'élevé')->count();
        $moderateRiskCount = $analyzedOrders->where('risk_level', 'modéré')->count();
        $lowRiskCount = $analyzedOrders->where('risk_level', 'faible')->count();

        return [
            'total_active_cod_orders' => $orders->count(),
            'total_cash_to_collect_fcfa' => $totalCashInField,
            'currency' => $shop->currency,
            'pending_confirmation_count' => $pendingConfirmation->count(),
            'shipped_in_transit_count' => $shippedInTransit->count(),
            'risk_breakdown' => [
                'high_risk' => $highRiskCount,
                'moderate_risk' => $moderateRiskCount,
                'low_risk' => $lowRiskCount,
            ],
            'orders' => $analyzedOrders->values()->all(),
        ];
    }
}
