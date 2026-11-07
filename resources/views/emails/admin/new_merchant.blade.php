<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau commerçant inscrit sur Zanika</title>
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
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 10px 16px; text-align: center; margin-bottom: 24px;">
                    <span style="font-size: 14px; font-weight: 800; color: #047857;">
                        👤 NOUVEAU MARCHAND INSCRIT SUR LA PLATEFORME
                    </span>
                </div>

                <h1 style="margin: 0 0 16px; font-size: 21px; font-weight: 800; color: #111827; text-align: center;">
                    {{ $merchant->name }} a rejoint Zanika !
                </h1>
                
                <p style="margin: 0 0 20px; font-size: 15px; color: #4b5563; text-align: center;">
                    Un nouveau commerçant vient de créer son compte sur votre plateforme et démarre la configuration de sa boutique.
                </p>

                <!-- Fiche Marchand -->
                <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                        Coordonnées du Marchand
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 14px;">
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Nom complet :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->name }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Email :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->email }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Téléphone :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->phone ?: 'Non renseigné' }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Pays :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->country ?: 'TG' }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Date d'inscription :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #111827; text-align: right;">{{ $merchant->created_at ? $merchant->created_at->format('d/m/Y à H:i') : date('d/m/Y à H:i') }}</td>
                        </tr>
                        @if ($shop && $shop->name)
                        <tr>
                            <td style="padding: 6px 0; color: #6b7280;">Nom Boutique :</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #FF2D6D; text-align: right;">{{ $shop->name }}</td>
                        </tr>
                        @endif
                    </table>
                </div>

                <!-- Bouton d'action -->
                <div style="text-align: center; margin-bottom: 16px;">
                    <a href="{{ route('admin.shops') }}" style="display: inline-block; background: linear-gradient(135deg, #FF2D6D, #E11D48); color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 28px; border-radius: 99px; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35);">
                        Consulter dans le Cockpit Super-Admin →
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
