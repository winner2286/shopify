@extends('layouts.app')

@section('title', 'Limite de boutiques atteinte — '.config('platform.name'))

@section('content')
<div class="container" style="max-width:620px;margin:3rem auto 4rem auto;padding:0 1rem">
    
    <div style="margin-bottom:1.5rem">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft);padding-left:0">
            <x-icon name="arrow-left" size="14" />
            <span>Retour au tableau de bord</span>
        </a>
    </div>

    <div class="card" style="padding:2.5rem 2rem;border-radius:18px;text-align:center;box-shadow:0 12px 35px rgba(0,0,0,0.15)">
        
        <!-- Icône monochrome Zanika -->
        <div style="width:64px;height:64px;border-radius:16px;background:color-mix(in srgb, var(--brand-1, #9e1b32) 15%, transparent);color:var(--brand-3, #c5223d);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem auto">
            <x-icon name="shop" size="32" />
        </div>

        <h1 style="font-size:1.6rem;font-weight:800;margin:0 0 .75rem 0;letter-spacing:-0.02em">
            Limite de boutiques atteinte
        </h1>

        <p style="font-size:.95rem;color:var(--text-soft);line-height:1.6;margin:0 0 2rem 0">
            Votre forfait actuel <strong>{{ $currentPlan->name }}</strong> vous autorise à gérer jusqu'à <strong>{{ $maxShops }} boutique{{ $maxShops > 1 ? 's' : '' }}</strong>. Vous avez actuellement <strong>{{ $shopsCount }} boutique{{ $shopsCount > 1 ? 's' : '' }}</strong> active{{ $shopsCount > 1 ? 's' : '' }}.
        </p>

        <!-- Grille des forfaits supérieurs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;margin-bottom:2rem;text-align:left">
            @if ($currentPlan->slug === 'essentiel')
                <div style="padding:1.25rem;border-radius:14px;background:var(--bg-soft);border:2px solid var(--brand-1, #9e1b32);position:relative">
                    <span class="badge" style="background:var(--brand-1, #9e1b32);color:#fff;font-size:.7rem;margin-bottom:.5rem">RECOMMANDÉ</span>
                    <div style="font-weight:800;font-size:1.1rem;color:var(--text)">Forfait Pro</div>
                    <div style="font-size:1.25rem;font-weight:800;color:var(--brand-3, #c5223d);margin:.25rem 0 .5rem 0">
                        15 000 FCFA <span style="font-size:.8rem;font-weight:500;color:var(--text-soft)">/ mois</span>
                    </div>
                    <ul style="font-size:.84rem;color:var(--text-soft);margin:0;padding-left:1rem;line-height:1.6">
                        <li><strong>Jusqu'à 3 boutiques distinctes</strong></li>
                        <li>300 produits par boutique</li>
                        <li>Scripts TikTok & Visuels IA</li>
                        <li>Support prioritaire WhatsApp</li>
                    </ul>
                    <a href="{{ route('subscription.checkout', 'pro') }}" class="btn btn-primary btn-sm btn-block" style="margin-top:1rem;justify-content:center">
                        Passer à Pro (3 boutiques) →
                    </a>
                </div>
            @endif

            <div style="padding:1.25rem;border-radius:14px;background:var(--bg-soft);border:1px solid var(--border)">
                <span class="badge" style="font-size:.7rem;margin-bottom:.5rem">PREMIUM</span>
                <div style="font-weight:800;font-size:1.1rem;color:var(--text)">Forfait Premium</div>
                <div style="font-size:1.25rem;font-weight:800;color:var(--text);margin:.25rem 0 .5rem 0">
                    30 000 FCFA <span style="font-size:.8rem;font-weight:500;color:var(--text-soft)">/ mois</span>
                </div>
                <ul style="font-size:.84rem;color:var(--text-soft);margin:0;padding-left:1rem;line-height:1.6">
                    <li><strong>Boutiques illimitées</strong></li>
                    <li>2 500 produits par boutique</li>
                    <li>Multi-collaborateurs (jusqu'à 3 accès)</li>
                    <li>Nom de domaine personnalisé</li>
                </ul>
                <a href="{{ route('subscription.checkout', 'premium') }}" class="btn btn-ghost btn-sm btn-block" style="margin-top:1rem;justify-content:center;border:1px solid var(--border)">
                    Passer à Premium (Illimité) →
                </a>
            </div>
        </div>

        <div style="display:flex;justify-content:center;gap:.75rem">
            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">
                Retour à mes boutiques
            </a>
            <a href="{{ route('home') }}#tarifs" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--text-soft)">
                Consulter la grille tarifaire complète
            </a>
        </div>

    </div>
</div>
@endsection
