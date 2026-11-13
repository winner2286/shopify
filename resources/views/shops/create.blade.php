@extends('layouts.app')

@section('title', 'Créer une boutique — '.config('platform.name'))

@section('content')
<div class="container" style="max-width:560px;margin:2.5rem auto 4rem auto;padding:0 1rem">
    
    <!-- Lien de retour épuré -->
    <div style="margin-bottom:1.5rem">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft);padding-left:0">
            <x-icon name="arrow-left" size="14" />
            <span>Retour au tableau de bord</span>
        </a>
    </div>

    <!-- En-tête minimaliste -->
    <div style="margin-bottom:2rem">
        <h1 style="font-size:1.85rem;margin:0 0 .5rem 0;font-weight:800;letter-spacing:-0.02em">
            Créer une nouvelle boutique
        </h1>
        <p style="margin:0;font-size:.92rem;color:var(--text-soft);line-height:1.5">
            Donnez un nom à votre marque. Vos zones de livraison locales, paiements et vitrine seront configurés automatiquement en arrière-plan.
        </p>
    </div>

    @if (session('upgrade_prompt'))
        <div class="alert alert-err" role="alert" style="margin-bottom:1.5rem">
            {{ session('upgrade_prompt') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-err" role="alert" style="margin-bottom:1.5rem">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Formulaire épuré -->
    <div class="card" style="padding:2rem;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.12)">
        <form method="POST" action="{{ route('shops.store') }}" data-once>
            @csrf

            <!-- Nom de la boutique -->
            <div class="field" style="margin-bottom:1.5rem">
                <label for="name" style="font-weight:700;font-size:.86rem;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:.5rem">
                    Nom de la boutique
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    required 
                    autofocus
                    placeholder="Ex: Maison Kpalimé, Mode Lomé..." 
                    value="{{ old('name') }}"
                    oninput="updateSubdomainPreview(this.value)"
                    class="input"
                    style="font-size:1.05rem;padding:.75rem 1rem;height:48px;border-radius:10px"
                >
                
                <!-- Aperçu dynamique du sous-domaine -->
                <div style="display:flex;align-items:center;gap:.4rem;margin-top:.5rem;font-size:.82rem;color:var(--text-soft)">
                    <x-icon name="external-link" size="13" />
                    <span>Lien public : <strong id="subdomain-preview" style="color:var(--brand-3, #c5223d);font-family:monospace;font-size:.88rem">votre-boutique</strong>.zanika.shop</span>
                </div>
            </div>

            <!-- Pays & Devise (intuitif et rapide) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:2rem">
                <div class="field">
                    <label for="country" style="font-weight:700;font-size:.84rem;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:.5rem">
                        Pays principal
                    </label>
                    <select id="country" name="country" class="input" style="height:46px;border-radius:10px">
                        @foreach ($countries as $code => $countryName)
                            <option value="{{ $code }}" @selected(old('country', $defaultCountry) === $code)>
                                {{ $countryName }} ({{ $code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="currency" style="font-weight:700;font-size:.84rem;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:.5rem">
                        Devise de vente
                    </label>
                    <select id="currency" name="currency" class="input" style="height:46px;border-radius:10px">
                        <option value="XOF" @selected(old('currency', $defaultCurrency) === 'XOF')>FCFA (UEMOA - XOF)</option>
                        <option value="XAF" @selected(old('currency', $defaultCurrency) === 'XAF')>FCFA (CEMAC - XAF)</option>
                        <option value="EUR" @selected(old('currency', $defaultCurrency) === 'EUR')>Euro (€)</option>
                        <option value="USD" @selected(old('currency', $defaultCurrency) === 'USD')>Dollar ($)</option>
                    </select>
                </div>
            </div>

            <!-- Bouton d'action principal -->
            <button type="submit" class="btn btn-primary btn-block" style="height:48px;font-size:1rem;font-weight:700;border-radius:10px;justify-content:center">
                <span>Créer et lancer ma boutique</span>
                <x-icon name="arrow-right" size="16" />
            </button>
        </form>

        <!-- Encadré Quota & Upsell Multi-boutiques (subtil, discret) -->
        <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border)">
            <div style="display:flex;align-items:flex-start;gap:.75rem;padding:1rem;border-radius:12px;background:var(--bg-soft);border:1px solid var(--border)">
                <div style="width:36px;height:36px;border-radius:8px;background:color-mix(in srgb, var(--brand-1, #9e1b32) 15%, transparent);display:flex;align-items:center;justify-content:center;color:var(--brand-1, #9e1b32);flex-shrink:0">
                    <x-icon name="shop" size="18" />
                </div>
                <div style="flex:1;font-size:.82rem">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                        <strong style="color:var(--text)">Gestion Multi-Boutiques</strong>
                        <span class="badge" style="font-size:.7rem">Forfait {{ $currentPlan->name }}</span>
                    </div>
                    <p style="margin:.3rem 0 0 0;color:var(--text-soft);line-height:1.4">
                        Vous utilisez actuellement <strong>{{ $shopsCount }} sur {{ $maxShops >= 999 ? 'illimité' : $maxShops }}</strong> boutique(s) allouée(s).
                    </p>
                    @if ($maxShops < 999)
                        <div style="margin-top:.6rem">
                            <a href="{{ route('home') }}#tarifs" target="_blank" style="color:var(--brand-3, #c5223d);font-weight:700;display:inline-flex;align-items:center;gap:.3rem;text-decoration:none">
                                <span>Besoin de plus de boutiques ? Découvrez les forfaits Pro & Premium</span>
                                <x-icon name="arrow-right" size="12" />
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function updateSubdomainPreview(val) {
    const preview = document.getElementById('subdomain-preview');
    if (!preview) return;
    if (!val || !val.trim()) {
        preview.innerText = 'votre-boutique';
        return;
    }
    const slug = val.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    preview.innerText = slug || 'votre-boutique';
}
</script>
@endsection

