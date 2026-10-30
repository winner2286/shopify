<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre essai gratuit Zanika expire bientôt</title>
</head>
<body style="margin: 0; padding: 32px 16px; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; margin: 0 auto;">
        <!-- En-tête de marque -->
        <tr>
            <td align="center" style="padding-bottom: 24px;">
                <div style="font-size: 28px; font-weight: 900; letter-spacing: -0.5px; color: #111827; text-decoration: none;">
                    ZANIKA<span style="color: #FF2D6D;">.</span>
                </div>
                <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af; margin-top: 4px;">
                    Commerce & Boutiques en Ligne
                </div>
            </td>
        </tr>

        <!-- Carte Principale -->
        <tr>
            <td style="background-color: #ffffff; border-radius: 16px; padding: 36px 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
                
                <!-- Badge d'alerte J-3 -->
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; text-align: center; margin-bottom: 24px;">
                    <span style="font-size: 14px; font-weight: 700; color: #b45309; display: inline-block;">
                        ⏳ ALERTE : Plus que {{ $daysRemaining <= 1 ? "1 jour" : "{$daysRemaining} jours" }} d'essai gratuit
                    </span>
                </div>

                <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 800; color: #111827; text-align: center; line-height: 1.3;">
                    Votre boutique « {{ $shop->name }} » se prépare pour la suite !
                </h1>
                
                <p style="margin: 0 0 14px; font-size: 15px; color: #374151;">
                    Bonjour <strong>{{ $user->name }}</strong>,
                </p>
                
                <p style="margin: 0 0 20px; font-size: 15px; color: #4b5563; line-height: 1.6;">
                    Votre période d'essai gratuit de 14 jours sur Zanika arrive à échéance le <strong>{{ $subscription->trial_ends_at ? $subscription->trial_ends_at->format('d/m/Y') : 'très bientôt' }}</strong>.
                </p>

                <!-- Récapitulatif de la boutique -->
                <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                    <div style="font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">
                        État actuel de votre boutique
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 14px;">
                        <tr>
                            <td style="padding: 4px 0; color: #6b7280;">Boutique en ligne :</td>
                            <td style="padding: 4px 0; font-weight: 700; color: #111827; text-align: right;">{{ $shop->name }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #6b7280;">Articles en catalogue :</td>
                            <td style="padding: 4px 0; font-weight: 700; color: #111827; text-align: right;">{{ $shop->products()->count() }} produit(s)</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #6b7280;">Lien public :</td>
                            <td style="padding: 4px 0; font-weight: 600; color: #FF2D6D; text-align: right;">
                                <a href="{{ url('/b/' . $shop->subdomain) }}" style="color: #FF2D6D; text-decoration: none;">/b/{{ $shop->subdomain }} ↗</a>
                            </td>
                        </tr>
                    </table>
                </div>

                <p style="margin: 0 0 20px; font-size: 15px; color: #374151; line-height: 1.6;">
                    Pour <strong>continuer à recevoir vos commandes clients sur WhatsApp</strong>, encaisser vos paiements par T-Money ou Flooz et utiliser les outils d'IA sans interruption, choisissez dès aujourd'hui votre formule adaptée :
                </p>

                <!-- Grille des formules -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
                    <tr>
                        <td style="padding: 10px; background: #fdf2f4; border: 1px solid #fecdd3; border-radius: 10px; text-align: center; width: 33%;">
                            <div style="font-size: 12px; font-weight: 700; color: #9f1239;">🥉 ESSENTIEL</div>
                            <div style="font-size: 16px; font-weight: 900; color: #111827; margin: 4px 0;">10 000 F</div>
                            <div style="font-size: 11px; color: #4b5563;">Jusqu'à 50 articles</div>
                        </td>
                        <td style="width: 10px;"></td>
                        <td style="padding: 10px; background: #f0fdf4; border: 2px solid #22c55e; border-radius: 10px; text-align: center; width: 33%; position: relative;">
                            <div style="font-size: 12px; font-weight: 800; color: #15803d;">⭐ PRO</div>
                            <div style="font-size: 16px; font-weight: 900; color: #111827; margin: 4px 0;">15 000 F</div>
                            <div style="font-size: 11px; color: #15803d; font-weight: 600;">Recommandé</div>
                        </td>
                        <td style="width: 10px;"></td>
                        <td style="padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; text-align: center; width: 33%;">
                            <div style="font-size: 12px; font-weight: 700; color: #475569;">👑 PREMIUM</div>
                            <div style="font-size: 16px; font-weight: 900; color: #111827; margin: 4px 0;">30 000 F</div>
                            <div style="font-size: 11px; color: #4b5563;">Multi-accès & VIP</div>
                        </td>
                    </tr>
                </table>

                <!-- Bouton d'action principal -->
                <div style="text-align: center; margin: 30px 0 24px;">
                    <a href="{{ route('shop.settings') }}" style="background: linear-gradient(135deg, #FF2D6D 0%, #E11D48 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 14px 28px; border-radius: 999px; display: inline-block; box-shadow: 0 4px 14px rgba(255, 45, 109, 0.4);">
                        Renouveler et activer ma formule maintenant →
                    </a>
                </div>

                <!-- Info paiement local & réassurance -->
                <div style="border-top: 1px solid #e5e7eb; padding-top: 18px; text-align: center;">
                    <div style="font-size: 13px; font-weight: 600; color: #4b5563; margin-bottom: 6px;">
                        💳 Règlements simples et instantanés :
                    </div>
                    <div style="font-size: 13px; color: #6b7280;">
                        T-Money (Togo) • Flooz (Moov) • Cartes Bancaires Visa & Mastercard
                    </div>
                    <p style="margin: 14px 0 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                        🛡️ <strong>Vos données sont protégées :</strong> Tous vos produits, photos et avis clients restent sauvegardés. Si vous ne renouvelez pas tout de suite, votre boutique sera simplement mise en pause temporaire sans aucune suppression.
                    </p>
                </div>
            </td>
        </tr>

        <!-- Pied de page -->
        <tr>
            <td align="center" style="padding-top: 24px;">
                <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                    Besoin d'aide ou d'un conseil ? Répondez simplement à cet email ou contactez l'équipe d'assistance Zanika sur WhatsApp au <strong>+228 90 00 00 00</strong>.<br>
                    © {{ date('Y') }} Zanika. Tous droits réservés.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
