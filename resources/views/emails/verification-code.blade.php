<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre code de vérification Zanika</title>
</head>
<body style="margin: 0; padding: 32px 16px; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 520px; margin: 0 auto;">
        <tr>
            <td align="center" style="padding-bottom: 24px;">
                <div style="font-size: 28px; font-weight: 900; letter-spacing: -0.5px; color: #111827; text-decoration: none;">
                    ZANIKA<span style="color: #FF2D6D;">.</span>
                </div>
                <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af; margin-top: 4px;">
                    Commerce & Boutiques
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #ffffff; border-radius: 16px; padding: 36px 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
                <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 800; color: #111827; text-align: center;">
                    Vérification de votre compte
                </h1>
                
                <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">
                    Bonjour <strong style="color: #111827;">{{ $name }}</strong>,
                </p>
                
                <p style="margin: 0 0 20px; font-size: 15px; color: #4b5563;">
                    Merci de rejoindre Zanika ! Pour sécuriser votre compte et finaliser la configuration de votre boutique, voici votre code de confirmation :
                </p>

                <!-- Code OTP Container -->
                <div style="background: #fdf2f4; border: 2px dashed #FF2D6D; border-radius: 12px; padding: 20px; text-align: center; margin: 28px 0;">
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #FF2D6D; margin-bottom: 6px;">
                        Votre code secret OTP
                    </div>
                    <div style="font-size: 42px; font-weight: 900; letter-spacing: 8px; color: #111827; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; margin: 4px 0 8px;">
                        {{ $code }}
                    </div>
                    <div style="font-size: 13px; color: #6b7280; font-weight: 600;">
                        ⏱️ Valable pendant <strong>{{ $minutes }} minutes</strong>
                    </div>
                </div>

                <div style="background-color: #f9fafb; border-left: 4px solid #FF2D6D; border-radius: 4px; padding: 14px 16px; margin-bottom: 24px;">
                    <p style="margin: 0; font-size: 13px; color: #4b5563; line-height: 1.5;">
                        🔒 <strong>Conseil de sécurité :</strong> Ne partagez ce code avec personne. L'équipe Zanika ne vous demandera jamais ce code par téléphone, WhatsApp ou SMS.
                    </p>
                </div>

                <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                    Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.
                </p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top: 24px;">
                <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                    © {{ date('Y') }} {{ config('platform.name', 'Zanika') }} — La plateforme e-commerce africaine nouvelle génération.<br>
                    Cotonou, Lomé, Abidjan & Dakar.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
