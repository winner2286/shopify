<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#f4f4fb;font-family:Arial,Helvetica,sans-serif;color:#1d1d33">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e3f0">
    <h2 style="margin:0 0 12px;color:#6d3df2">{{ config('platform.name') }}</h2>
    <p>Bonjour {{ $name }},</p>
    <p>Suite à plusieurs tentatives de connexion échouées, l'accès à votre compte est bloqué pendant {{ $minutes }} minutes par sécurité.</p>
    <p>Si c'était vous, patientez puis réessayez ou utilisez « Mot de passe oublié ». Sinon, nous vous conseillons de changer votre mot de passe.</p>
</div>
</body>
</html>
