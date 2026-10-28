@extends('layouts.app')

@section('title', 'Mot de passe oublié : '.config('platform.name'))

@section('content')
<div class="container auth-wrap">
    <div class="card auth-card">
        <h1>Mot de passe oublié</h1>
        <p class="sub">Saisissez votre email : nous vous enverrons un lien valable 30 minutes.</p>

        @if (session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif

        <form method="POST" action="{{ route('password.email') }}" data-once>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="input @error('email') invalid @enderror" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <button id="forgot-submit" type="submit" class="btn btn-primary btn-block">Envoyer le lien</button>
        </form>

        <p class="alt"><a href="{{ route('login') }}">← Retour à la connexion</a></p>
    </div>
</div>
@endsection
