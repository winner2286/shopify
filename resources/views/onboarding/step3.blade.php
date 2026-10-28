@extends('layouts.app')

@section('title', 'Étape 3 : Photos de vos produits - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding:2.5rem 1rem 5rem">
    
    <!-- Wizard -->
    <div class="wizard-steps">
        <a href="{{ route('onboarding.step1') }}" class="wizard-step done" style="text-decoration:none">
            <span class="circle"><x-icon name="check" size="14" /></span>
            <span>Activité</span>
        </a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <a href="{{ route('onboarding.step2') }}" class="wizard-step done" style="text-decoration:none">
            <span class="circle"><x-icon name="check" size="14" /></span>
            <span>Style</span>
        </a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <div class="wizard-step active">
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
            Envoyez les photos de vos articles
            <x-icon name="camera" size="28" style="color:var(--brand-primary)" />
        </h1>
        <p style="color:var(--text-soft);margin-bottom:1.8rem">
            Sélectionnez vos photos directement depuis votre téléphone ou votre ordinateur. L'IA les analysera pour rédiger automatiquement vos titres et descriptions de vente.
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

        <form method="POST" action="{{ route('onboarding.step3.store') }}" enctype="multipart/form-data" data-once>
            @csrf

            <!-- Zone de sélection / Dropzone -->
            <div class="dropzone" onclick="document.getElementById('photo-input').click()">
                <div style="margin-bottom:.75rem;color:var(--brand-primary);display:flex;justify-content:center" aria-hidden="true">
                    <x-icon name="upload" size="48" />
                </div>
                <h3 style="margin-bottom:.3rem">Touchez pour sélectionner vos photos</h3>
                <p style="color:var(--text-soft);font-size:.9rem;margin-bottom:1rem">Jusqu'à 20 photos (JPG, PNG, WebP — max 5 Mo par image)</p>
                <button type="button" class="btn btn-ghost btn-sm">Parcourir les fichiers</button>
                <input type="file" id="photo-input" name="photos[]" multiple accept="image/png,image/jpeg,image/webp" style="display:none" onchange="previewFiles(this)">
            </div>

            <!-- Grille de prévisualisation immédiate -->
            <div id="preview-grid" class="gallery-grid" style="margin-top:1.5rem"></div>

            <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;margin:1.8rem 0;display:flex;align-items:flex-start;gap:.8rem">
                <x-icon name="lightbulb" size="22" style="color:var(--warn);flex-shrink:0" />
                <span style="font-size:.88rem;color:var(--text-soft)">
                    <strong>Pas de photo sous la main ?</strong> Vous pouvez continuer dès maintenant. L'IA générera des articles de démonstration que vous pourrez remplacer plus tard.
                </span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2.5rem">
                <a href="{{ route('onboarding.step2') }}" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="arrow-left" size="16" />
                    Précédent
                </a>
                <button id="step3-submit" type="submit" class="btn btn-primary" style="min-width:200px;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                    Lancer la génération IA
                    <x-icon name="sparkles" size="16" />
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewFiles(input) {
        const grid = document.getElementById('preview-grid');
        grid.innerHTML = '';
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'gallery-item';
                    item.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                    grid.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        }
    }
</script>
@endsection
