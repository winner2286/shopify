<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#f4f4fb;font-family:Arial,Helvetica,sans-serif;color:#1d1d33">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e3f0">
    <h2 style="margin:0 0 12px;color:#6d3df2">{{ config('platform.name') }}</h2>
    <p>Bonjour {{ $name }},</p>
    <p>Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable {{ $minutes }} minutes et ne peut être utilisé qu'une fois.</p>
    <p style="text-align:center;margin:24px 0"><a href="{{ $url }}" style="background:#6d3df2;color:#fff;padding:14px 26px;border-radius:12px;text-decoration:none;font-weight:700">Choisir un nouveau mot de passe</a></p>
    <p style="font-size:13px;color:#777">Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.</p>
</div>
</body>
</html>
