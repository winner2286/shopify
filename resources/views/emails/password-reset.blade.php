<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe Zanika</title>
</head>
<body style="margin: 0; padding: 32px 16px; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 520px; margin: 0 auto;">
        <tr>
            <td align="center" style="padding-bottom: 24px;">
                <div style="font-size: 28px; font-weight: 900; letter-spacing: -0.5px; color: #111827;">
                    ZANIKA<span style="color: #FF2D6D;">.</span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #ffffff; border-radius: 16px; padding: 36px 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb;">
                <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 800; color: #111827; text-align: center;">
                    Réinitialiser votre mot de passe
                </h1>
                
                <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">
                    Bonjour <strong style="color: #111827;">{{ $name }}</strong>,
                </p>
                
                <p style="margin: 0 0 24px; font-size: 15px; color: #4b5563;">
                    Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe sécurisé :
                </p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $url }}" style="background-color: #FF2D6D; color: #ffffff; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 15px; text-decoration: none; display: inline-block;">
                        Choisir un nouveau mot de passe
                    </a>
                </div>

                <div style="background-color: #f9fafb; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.5;">
                        ⏱️ Ce lien est valable pendant <strong>{{ $minutes }} minutes</strong> et ne peut être utilisé qu'une seule fois.
                    </p>
                </div>

                <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                    Si vous n'avez pas demandé ce changement, ignorez cet email : votre mot de passe reste strictement inchangé.
                </p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top: 24px;">
                <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                    © {{ date('Y') }} {{ config('platform.name', 'Zanika') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
