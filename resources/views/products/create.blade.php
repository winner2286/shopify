@extends('layouts.app')

@section('title', 'Nouveau Produit : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Ajouter un produit</h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" data-once>
        @csrf
        <div class="dash-grid" style="grid-template-columns: minmax(320px, 2fr) minmax(300px, 1fr);">
            
            <!-- Informations principales -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <section class="card panel">
                    <h3>Détails du produit</h3>
                    
                    <div class="field">
                        <label for="title">Titre du produit <span style="color:var(--danger)">*</span></label>
                        <input id="title" name="title" type="text" class="input" value="{{ old('title') }}" placeholder="Ex: Chemise en lin blanc" maxlength="120" required autofocus>
                        <div class="hint">120 caractères maximum.</div>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="input" rows="5" placeholder="Décrivez les atouts, matières, conseils d'entretien...">{{ old('description') }}</textarea>
                    </div>

                    <div class="field">
                        <label for="sku">Référence interne (SKU)</label>
                        <input id="sku" name="sku" type="text" class="input" value="{{ old('sku') }}" placeholder="Ex: CH-BLANC-01">
                    </div>
                </section>

                <!-- Photos (jusqu'à 8 photos) avec Appareil Photo & Galerie -->
                <section class="card panel" id="product-photos-section">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;flex-wrap:wrap;gap:.5rem">
                        <h3 style="margin:0">Photos du produit</h3>
                        <span id="photo-counter-badge" class="badge-status badge-hidden" style="font-size:.8rem;padding:.25rem .7rem">0 / 8 photo(s)</span>
                    </div>
                    <p style="font-size:.9rem;color:var(--text-soft);margin-bottom:1.2rem">
                        La première photo sera la photo principale de votre fiche produit. Vous pouvez prendre une photo directement avec votre appareil ou choisir depuis votre galerie.
                    </p>

                    <!-- Boutons d'action : Appareil photo & Galerie -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:.75rem;margin-bottom:1rem">
                        <button type="button" id="btn-snap-camera" class="btn" style="background:linear-gradient(135deg, #ea580c, #f97316);color:#fff;border:none;display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.9rem 1.2rem;font-weight:600;border-radius:12px;box-shadow:0 4px 14px rgba(234, 88, 12, 0.35);cursor:pointer;transition:transform .15s, box-shadow .15s;">
                            <x-icon name="camera" size="22" />
                            <span>Prendre une photo</span>
                        </button>

                        <button type="button" id="btn-open-gallery" class="btn btn-ghost" style="border:1px solid var(--border);background:var(--bg-soft);color:var(--text);display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.9rem 1.2rem;font-weight:600;border-radius:12px;cursor:pointer;transition:border-color .15s, background .15s;">
                            <x-icon name="gallery" size="22" style="color:var(--brand-primary)" />
                            <span>Choisir dans la galerie</span>
                        </button>
                    </div>

                    <!-- Zone de glisser-déposer / dropzone desktop -->
                    <div id="photo-dropzone" style="border:2px dashed var(--border);border-radius:12px;padding:1.4rem 1rem;text-align:center;background:rgba(255,255,255,0.015);cursor:pointer;transition:all .2s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4rem">
                        <div style="color:var(--text-soft);display:flex;align-items:center;gap:.5rem;font-size:.92rem;font-weight:500">
                            <x-icon name="upload" size="20" style="color:var(--brand-primary)" />
                            <span>Ou glissez-déposez vos photos ici</span>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-faint)">Formats acceptés : JPG, PNG, WebP — 5 Mo max par photo</div>
                    </div>

                    <!-- Inputs fichiers masqués -->
                    <input type="file" id="camera-file-input" accept="image/*" capture="environment" style="display:none">
                    <input type="file" id="gallery-file-input" multiple accept="image/jpeg,image/png,image/webp,image/*" style="display:none">
                    <input type="file" id="master-photos-input" name="photos[]" multiple style="display:none">

                    <!-- Message d'alerte limite -->
                    <div id="photo-limit-warning" class="alert alert-warn" style="display:none;margin-top:1rem;font-size:.85rem;padding:.6rem .9rem;">
                        Limite atteinte : Vous avez sélectionné le maximum de 8 photos.
                    </div>

                    <!-- Grille de prévisualisation des photos sélectionnées -->
                    <div id="photos-preview-grid" class="gallery-grid" style="display:none;margin-top:1.2rem"></div>
                </section>

                <!-- Stock et inventaire (C-04) -->
                <section class="card panel">
                    <h3>Stock & Disponibilité</h3>
                    
                    <div class="field">
                        <label class="check" style="margin-bottom:1rem">
                            <input type="checkbox" name="track_stock" id="track_stock" value="1" @checked(old('track_stock', true)) onchange="document.getElementById('stock-settings').style.display = this.checked ? 'block' : 'none'">
                            <span>Suivre la quantité en stock pour ce produit</span>
                        </label>
                    </div>

                    <div id="stock-settings" style="display: {{ old('track_stock', true) ? 'block' : 'none' }}">
                        <div class="dash-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:0">
                            <div class="field">
                                <label for="stock">Quantité disponible</label>
                                <input id="stock" name="stock" type="number" class="input" value="{{ old('stock', 0) }}" min="0">
                            </div>
                            <div class="field">
                                <label for="low_stock_threshold">Seuil d'alerte stock bas</label>
                                <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="input" value="{{ old('low_stock_threshold', 3) }}" min="1">
                                <div class="hint">Par défaut : 3 unités.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Colonne latérale : Prix, Catégories, Statut -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Prix en FCFA -->
                <section class="card panel">
                    <h3>Tarification (FCFA)</h3>
                    
                    <div class="field">
                        <label for="price">Prix de vente (FCFA) <span style="color:var(--danger)">*</span></label>
                        <input id="price" name="price" type="number" class="input" value="{{ old('price') }}" placeholder="Ex: 15000" min="1" step="1" required>
                    </div>

                    <div class="field">
                        <label for="compare_at_price">Prix barré / promotion (FCFA)</label>
                        <input id="compare_at_price" name="compare_at_price" type="number" class="input" value="{{ old('compare_at_price') }}" placeholder="Ex: 18000" min="1" step="1">
                        <div class="hint">Laissez vide si le produit n'est pas en promotion.</div>
                    </div>
                </section>

                <!-- Organisation & Catégories (1 à 3) -->
                <section class="card panel">
                    <h3>Organisation</h3>
                    
                    <div class="field">
                        <label>Catégories (1 à 3 max)</label>
                        @if ($categories->isEmpty())
                            <div style="font-size:.9rem;color:var(--text-faint);margin-bottom:.5rem">
                                Aucune catégorie créée. <a href="{{ route('categories.index') }}">Créer une catégorie</a>
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:.4rem;max-height:160px;overflow-y:auto">
                                @foreach ($categories as $cat)
                                    <label class="check">
                                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(is_array(old('category_ids')) && in_array($cat->id, old('category_ids')))>
                                        <span>{{ $cat->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="field" style="margin-top:1.2rem">
                        <label for="status">Statut du produit</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', 'active') === 'active')>Actif (visible sur la vitrine)</option>
                            <option value="hidden" @selected(old('status') === 'hidden')>Masqué (brouillon)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem">Enregistrer le produit</button>
                </section>
            </div>

        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const maxPhotos = 8;
    const maxSizeBytes = 5 * 1024 * 1024; // 5 Mo
    const cameraInput = document.getElementById('camera-file-input');
    const galleryInput = document.getElementById('gallery-file-input');
    const masterInput = document.getElementById('master-photos-input');
    const btnCamera = document.getElementById('btn-snap-camera');
    const btnGallery = document.getElementById('btn-open-gallery');
    const dropzone = document.getElementById('photo-dropzone');
    const previewGrid = document.getElementById('photos-preview-grid');
    const counterBadge = document.getElementById('photo-counter-badge');
    const limitWarning = document.getElementById('photo-limit-warning');

    let selectedFiles = [];

    // Clic sur "Prendre une photo" -> déclenche la caméra
    if (btnCamera && cameraInput) {
        btnCamera.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedFiles.length >= maxPhotos) {
                alert('Vous avez déjà atteint la limite de ' + maxPhotos + ' photos.');
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

    // Clic sur "Choisir dans la galerie" -> explorateur de fichiers / galerie
    if (btnGallery && galleryInput) {
        btnGallery.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedFiles.length >= maxPhotos) {
                alert('Vous avez déjà atteint la limite de ' + maxPhotos + ' photos.');
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

    // Dropzone desktop
    if (dropzone) {
        dropzone.addEventListener('click', function() {
            if (selectedFiles.length < maxPhotos && galleryInput) {
                galleryInput.click();
            }
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = 'var(--brand-primary)';
                dropzone.style.background = 'rgba(234, 88, 12, 0.08)';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = 'var(--border)';
                dropzone.style.background = 'rgba(255,255,255,0.015)';
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handleNewFiles(Array.from(dt.files));
            }
        });
    }

    // Traitement des nouveaux fichiers ajoutés
    function handleNewFiles(files) {
        let added = 0;
        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                alert('Le fichier "' + file.name + '" n\'est pas une image valide.');
                continue;
            }
            if (file.size > maxSizeBytes) {
                alert('La photo "' + file.name + '" dépasse la taille maximale autorisée (5 Mo).');
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

    // Synchronisation avec masterInput via DataTransfer pour envoi au serveur
    function syncMasterInput() {
        if (!masterInput) return;
        try {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            masterInput.files = dt.files;
        } catch (err) {
            console.warn('DataTransfer API non supportée', err);
        }
    }

    // Suppression d'une photo par son index
    window.removeProductPhoto = function(index) {
        if (index >= 0 && index < selectedFiles.length) {
            selectedFiles.splice(index, 1);
            syncMasterInput();
            renderPreviews();
        }
    };

    // Rendu dynamique de la grille de prévisualisation
    function renderPreviews() {
        if (!previewGrid || !counterBadge) return;

        const count = selectedFiles.length;
        counterBadge.textContent = count + ' / ' + maxPhotos + ' photo(s)';

        if (count > 0) {
            counterBadge.className = 'badge-status badge-active';
            previewGrid.style.display = 'grid';
        } else {
            counterBadge.className = 'badge-status badge-hidden';
            previewGrid.style.display = 'none';
        }

        if (limitWarning) {
            limitWarning.style.display = count >= maxPhotos ? 'block' : 'none';
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

            // Badge principale ou numéro
            const badge = document.createElement('span');
            badge.style.position = 'absolute';
            badge.style.bottom = '6px';
            badge.style.left = '6px';
            badge.style.fontSize = '0.72rem';
            badge.style.fontWeight = '700';
            badge.style.padding = '2px 7px';
            badge.style.borderRadius = '5px';
            badge.style.backdropFilter = 'blur(6px)';
            badge.style.zIndex = '2';

            if (idx === 0) {
                badge.style.background = 'rgba(234, 88, 12, 0.95)';
                badge.style.color = '#fff';
                badge.textContent = '★ Principale';
            } else {
                badge.style.background = 'rgba(0, 0, 0, 0.75)';
                badge.style.color = '#e2e8f0';
                badge.textContent = '#' + (idx + 1);
            }
            item.appendChild(badge);

            // Bouton supprimer avec croix
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.title = 'Supprimer cette photo';
            delBtn.setAttribute('aria-label', 'Supprimer');
            delBtn.innerHTML = '×';
            delBtn.style.fontSize = '18px';
            delBtn.style.lineHeight = '1';
            delBtn.onclick = function(e) {
                e.stopPropagation();
                window.removeProductPhoto(idx);
            };
            item.appendChild(delBtn);

            previewGrid.appendChild(item);
        });
    }
});
</script>
@endsection
