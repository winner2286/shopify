@extends('layouts.app')

@section('title', 'Étape 2 : Style & Marque - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding:2.5rem 1rem 5rem">
    
    <!-- Wizard -->
    <div class="wizard-steps">
        <a href="{{ route('onboarding.step1') }}" class="wizard-step done" style="text-decoration:none">
            <span class="circle"><x-icon name="check" size="14" /></span>
            <span>Activité</span>
        </a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <div class="wizard-step active">
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
            Personnalisez votre identité
            <x-icon name="palette" size="28" style="color:var(--brand-primary)" />
        </h1>
        <p style="color:var(--text-soft);margin-bottom:1.8rem">
            Définissez le nom, les couleurs et l'ambiance visuelle de votre future boutique en ligne.
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

        <form method="POST" action="{{ route('onboarding.step2.store') }}" data-once>
            @csrf

            <!-- Nom de la boutique -->
            <div class="field">
                <label for="suggested_name">Nom de votre boutique <span style="color:var(--danger)">*</span></label>
                <input id="suggested_name" name="suggested_name" type="text" class="input" value="{{ old('suggested_name', $shop->name ?? 'Maison Élégance') }}" placeholder="Ex: Lomé Chic, Wax & Co..." required>
                <div class="hint">Ce nom sera utilisé pour votre logo, votre adresse et vos factures.</div>
            </div>

            <!-- Numéro WhatsApp de la boutique -->
            <div class="field" style="margin-top:1.4rem">
                <label for="whatsapp" style="display:flex;align-items:center;gap:.4rem">
                    <x-icon name="whatsapp" size="18" style="color:#25D366" />
                    Numéro WhatsApp de la boutique (pour recevoir vos alertes de commandes)
                </label>
                <input id="whatsapp" name="whatsapp" type="text" class="input" value="{{ old('whatsapp', $shop->whatsapp ?? '') }}" placeholder="Ex: +228 90 12 34 56">
                <div class="hint" style="color:var(--text-soft)">Votre numéro pour recevoir les alertes de commandes avec récapitulatif instantané et discuter avec vos clients.</div>
            </div>

            <!-- Public visé -->
            <div class="field" style="margin-top:1.4rem">
                <label for="target_audience">À qui s'adressent vos produits ?</label>
                <input id="target_audience" name="target_audience" type="text" class="input" value="{{ old('target_audience', $shop->target_audience ?? 'Jeunes actifs, femmes élégantes, professionnels') }}" placeholder="Ex: Familles, étudiants, amateurs de mode...">
            </div>

            <!-- Ambiance / Style visuel -->
            <div class="field" style="margin-top:1.6rem">
                <label>Style visuel souhaité <span style="color:var(--danger)">*</span></label>
                @php $currentStyle = old('visual_style', $shop->visual_style ?? 'moderne'); @endphp
                <div class="choice-grid">
                    @foreach ([
                        ['moderne', 'rocket', 'Moderne', 'Épuré, violet néon, high-tech'],
                        ['elegant', 'crown', 'Élégant', 'Doré, noir profond, luxe'],
                        ['colore', 'flower', 'Coloré', 'Vibrant, festif, chaleureux'],
                        ['minimaliste', 'sun', 'Minimaliste', 'Sobre, noir et blanc, essentiel'],
                        ['artisanal', 'craft', 'Artisanal', 'Terre cuite, naturel, authentique'],
                    ] as [$style, $ico, $label, $sub])
                        <label class="choice-card {{ $currentStyle === $style ? 'selected' : '' }}">
                            <input type="radio" name="visual_style" value="{{ $style }}" @checked($currentStyle === $style) onchange="document.querySelectorAll('.choice-card').forEach(c=>c.classList.remove('selected')); this.closest('.choice-card').classList.add('selected');">
                            <span class="icon"><x-icon :name="$ico" size="24" /></span>
                            <span>{{ $label }}</span>
                            <div style="font-size:.75rem;color:var(--text-faint);margin-top:.2rem">{{ $sub }}</div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Couleur de marque -->
            <div class="field" style="margin-top:1.6rem">
                <label for="brand_color">Couleur principale de marque</label>
                <div style="display:flex;align-items:center;gap:1rem;margin-top:.4rem">
                    <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $shop->brand_color ?? '#6d3df2') }}" style="width:50px;height:46px;border-radius:10px;border:1px solid var(--border);cursor:pointer;background:transparent;">
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        @foreach (['#6d3df2', '#00d2ff', '#d4af37', '#ff007a', '#10b981', '#b45309'] as $c)
                            <button type="button" style="width:34px;height:34px;border-radius:50%;background:{{ $c }};border:2px solid var(--border);cursor:pointer" onclick="document.getElementById('brand_color').value='{{ $c }}';"></button>
                        @endforeach
                    </div>
                </div>
                <div class="hint">Cette couleur sera utilisée pour vos boutons, vos bannières et votre logo.</div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2.5rem">
                <a href="{{ route('onboarding.step1') }}" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="arrow-left" size="16" />
                    Précédent
                </a>
                <button id="step2-submit" type="submit" class="btn btn-primary" style="min-width:200px;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                    Continuer : Photos
                    <x-icon name="arrow-right" size="16" />
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
