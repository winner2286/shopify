@extends('layouts.app')

@section('title', 'Étape 4 : Génération de votre boutique - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding:2.5rem 1rem 5rem">
    
    <!-- Wizard -->
    <div class="wizard-steps">
        <a href="{{ route('onboarding.step1') }}" class="wizard-step done" style="text-decoration:none"><span class="circle"><x-icon name="check" size="14" /></span></a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <a href="{{ route('onboarding.step2') }}" class="wizard-step done" style="text-decoration:none"><span class="circle"><x-icon name="check" size="14" /></span></a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <a href="{{ route('onboarding.step3') }}" class="wizard-step done" style="text-decoration:none"><span class="circle"><x-icon name="check" size="14" /></span></a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <div class="wizard-step active">
            <span class="circle">4</span>
            <span>Génération</span>
        </div>
    </div>

    <div class="card panel" style="text-align:center">
        <div style="margin-bottom:1rem;color:var(--brand-primary);display:flex;justify-content:center" aria-hidden="true">
            <x-icon name="sparkles" size="56" />
        </div>
        <h1 style="font-size:2.2rem;margin-bottom:.5rem">L'IA est prête à construire votre boutique</h1>
        <p style="color:var(--text-soft);max-width:540px;margin:0 auto 2rem">
            En un clic, nous allons créer l'ensemble de votre boutique : thème personnalisé, 3 propositions de logos, rayons et textes de produits optimisés.
        </p>

        <!-- Étapes de la chaîne IA -->
        <div class="ai-loader" style="text-align:left;max-width:480px;margin:0 auto 2.5rem">
            <div class="ai-step-item complete">
                <span style="color:var(--ok);display:inline-flex;align-items:center"><x-icon name="check" size="18" /></span>
                <div>
                    <strong>Profil & Cible commerciale</strong>
                    <div style="font-size:.8rem;color:var(--text-soft)">Secteur : {{ $shop->business_category ?? 'Général' }}</div>
                </div>
            </div>
            <div class="ai-step-item complete">
                <span style="color:var(--ok);display:inline-flex;align-items:center"><x-icon name="check" size="18" /></span>
                <div>
                    <strong>Identité visuelle & Style</strong>
                    <div style="font-size:.8rem;color:var(--text-soft)">Ambiance {{ $shop->visual_style ?? 'Moderne' }} & Teinte {{ $shop->brand_color ?? '#6d3df2' }}</div>
                </div>
            </div>
            <div class="ai-step-item" id="step-ai-3">
                <span style="display:inline-flex;align-items:center"><x-icon name="sparkles" size="18" /></span>
                <div>
                    <strong>Logos & Monogrammes vectoriels</strong>
                    <div style="font-size:.8rem;color:var(--text-soft)">3 propositions uniques générées pour {{ $shop->name ?? 'votre boutique' }}</div>
                </div>
            </div>
            <div class="ai-step-item" id="step-ai-4">
                <span style="display:inline-flex;align-items:center"><x-icon name="tag" size="18" /></span>
                <div>
                    <strong>Rayons & Rédaction des fiches</strong>
                    <div style="font-size:.8rem;color:var(--text-soft)">Descriptions vendeuses rédigées par l'IA (sans prix imposé)</div>
                </div>
            </div>
            <div class="ai-step-item" id="step-ai-5">
                <span style="display:inline-flex;align-items:center"><x-icon name="file-text" size="18" /></span>
                <div>
                    <strong>Pages d'information & Livraison</strong>
                    <div style="font-size:.8rem;color:var(--text-soft)">À propos, Mobile Money, contact direct</div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('onboarding.generate') }}" onsubmit="startLoader(this)">
            @csrf
            <button id="btn-run-ai" type="submit" class="btn btn-primary" style="padding:1rem 2.5rem;font-size:1.1rem;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;margin:0 auto">
                <x-icon name="zap" size="20" />
                Lancer la création automatique
            </button>
        </form>
    </div>
</div>

<script>
    function startLoader(form) {
        const btn = document.getElementById('btn-run-ai');
        btn.disabled = true;
        btn.textContent = 'Génération en cours… (moins de 15 sec)';
        document.getElementById('step-ai-3').classList.add('running');
        setTimeout(() => {
            document.getElementById('step-ai-3').classList.remove('running');
            document.getElementById('step-ai-3').classList.add('complete');
            document.getElementById('step-ai-4').classList.add('running');
        }, 1200);
        setTimeout(() => {
            document.getElementById('step-ai-4').classList.remove('running');
            document.getElementById('step-ai-4').classList.add('complete');
            document.getElementById('step-ai-5').classList.add('running');
        }, 2400);
    }
</script>
@endsection
