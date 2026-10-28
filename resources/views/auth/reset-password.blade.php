@extends('layouts.app')

@section('title', 'Nouveau mot de passe : '.config('platform.name'))

@section('content')
<div class="container auth-wrap">
    <div class="card auth-card">
        <h1>Nouveau mot de passe</h1>
        <p class="sub">Choisissez un mot de passe d'au moins 8 caractères.</p>

        @error('email') <div class="alert alert-err" role="alert">{{ $message }} <a href="{{ route('password.request') }}">Nouvelle demande</a></div> @enderror

        <form method="POST" action="{{ route('password.update') }}" data-once>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ old('email', $email) }}">

            <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <div class="pw">
                    <input id="password" name="password" type="password" class="input @error('password') invalid @enderror" autocomplete="new-password" required autofocus>
                    <button type="button" data-toggle-pw="password" aria-label="Afficher le mot de passe"><x-icon name="eye" size="18" /></button>
                </div>
                <div class="meter"><i id="meter-bar"></i></div>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>

            <button id="reset-submit" type="submit" class="btn btn-primary btn-block">Modifier mon mot de passe</button>
        </form>
    </div>
</div>
@endsection
