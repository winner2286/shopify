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

            <!-- Boutons d'action : Appareil photo & Galerie -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:1.2rem">
                <button type="button" id="btn-snap-camera" class="btn" style="background:linear-gradient(135deg, #ea580c, #f97316);color:#fff;border:none;display:flex;align-items:center;justify-content:center;gap:.7rem;padding:1.1rem 1.4rem;font-weight:600;font-size:1.05rem;border-radius:14px;box-shadow:0 6px 18px rgba(234, 88, 12, 0.35);cursor:pointer;transition:transform .15s, box-shadow .15s;">
                    <x-icon name="camera" size="24" />
                    <span>Prendre une photo</span>
                </button>

                <button type="button" id="btn-open-gallery" class="btn btn-ghost" style="border:1px solid var(--border);background:var(--bg-soft);color:var(--text);display:flex;align-items:center;justify-content:center;gap:.7rem;padding:1.1rem 1.4rem;font-weight:600;font-size:1.05rem;border-radius:14px;cursor:pointer;">
                    <x-icon name="gallery" size="24" style="color:var(--brand-primary)" />
                    <span>Choisir dans la galerie</span>
                </button>
            </div>

            <!-- Zone de sélection / Dropzone desktop -->
            <div id="onboarding-dropzone" class="dropzone" style="cursor:pointer;padding:1.8rem 1rem">
                <div style="margin-bottom:.5rem;color:var(--brand-primary);display:flex;justify-content:center" aria-hidden="true">
                    <x-icon name="upload" size="36" />
                </div>
                <h3 style="margin-bottom:.2rem;font-size:1.1rem">Ou glissez-déposez vos photos ici</h3>
                <p style="color:var(--text-soft);font-size:.85rem;margin-bottom:.5rem">Jusqu'à 20 photos (JPG, PNG, WebP — max 5 Mo par image)</p>
                <span id="onboarding-counter-badge" class="badge-status badge-hidden" style="font-size:.82rem;padding:.25rem .75rem">0 / 20 photo(s) ajoutée(s)</span>
            </div>

            <!-- Inputs cachés -->
            <input type="file" id="camera-file-input" accept="image/*" capture="environment" style="display:none">
            <input type="file" id="gallery-file-input" multiple accept="image/png,image/jpeg,image/webp,image/*" style="display:none">
            <input type="file" id="master-photos-input" name="photos[]" multiple style="display:none">

            <!-- Grille de prévisualisation immédiate -->
            <div id="preview-grid" class="gallery-grid" style="margin-top:1.5rem;display:none"></div>

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
document.addEventListener('DOMContentLoaded', function() {
    const maxPhotos = 20;
    const maxSizeBytes = 5 * 1024 * 1024;
    const cameraInput = document.getElementById('camera-file-input');
    const galleryInput = document.getElementById('gallery-file-input');
    const masterInput = document.getElementById('master-photos-input');
    const btnCamera = document.getElementById('btn-snap-camera');
    const btnGallery = document.getElementById('btn-open-gallery');
    const dropzone = document.getElementById('onboarding-dropzone');
    const previewGrid = document.getElementById('preview-grid');
    const counterBadge = document.getElementById('onboarding-counter-badge');

    let selectedFiles = [];

    if (btnCamera && cameraInput) {
        btnCamera.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedFiles.length >= maxPhotos) {
                alert('Limite de ' + maxPhotos + ' photos atteinte.');
                return;
            }
            cameraInput.click();
        });
        cameraInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                handleNewFiles(Array.from(this.files));
                this.value = '';
            }
        });
    }

    if (btnGallery && galleryInput) {
        btnGallery.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedFiles.length >= maxPhotos) {
                alert('Limite de ' + maxPhotos + ' photos atteinte.');
                return;
            }
            galleryInput.click();
        });
        galleryInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                handleNewFiles(Array.from(this.files));
                this.value = '';
            }
        });
    }

    if (dropzone) {
        dropzone.addEventListener('click', function(e) {
            if (selectedFiles.length < maxPhotos && galleryInput) {
                galleryInput.click();
            }
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--brand-primary)';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--border)';
            }, false);
        });
        dropzone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handleNewFiles(Array.from(dt.files));
            }
        });
    }

    function handleNewFiles(files) {
        let added = 0;
        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                alert('Le fichier "' + file.name + '" n\'est pas une image valide.');
                continue;
            }
            if (file.size > maxSizeBytes) {
                alert('La photo "' + file.name + '" dépasse 5 Mo.');
                continue;
            }
            if (selectedFiles.length >= maxPhotos) {
                alert('Limite de ' + maxPhotos + ' photos atteinte.');
                break;
            }
            selectedFiles.push(file);
            added++;
        }
        if (added > 0) {
            syncMasterInput();
            renderPreviews();
        }
    }

    function syncMasterInput() {
        if (!masterInput) return;
        try {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            masterInput.files = dt.files;
        } catch (err) {
            console.warn('DataTransfer non supporté', err);
        }
    }

    window.removeOnboardingPhoto = function(index) {
        if (index >= 0 && index < selectedFiles.length) {
            selectedFiles.splice(index, 1);
            syncMasterInput();
            renderPreviews();
        }
    };

    function renderPreviews() {
        if (!previewGrid || !counterBadge) return;
        const count = selectedFiles.length;
        counterBadge.textContent = count + ' / ' + maxPhotos + ' photo(s) ajoutée(s)';

        if (count > 0) {
            counterBadge.className = 'badge-status badge-active';
            previewGrid.style.display = 'grid';
        } else {
            counterBadge.className = 'badge-status badge-hidden';
            previewGrid.style.display = 'none';
        }

        previewGrid.innerHTML = '';
        selectedFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'gallery-item';
            item.style.position = 'relative';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            item.appendChild(img);

            const badge = document.createElement('span');
            badge.style.position = 'absolute';
            badge.style.bottom = '6px';
            badge.style.left = '6px';
            badge.style.fontSize = '0.72rem';
            badge.style.fontWeight = '700';
            badge.style.padding = '2px 7px';
            badge.style.borderRadius = '5px';
            badge.style.background = idx === 0 ? 'rgba(234, 88, 12, 0.95)' : 'rgba(0, 0, 0, 0.75)';
            badge.style.color = '#fff';
            badge.textContent = idx === 0 ? '★ Photo 1' : '#' + (idx + 1);
            item.appendChild(badge);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.title = 'Supprimer cette photo';
            delBtn.innerHTML = '×';
            delBtn.style.fontSize = '18px';
            delBtn.style.lineHeight = '1';
            delBtn.onclick = function(e) {
                e.stopPropagation();
                window.removeOnboardingPhoto(idx);
            };
            item.appendChild(delBtn);

            previewGrid.appendChild(item);
        });
    }
});
</script>
@endsection
