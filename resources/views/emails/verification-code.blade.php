<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#f4f4fb;font-family:Arial,Helvetica,sans-serif;color:#1d1d33">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e3f0">
    <h2 style="margin:0 0 12px;color:#6d3df2">{{ config('platform.name') }}</h2>
    <p>Bonjour {{ $name }},</p>
    <p>Voici votre code de vérification :</p>
    <p style="font-size:34px;letter-spacing:8px;font-weight:700;text-align:center;background:#f4f1ff;border-radius:12px;padding:16px;margin:20px 0">{{ $code }}</p>
    <p>Il est valable {{ $minutes }} minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>
</div>
</body>
</html>
