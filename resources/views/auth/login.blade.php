@extends('layouts.app')

@section('title', 'Connexion : '.config('platform.name'))

@section('content')
<div class="container auth-wrap">
    <div class="card auth-card">
        <h1>Bon retour</h1>
        <p class="sub">Connectez-vous pour gérer votre boutique.</p>

        @if (session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif
        @error('email') <div class="alert alert-err" role="alert">{{ $message }}</div> @enderror

        <form method="POST" action="{{ route('login') }}" data-once>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="input @error('email') invalid @enderror" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="pw">
                    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                    <button type="button" data-toggle-pw="password" aria-label="Afficher le mot de passe"><x-icon name="eye" size="18" /></button>
                </div>
            </div>

            <div class="row">
                <label class="check"><input type="checkbox" name="remember" value="1"><span>Rester connecté</span></label>
                <a id="forgot-link" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
            </div>

            <button id="login-submit" type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>

        <p class="alt">Pas encore de boutique ? <a href="{{ route('register') }}">Créer mon compte</a></p>
    </div>
</div>
@endsection
