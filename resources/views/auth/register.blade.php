@extends('layouts.app')

@section('title', 'Créer mon compte : '.config('platform.name'))

@section('content')
<div class="container auth-wrap">
    <div class="card auth-card">
        <h1>Créer ma boutique</h1>
        <p class="sub">Inscrivez-vous en 1 minute. Vous décrirez votre activité juste après.</p>

        <form method="POST" action="{{ route('register') }}" data-once novalidate>
            @csrf
            <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            <div class="field">
                <label for="name">Nom complet</label>
                <input id="name" name="name" type="text" class="input @error('name') invalid @enderror" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="input @error('email') invalid @enderror" value="{{ old('email', request('email')) }}" autocomplete="email" inputmode="email" required>
                @error('email')
                    <div class="error">{{ $message }} @if (session('login_hint')) <a href="{{ route('login') }}">Se connecter</a> @endif</div>
                @enderror
            </div>

            <div class="field">
                <label for="country">Pays</label>
                <select id="country" name="country" class="select">
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code === 'OTHER' ? 'XX' : $code }}" @selected(old('country', 'TG') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="pw">
                    <input id="password" name="password" type="password" class="input @error('password') invalid @enderror" autocomplete="new-password" required>
                    <button type="button" data-toggle-pw="password" aria-label="Afficher le mot de passe"><x-icon name="eye" size="18" /></button>
                </div>
                <div class="meter"><i id="meter-bar"></i></div>
                @error('password') <div class="error">{{ $message }}</div> @else <div class="hint">8 caractères minimum.</div> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <div class="pw">
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                    <button type="button" data-toggle-pw="password_confirmation" aria-label="Afficher le mot de passe"><x-icon name="eye" size="18" /></button>
                </div>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    <span>J'accepte les conditions d'utilisation et la politique de confidentialité.</span>
                </label>
                @error('terms') <div class="error">{{ $message }}</div> @enderror
            </div>

            <button id="register-submit" type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
        </form>

        <p class="alt">Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a></p>
    </div>
</div>
@endsection
