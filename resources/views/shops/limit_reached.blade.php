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
            Votre formule {{ $currentPlan->name }} inclut {{ $maxShops }} boutique{{ $maxShops > 1 ? 's' : '' }}. Votre compte en utilise {{ $shopsCount }}.
        </p>

        <!-- Grille des forfaits supérieurs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;margin-bottom:2rem;text-align:left">
            @forelse ($upgradePlans as $plan)
                <div style="padding:1.25rem;border-radius:14px;background:var(--bg-soft);border:1px solid var(--border)">
                    <h2 style="font-size:1.1rem;margin:0">{{ $plan->name }}</h2>
                    <p style="font-weight:750;margin:.3rem 0 .75rem">{{ number_format($plan->monthly_price, 0, ',', ' ') }} FCFA / mois</p>
                    @include('subscription.plan-allowances', ['plan' => $plan])
                    <a href="{{ route('subscription.checkout', $plan->slug) }}" class="btn btn-{{ $loop->first ? 'primary' : 'ghost' }} btn-sm btn-block">Choisir {{ $plan->name }}</a>
                </div>
            @empty
                <p class="plan-conditions" style="text-align:center">Votre compte a atteint le nombre maximum de boutiques des formules disponibles.</p>
            @endforelse
        </div>

        <div style="display:flex;justify-content:center;gap:.75rem">
            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">
                Retour à mes boutiques
            </a>
            <a href="{{ route('home', ['landing' => 1]) }}#tarifs" class="btn btn-ghost btn-sm" style="color:var(--text-soft)">
                Consulter la grille tarifaire complète
            </a>
        </div>

    </div>
</div>
@endsection

