@extends('layouts.app')

@section('title', 'Vérifier mon email : '.config('platform.name'))

@section('content')
<div class="container auth-wrap">
    <div class="card auth-card">
        <h1>Vérifiez votre email</h1>
        <p class="sub">Nous avons envoyé un code à 6 chiffres à <strong>{{ $email }}</strong>. Il est valable 10 minutes.</p>

        @if (session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
        @if (session('dev_code'))
            <div class="alert" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); color: #fde68a; margin-bottom: 1.2rem; text-align: center; border-radius: 8px; padding: 0.85rem;">
                <div style="font-size: 0.85rem; font-weight: 600;">Code de vérification (Mode Test sans SMTP) :</div>
                <div style="font-size: 1.75rem; font-weight: 800; letter-spacing: 5px; color: #fff; margin: 0.35rem 0;">{{ session('dev_code') }}</div>
                <small style="color: var(--text-soft); font-size: 0.78rem;">Saisissez ce code ci-dessous pour continuer.</small>
            </div>
        @endif
        @error('code') <div class="alert alert-err" role="alert">{{ $message }}</div> @enderror

        <form method="POST" action="{{ route('verification.verify') }}" data-once>
            @csrf
            <input type="hidden" id="code" name="code">
            <div class="otp" data-otp>
                @for ($i = 0; $i < 6; $i++)
                    <input type="text" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" maxlength="1" aria-label="Chiffre {{ $i + 1 }}">
                @endfor
            </div>
            <div class="hint" style="margin-bottom:1.2rem">Collez le code ou saisissez-le : l'envoi est automatique.</div>
            <button id="verify-submit" type="submit" class="btn btn-primary btn-block">Valider</button>
        </form>

        <form method="POST" action="{{ route('verification.resend') }}" class="alt">
            @csrf
            <span style="color:var(--text-soft)">Pas reçu le code ?</span>
            <button id="resend-code" type="submit" class="btn btn-ghost btn-sm" style="margin-left:.5rem">Renvoyer</button>
        </form>
    </div>
</div>
@endsection
