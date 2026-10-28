@extends('layouts.app')

@section('title', 'Étape 1 : Décrivez votre activité - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding:2.5rem 1rem 5rem">
    
    <!-- Indicateur d'étapes (Wizard) -->
    <div class="wizard-steps">
        <div class="wizard-step active">
            <span class="circle">1</span>
            <span>Activité</span>
        </div>
        <div class="wizard-line"></div>
        <div class="wizard-step">
            <span class="circle">2</span>
            <span>Style</span>
        </div>
        <div class="wizard-line"></div>
        <div class="wizard-step">
            <span class="circle">3</span>
            <span>Photos</span>
        </div>
        <div class="wizard-line"></div>
        <div class="wizard-step">
            <span class="circle">4</span>
            <span>Génération</span>
        </div>
    </div>

    <div class="card panel">
        <h1 style="font-size:2rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.6rem">
            Parlez-nous de votre commerce
            <x-icon name="sparkles" size="28" style="color:var(--brand-primary)" />
        </h1>
        <p style="color:var(--text-soft);margin-bottom:1.8rem">
            Décrivez simplement ce que vous vendez. L'intelligence artificielle s'occupe de structurer les catégories, le design et les textes de vos fiches produits.
        </p>

        @if ($errors->any())
            <div class="alert alert-err" role="alert">
                <ul style="margin:0;padding-left:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.step1.store') }}" data-once>
            @csrf

            <!-- Secteur d'activité -->
            <div class="field">
                <label>Quel est votre domaine d'activité ? <span style="color:var(--danger)">*</span></label>
                @php $currentCat = old('business_category', $shop->business_category ?? 'mode'); @endphp
                <div class="choice-grid">
                    @foreach ([
                        ['mode', 'shirt', 'Mode & Vêtements'],
                        ['beaute', 'beauty', 'Beauté & Parfums'],
                        ['alimentation', 'food', 'Alimentation & Épicerie'],
                        ['electronique', 'laptop', 'Électronique & Tech'],
                        ['maison', 'lamp', 'Maison & Décoration'],
                        ['autre', 'sparkles', 'Autre commerce'],
                    ] as [$code, $iconName, $label])
                        <label class="choice-card {{ $currentCat === $code ? 'selected' : '' }}">
                            <input type="radio" name="business_category" value="{{ $code }}" @checked($currentCat === $code) onchange="document.querySelectorAll('.choice-card').forEach(c=>c.classList.remove('selected')); this.closest('.choice-card').classList.add('selected');">
                            <span class="icon"><x-icon :name="$iconName" size="24" /></span>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Description libre (20 à 1000 caractères) -->
            <div class="field" style="margin-top:1.8rem">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem">
                    <label for="desc-input" style="margin:0">Décrivez votre activité <span style="color:var(--danger)">*</span></label>
                    <span id="char-count" style="font-size:.82rem;color:var(--text-faint)">0 / 1000</span>
                </div>
                <textarea id="desc-input" name="description" class="input" rows="5" maxlength="1000" placeholder="Ex: Je vends des chemises en lin, des robes wax sur mesure et des sandales en cuir artisanal faites à Lomé. Je m'adresse à des femmes et hommes qui aiment l'élégance africaine moderne..." required>{{ old('description', $shop->description) }}</textarea>
                <div class="hint">Minimum 20 caractères. Décrivez vos produits phares, vos matières ou votre savoir-faire.</div>
            </div>

            <!-- Exemples guidés à insérer -->
            <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;margin:1.4rem 0">
                <span style="font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.5rem">
                    <x-icon name="lightbulb" size="18" style="color:var(--warn)" />
                    Besoin d'inspiration ? Cliquez sur un exemple :
                </span>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    <button type="button" class="btn btn-ghost btn-sm" style="font-size:.82rem;padding:.3rem .6rem;" onclick="insertExample('Boutique de prêt-à-porter masculin et féminin à Lomé. Nous proposons des costumes, robes de soirée, chemises en coton et accessoires élégants pour le travail et les événements.')">Prêt-à-porter chic</button>
                    <button type="button" class="btn btn-ghost btn-sm" style="font-size:.82rem;padding:.3rem .6rem;" onclick="insertExample('Vente de produits cosmétiques naturels, beurres de karité purs, savons artisanaux et huiles essentielles bio d\'Afrique de l\'Ouest pour peaux et cheveux.')">Cosmétiques naturels</button>
                    <button type="button" class="btn btn-ghost btn-sm" style="font-size:.82rem;padding:.3rem .6rem;" onclick="insertExample('Spécialiste smartphones récents, écouteurs sans fil bluetooth, coques antichocs et chargeurs rapides garantis avec livraison express à domicile.')">Smartphones & High-Tech</button>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:2rem">
                <button id="step1-submit" type="submit" class="btn btn-primary" style="min-width:200px;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                    Continuer : Style visuel
                    <x-icon name="arrow-right" size="16" />
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const txt = document.getElementById('desc-input');
    const cnt = document.getElementById('char-count');
    function updateCount() {
        cnt.textContent = `${txt.value.length} / 1000`;
        cnt.style.color = txt.value.length >= 20 ? 'var(--ok)' : 'var(--warn)';
    }
    txt.addEventListener('input', updateCount);
    updateCount();

    function insertExample(text) {
        txt.value = text;
        updateCount();
    }
</script>
@endsection
