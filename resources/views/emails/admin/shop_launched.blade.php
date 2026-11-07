<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle boutique mise en ligne sur Zanika</title>
</head>
<body style="margin: 0; padding: 32px 16px; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; margin: 0 auto;">
        <!-- En-tête -->
        <tr>
            <td align="center" style="padding-bottom: 24px;">
                <div style="font-size: 28px; font-weight: 900; letter-spacing: -0.5px; color: #111827;">
                    ZANIKA<span style="color: #FF2D6D;">.</span>
                </div>
                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af; margin-top: 4px;">
                    Notification Super-Administration
                </div>
            </td>
        </tr>

        <!-- Carte Principale -->
        <tr>
            <td style="background-color: #ffffff; border-radius: 16px; padding: 36px 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
                <div style="background: rgba(124, 58, 237, 0.1); border: 1px solid #8b5cf6; border-radius: 8px; padding: 10px 16px; text-align: center; margin-bottom: 24px;">
                    <span style="font-size: 14px; font-weight: 800; color: #6d28d9;">
                        🚀 NOUVELLE BOUTIQUE PUBLIÉE EN LIGNE
                    </span>
                </div>

                <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 800; color: #111827; text-align: center;">
                    « {{ $shop->name }} » est officiellement en ligne !
                </h1>
                
                <p style="margin: 0 0 20px; font-size: 15px; color: #4b5563; text-align: center;">
                    Le commerçant <strong>{{ $merchant->name }}</strong> vient de terminer l'onboarding et sa vitrine est accessible au public.
                </p>

                <!-- Fiche Boutique -->
                <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                        Détails de la Boutique
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 14px;">
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Boutique :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $shop->name }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Marchand :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->name }} ({{ $merchant->email }})</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Sous-domaine :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #8b5cf6; text-align: right;">{{ $shop->subdomain }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Articles en vente :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $shop->products()->count() }} produit(s)</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Style visuel :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $shop->visual_style ?: 'Moderne' }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Boutons d'action -->
                <div style="text-align: center; margin-bottom: 16px;">
                    @if ($shop->subdomain)
                    <a href="{{ url('/b/' . $shop->subdomain) }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #10B981, #059669); color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 24px; border-radius: 99px; margin-right: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        👁️ Visiter la boutique en direct →
                    </a>
                    @endif
                    <a href="{{ route('admin.shops') }}" style="display: inline-block; background: #f3f4f6; color: #374151; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 20px; border-radius: 99px; border: 1px solid #d1d5db;">
                        Gérer dans le Cockpit
                    </a>
                </div>
            </td>
        </tr>

        <!-- Pied de page -->
        <tr>
            <td align="center" style="padding-top: 24px; font-size: 12px; color: #9ca3af;">
                Alerte de gestion automatique envoyée aux administrateurs de {{ config('platform.name', 'Zanika') }}.
            </td>
        </tr>
    </table>
</body>
</html>
