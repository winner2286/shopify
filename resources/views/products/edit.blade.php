@extends('layouts.app')

@section('title', 'Modifier : '.$product->title.' - '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Modifier le produit</h1>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            <button type="button" class="btn btn-ghost btn-sm" onclick="openProductLivePreview()" style="display:inline-flex;align-items:center;gap:.4rem;font-weight:700;border:1px solid var(--border)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span>Aperçu vitrine</span>
            </button>
            <form method="POST" action="{{ route('products.destroy', $product) }}" style="display:inline" data-confirm="Archiver ce produit ? Il ne sera plus proposé sur la boutique.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Archiver le produit</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" data-once>
        @csrf
        @method('PUT')
        <input type="hidden" id="ai_temp_photo_path" name="ai_temp_photo_path" value="">
        
        <div class="product-form-grid">
            
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Détails produit -->
                <section class="card panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
                        <h3 style="margin:0">Détails du produit</h3>
                        <button type="button" class="btn btn-sm" onclick="runAiProductOptimize()" id="ai-optimize-btn" style="background:linear-gradient(135deg, #e11d48, #7c3aed);color:#fff;font-weight:700;border:none;padding:.35rem .85rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;box-shadow:0 2px 8px rgba(225,29,72,0.3)">
                            <x-icon name="sparkles" :size="14" />
                            <span>Optimiser avec l'IA</span>
                        </button>
                    </div>
                    
                    <div id="ai-optimize-banner" style="display:none;background:rgba(225,29,72,0.08);border:1px solid rgba(225,29,72,0.35);border-radius:10px;padding:.85rem;margin-bottom:1.25rem">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                            <strong style="color:#e11d48;font-size:.9rem">Suggestions d'optimisation IA prêtes</strong>
                            <button type="button" onclick="applyAiOptimizations()" class="btn btn-sm btn-primary" style="padding:.25rem .75rem;font-size:.78rem">
                                Appliquer à la fiche
                            </button>
                        </div>
                        <div id="ai-optimize-preview" style="font-size:.82rem;color:var(--text-soft);line-height:1.4"></div>
                    </div>
                    
                    <div class="field">
                        <label for="title">Titre du produit <span style="color:var(--danger)">*</span></label>
                        <input id="title" name="title" type="text" class="input" value="{{ old('title', $product->title) }}" maxlength="120" required>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="input" rows="5">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="field">
                        <label for="sku">Référence interne (SKU)</label>
                        <input id="sku" name="sku" type="text" class="input" value="{{ old('sku', $product->sku) }}">
                    </div>
                </section>

                <!-- Photos actuelles & Ajout de photos -->
                <section class="card panel">
                    <h3>Photos du produit ({{ $product->images->count() }} / 8)</h3>
                    
                    @if ($product->images->isNotEmpty())
                        <div class="gallery-grid">
                            @foreach ($product->images as $img)
                                <div class="gallery-item">
                                    <img src="{{ $img->url }}" alt="">
                                    <button type="button" data-confirm="Supprimer cette photo du produit ?" data-confirm-action="document.getElementById('delete-img-{{ $img->id }}').submit();" title="Supprimer" style="display:grid;place-items:center"><x-icon name="x" size="14" /></button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @php $remainingPhotos = 8 - $product->images->count(); @endphp
                    @if ($remainingPhotos > 0)
                        <div style="margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--border)">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;flex-wrap:wrap;gap:.5rem">
                                <label style="font-weight:600;margin:0">Ajouter de nouvelles photos</label>
                                <span id="photo-counter-badge" class="badge-status badge-hidden" style="font-size:.8rem;padding:.2rem .6rem">0 / {{ $remainingPhotos }} nouvelle(s) photo(s)</span>
                            </div>
                            <p style="font-size:.85rem;color:var(--text-soft);margin-bottom:.8rem">
                                Vous pouvez encore ajouter jusqu'à {{ $remainingPhotos }} photo(s).
                            </p>

                            <!-- Bannière Studio Photo IA Professionnel (Zanika Luxury Theme) -->
                            <div id="ai-quality-card" style="background:var(--surface);border:1px solid rgba(158,27,50,0.3);border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;box-shadow:0 3px 12px rgba(0,0,0,0.15)">
                                <div style="display:flex;align-items:center;gap:.75rem;min-width:220px;flex:1">
                                    <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg, #9e1b32, #c5223d);display:grid;place-items:center;color:#fff;box-shadow:0 3px 10px rgba(158,27,50,0.35);flex-shrink:0">
                                        <x-icon name="sparkles" size="18" />
                                    </div>
                                    <div style="min-width:0">
                                        <div style="display:flex;align-items:center;gap:.4rem">
                                            <strong style="color:var(--text);font-size:.95rem;display:block">Studio Photo IA Professionnel</strong>
                                            <span style="font-size:.68rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);padding:1px 5px;border-radius:4px">100% Fidèle</span>
                                        </div>
                                        <span style="font-size:.8rem;color:var(--text-soft)">Détourage net, cadrage 1:1, ombres physiques & décor adapté • +12 crédits</span>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:.6rem;flex-shrink:0">
                                    <button type="button" onclick="triggerAiStudioFromButton()" id="btn-trigger-ai-studio" class="btn btn-sm" style="background:linear-gradient(135deg, #9e1b32, #c5223d);color:#fff;border:none;font-weight:700;padding:.5rem 1rem;border-radius:8px;font-size:.84rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;box-shadow:0 3px 10px rgba(158,27,50,0.3)">
                                        <x-icon name="sparkles" size="15" />
                                        <span>Améliorer avec l'IA</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Indicateur de chargement IA -->
                            <div id="ai-quality-loading" style="display:none;margin-bottom:1rem;padding:.75rem 1rem;background:rgba(158,27,50,0.08);border:1px solid rgba(158,27,50,0.3);border-radius:10px;color:var(--text);font-size:.84rem;align-items:center;gap:.6rem">
                                <span style="width:16px;height:16px;border:2px solid #c5223d;border-top-color:transparent;border-radius:50%;display:inline-block;animation:zanika-spin 0.8s linear infinite;flex-shrink:0"></span>
                                <span id="ai-quality-loading-text">Studio Photo IA en cours : Analyse du produit et création des packshots...</span>
                            </div>

                            <!-- Boutons d'action : Appareil photo & Galerie -->
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.75rem;margin-bottom:1rem">
                                <button type="button" id="btn-snap-camera" class="btn" style="background:linear-gradient(135deg, #ea580c, #f97316);color:#fff;border:none;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1rem;font-weight:600;border-radius:10px;box-shadow:0 4px 12px rgba(234, 88, 12, 0.3);cursor:pointer;">
                                    <x-icon name="camera" size="20" />
                                    <span>Prendre une photo</span>
                                </button>

                                <button type="button" id="btn-open-gallery" class="btn btn-ghost" style="border:1px solid var(--border);background:var(--bg-soft);color:var(--text);display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1rem;font-weight:600;border-radius:10px;cursor:pointer;">
                                    <x-icon name="gallery" size="20" style="color:var(--brand-primary)" />
                                    <span>Choisir dans la galerie</span>
                                </button>
                            </div>

                            <!-- Zone de glisser-déposer desktop -->
                            <div id="photo-dropzone" style="border:2px dashed var(--border);border-radius:10px;padding:1.2rem 1rem;text-align:center;background:rgba(255,255,255,0.015);cursor:pointer;transition:all .2s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.3rem">
                                <div style="color:var(--text-soft);display:flex;align-items:center;gap:.4rem;font-size:.88rem">
                                    <x-icon name="upload" size="18" style="color:var(--brand-primary)" />
                                    <span>Ou glissez vos photos ici (max 5 Mo)</span>
                                </div>
                            </div>

                            <!-- Inputs masqués -->
                            <input type="file" id="camera-file-input" accept="image/*" capture="environment" style="display:none">
                            <input type="file" id="gallery-file-input" multiple accept="image/jpeg,image/png,image/webp,image/*" style="display:none">
                            <input type="file" id="master-photos-input" name="photos[]" multiple style="display:none">

                            <div id="photo-limit-warning" class="alert alert-warn" style="display:none;margin-top:.8rem;font-size:.82rem;padding:.5rem .8rem;">
                                Limite atteinte pour ce produit (8 photos au total).
                            </div>

                            <!-- Grille de prévisualisation des NOUVELLES photos ajoutées -->
                            <div id="photos-preview-grid" class="gallery-grid" style="display:none;margin-top:1rem"></div>
                        </div>
                    @else
                        <div class="hint" style="margin-top:1rem">Vous avez atteint le maximum de 8 photos pour ce produit. Supprimez-en une pour en ajouter une nouvelle.</div>
                    @endif
                </section>

                <!-- Stock et Ajustements (C-04) -->
                <section class="card panel">
                    <h3>Gestion du stock</h3>
                    
                    <div class="field">
                        <label class="check">
                            <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))>
                            <span>Suivre la quantité en stock pour ce produit</span>
                        </label>
                    </div>

                    <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;margin:1rem 0">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span>Stock actuel :</span>
                            <strong style="font-size:1.3rem;">{{ $product->stock }} unité(s)</strong>
                        </div>
                    </div>

                    <h4>Ajustement manuel du stock</h4>
                    <div class="dash-grid" style="grid-template-columns: 1fr 2fr; margin-bottom:1rem">
                        <div class="field">
                            <label for="stock_adjustment">Variation (+ ou -)</label>
                            <input id="stock_adjustment" name="stock_adjustment" type="number" class="input" placeholder="Ex: +5 ou -2">
                        </div>
                        <div class="field">
                            <label for="adjustment_reason">Motif de l'ajustement</label>
                            <input id="adjustment_reason" name="adjustment_reason" type="text" class="input" placeholder="Ex: Réassort, Perte, Erreur comptage">
                        </div>
                    </div>

                    <div class="field">
                        <label for="low_stock_threshold">Seuil d'alerte stock bas</label>
                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="input" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" min="1">
                    </div>

                    @if ($product->stockMovements->isNotEmpty())
                        <h4 style="margin-top:1.5rem">Historique des mouvements récents</h4>
                        <div class="table-wrap">
                            <table class="table" style="font-size:.85rem">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Variation</th>
                                        <th>Nouveau stock</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->stockMovements->take(5) as $mvt)
                                        <tr>
                                            <td>{{ $mvt->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <strong style="color: {{ $mvt->quantity_change > 0 ? 'var(--ok)' : 'var(--danger)' }}">
                                                    {{ $mvt->quantity_change > 0 ? '+'.$mvt->quantity_change : $mvt->quantity_change }}
                                                </strong>
                                            </td>
                                            <td>{{ $mvt->new_stock }}</td>
                                            <td>{{ $mvt->comment ?? $mvt->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <!-- Colonne latérale -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Tarification -->
                <section class="card panel">
                    <h3>Tarification (FCFA)</h3>
                    
                    <div class="field">
                        <label for="price">Prix de vente (FCFA) <span style="color:var(--danger)">*</span></label>
                        <input id="price" name="price" type="number" class="input" value="{{ old('price', $product->price) }}" min="1" step="1" required>
                    </div>

                    <div class="field">
                        <label for="compare_at_price">Prix barré (FCFA)</label>
                        <input id="compare_at_price" name="compare_at_price" type="number" class="input" value="{{ old('compare_at_price', $product->compare_at_price) }}" min="1" step="1">
                    </div>
                </section>

                <!-- Organisation & Catégories -->
                <section class="card panel">
                    <h3>Organisation</h3>
                    
                    <div class="field">
                        <label>Catégories (1 à 3 max)</label>
                        @php $selectedCats = old('category_ids', $product->categories->pluck('id')->toArray()); @endphp
                        <div style="display:flex;flex-direction:column;gap:.4rem;max-height:160px;overflow-y:auto">
                            @foreach ($categories as $cat)
                                <label class="check">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $selectedCats))>
                                    <span>{{ $cat->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="field" style="margin-top:1.2rem">
                        <label for="status">Statut de publication</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', $product->status) === 'active')>Actif (visible sur la vitrine)</option>
                            <option value="hidden" @selected(old('status', $product->status) === 'hidden')>Masqué (brouillon privé)</option>
                            <option value="archived" @selected(old('status', $product->status) === 'archived')>Archivé</option>
                        </select>
                    </div>

                    <!-- Actions d'enregistrement et de prévisualisation -->
                    <div style="display:flex;flex-direction:column;gap:.75rem;margin-top:1.5rem">
                        <button type="button" class="btn btn-ghost" onclick="openProductLivePreview()" style="width:100%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;gap:.5rem;font-weight:700;padding:.85rem;background:var(--bg-soft);border-radius:10px">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <span>👁️ Prévisualiser la fiche vitrine</span>
                        </button>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                            <button type="button" class="btn btn-ghost" onclick="confirmProductSubmission('hidden')" style="font-size:.82rem;padding:.75rem .4rem;text-align:center;border-radius:10px;border:1px solid var(--border)">
                                💾 Mettre en brouillon
                            </button>
                            <button type="button" class="btn btn-primary" onclick="confirmProductSubmission('active')" style="background:linear-gradient(135deg, #10b981, #059669);border-color:#059669;font-size:.82rem;padding:.75rem .4rem;font-weight:700;text-align:center;border-radius:10px;box-shadow:0 3px 12px rgba(16,185,129,0.3)">
                                🚀 Publier en ligne
                            </button>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </form>

    <!-- Formulaires cachés de suppression d'image -->
    @foreach ($product->images as $img)
        <form id="delete-img-{{ $img->id }}" method="POST" action="{{ route('products.images.destroy', [$product, $img]) }}" style="display:none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</div>

@if ($remainingPhotos > 0)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const maxNewPhotos = {{ $remainingPhotos }};
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

    if (btnCamera && cameraInput) {
        btnCamera.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedFiles.length >= maxNewPhotos) {
                alert('Vous ne pouvez pas ajouter plus de ' + maxNewPhotos + ' nouvelle(s) photo(s).');
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
            if (selectedFiles.length >= maxNewPhotos) {
                alert('Vous ne pouvez pas ajouter plus de ' + maxNewPhotos + ' nouvelle(s) photo(s).');
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
        dropzone.addEventListener('click', function() {
            if (selectedFiles.length < maxNewPhotos && galleryInput) {
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

    function handleNewFiles(files) {
        let added = 0;
        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                alert('Le fichier "' + file.name + '" n\'est pas une image.');
                continue;
            }
            if (file.size > maxSizeBytes) {
                alert('La photo "' + file.name + '" dépasse 5 Mo.');
                continue;
            }
            if (selectedFiles.length >= maxNewPhotos) {
                alert('Limite atteinte (' + maxNewPhotos + ' nouvelle(s) photo(s) max).');
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

    window.removeEditPhoto = function(index) {
        if (index >= 0 && index < selectedFiles.length) {
            selectedFiles.splice(index, 1);
            syncMasterInput();
            renderPreviews();
        }
    };

    window.rotateEditPhoto = function(index) {
        if (index < 0 || index >= selectedFiles.length) return;
        const file = selectedFiles[index];
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.height;
            canvas.height = img.width;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width / 2, canvas.height / 2);
            ctx.rotate(90 * Math.PI / 180);
            ctx.drawImage(img, -img.width / 2, -img.height / 2);
            canvas.toBlob(function(blob) {
                if (blob) {
                    const rotatedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    selectedFiles[index] = rotatedFile;
                    syncMasterInput();
                    renderPreviews();
                }
                URL.revokeObjectURL(url);
            }, 'image/jpeg', 0.92);
        };
        img.src = url;
    };

    function renderPreviews() {
        if (!previewGrid || !counterBadge) return;

        const count = selectedFiles.length;
        counterBadge.textContent = count + ' / ' + maxNewPhotos + ' nouvelle(s) photo(s)';

        if (count > 0) {
            counterBadge.className = 'badge-status badge-active';
            previewGrid.style.display = 'grid';
        } else {
            counterBadge.className = 'badge-status badge-hidden';
            previewGrid.style.display = 'none';
        }

        if (limitWarning) {
            limitWarning.style.display = count >= maxNewPhotos ? 'block' : 'none';
        }

        previewGrid.innerHTML = '';
        selectedFiles.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'gallery-item';
            item.style.position = 'relative';
            item.style.background = '#ffffff';
            item.style.padding = '5px';
            item.style.boxSizing = 'border-box';
            item.style.border = '1px solid rgba(255,255,255,0.15)';
            item.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            img.style.objectFit = 'contain';
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.borderRadius = '4px';
            img.style.filter = 'contrast(1.05) brightness(1.02)';
            item.appendChild(img);

            const badge = document.createElement('span');
            badge.style.position = 'absolute';
            badge.style.bottom = '6px';
            badge.style.left = '6px';
            badge.style.fontSize = '0.7rem';
            badge.style.fontWeight = '700';
            badge.style.padding = '2px 6px';
            badge.style.borderRadius = '4px';
            badge.style.background = 'rgba(234, 88, 12, 0.9)';
            badge.style.color = '#fff';
            badge.textContent = '+ Nouvelle';
            item.appendChild(badge);

            // Bouton Studio IA interactif sur chaque nouvelle photo
            const aiStudioBtn = document.createElement('button');
            aiStudioBtn.type = 'button';
            aiStudioBtn.title = 'Améliorer cette photo avec le Studio Photo IA';
            aiStudioBtn.style.position = 'absolute';
            aiStudioBtn.style.bottom = '6px';
            aiStudioBtn.style.right = '6px';
            aiStudioBtn.style.background = 'linear-gradient(135deg, #9e1b32, #c5223d)';
            aiStudioBtn.style.border = 'none';
            aiStudioBtn.style.color = '#fff';
            aiStudioBtn.style.fontSize = '0.68rem';
            aiStudioBtn.style.fontWeight = '700';
            aiStudioBtn.style.padding = '3px 8px';
            aiStudioBtn.style.borderRadius = '6px';
            aiStudioBtn.style.cursor = 'pointer';
            aiStudioBtn.style.display = 'inline-flex';
            aiStudioBtn.style.alignItems = 'center';
            aiStudioBtn.style.gap = '3px';
            aiStudioBtn.style.zIndex = '3';
            aiStudioBtn.style.boxShadow = '0 2px 8px rgba(158,27,50,0.4)';
            aiStudioBtn.innerHTML = `
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3Z"/></svg>
                <span>Studio IA</span>
            `;
            aiStudioBtn.onclick = function(e) {
                e.stopPropagation();
                window.runAiQualityCheck(file);
            };
            item.appendChild(aiStudioBtn);

            // Bouton rotation 90°
            const rotBtn = document.createElement('button');
            rotBtn.type = 'button';
            rotBtn.title = 'Faire pivoter de 90°';
            rotBtn.setAttribute('aria-label', 'Pivoter');
            rotBtn.innerHTML = '↺';
            rotBtn.style.position = 'absolute';
            rotBtn.style.top = '6px';
            rotBtn.style.left = '6px';
            rotBtn.style.width = '26px';
            rotBtn.style.height = '26px';
            rotBtn.style.background = 'rgba(0, 0, 0, 0.75)';
            rotBtn.style.border = '1px solid rgba(255, 255, 255, 0.25)';
            rotBtn.style.color = '#fff';
            rotBtn.style.borderRadius = '50%';
            rotBtn.style.fontSize = '14px';
            rotBtn.style.display = 'grid';
            rotBtn.style.placeItems = 'center';
            rotBtn.style.cursor = 'pointer';
            rotBtn.style.zIndex = '3';
            rotBtn.onclick = function(e) {
                e.stopPropagation();
                window.rotateEditPhoto(idx);
            };
            item.appendChild(rotBtn);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.title = 'Retirer cette photo';
            delBtn.setAttribute('aria-label', 'Supprimer');
            delBtn.innerHTML = '×';
            delBtn.style.position = 'absolute';
            delBtn.style.top = '6px';
            delBtn.style.right = '6px';
            delBtn.style.width = '26px';
            delBtn.style.height = '26px';
            delBtn.style.background = 'rgba(0, 0, 0, 0.75)';
            delBtn.style.border = '1px solid rgba(255, 255, 255, 0.25)';
            delBtn.style.color = '#fff';
            delBtn.style.borderRadius = '50%';
            delBtn.style.fontSize = '17px';
            delBtn.style.lineHeight = '1';
            delBtn.style.cursor = 'pointer';
            delBtn.style.zIndex = '3';
            delBtn.onclick = function(e) {
                e.stopPropagation();
                window.removeEditPhoto(idx);
            };
            item.appendChild(delBtn);

            previewGrid.appendChild(item);
        });
    }
});
</script>
@endif

<script>
// ==========================================================================
// AI PRODUCT OPTIMIZATION SCRIPT
// ==========================================================================
let lastAiOptimization = null;

async function runAiProductOptimize() {
    const btn = document.getElementById('ai-optimize-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Optimisation en cours (3s)...</span>';

    try {
        const res = await fetch('{{ route('ai.products.optimize', $product) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        const data = await res.json();

        if (data.status === 'success') {
            lastAiOptimization = data;
            const banner = document.getElementById('ai-optimize-banner');
            const preview = document.getElementById('ai-optimize-preview');

            let sellingPointsHtml = '';
            if (data.selling_points && data.selling_points.length) {
                sellingPointsHtml = `<ul style="margin:.3rem 0;padding-left:1.2rem">${data.selling_points.map(p => `<li>${p}</li>`).join('')}</ul>`;
            }

            preview.innerHTML = `
                <div><strong>Titre suggéré :</strong> ${data.optimized_title || ''}</div>
                ${sellingPointsHtml}
                ${data.suggested_compare_price ? `<div><strong>Prix barré recommandé :</strong> ${new Intl.NumberFormat('fr-FR').format(data.suggested_compare_price)} FCFA</div>` : ''}
            `;
            banner.style.display = 'block';
        } else {
            alert(data.message || 'Impossible d\'optimiser ce produit pour le moment.');
        }

    } catch (err) {
        console.error(err);
        alert('Erreur de communication avec le service IA.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function applyAiOptimizations() {
    if (!lastAiOptimization) return;

    if (lastAiOptimization.optimized_title) {
        document.getElementById('title').value = lastAiOptimization.optimized_title;
    }
    if (lastAiOptimization.optimized_description) {
        document.getElementById('description').value = lastAiOptimization.optimized_description;
    }
    if (lastAiOptimization.suggested_compare_price) {
        const compareInput = document.getElementById('compare_at_price');
        if (compareInput) compareInput.value = lastAiOptimization.suggested_compare_price;
    }

    const banner = document.getElementById('ai-optimize-banner');
    banner.style.background = 'rgba(16, 185, 129, 0.12)';
    banner.style.borderColor = '#10b981';
    banner.innerHTML = `<span style="color:#10b981;font-weight:700">✓ Suggestions appliquées au formulaire avec succès ! Cliquez sur "Enregistrer les modifications" pour publier.</span>`;
}
</script>

<!-- ======================================================================= -->
<!-- MODALE STUDIO PHOTO IA PROFESSIONNEL (THÈME ZANIKA)                     -->
<!-- ======================================================================= -->
@include('partials.ai_photo_studio_modal')

<script>
// ==========================================================================
// ZANIKA AI PRODUCT PHOTOGRAPHY STUDIO — MOTEUR INTERACTIF (MODIFICATION)
// ==========================================================================
let currentAiVariations = [];
let currentSourceFile = null;
let activeCompareIndex = 0;

window.triggerAiStudioFromButton = function() {
    const files = typeof window.getSelectedEditFiles === 'function' ? window.getSelectedEditFiles() : [];
    if (files.length > 0) {
        window.runAiQualityCheck(files[0]);
    } else {
        const loadingBox = document.getElementById('ai-quality-loading');
        if (loadingBox) {
            loadingBox.style.display = 'flex';
            const txt = document.getElementById('ai-quality-loading-text');
            if (txt) txt.textContent = "Veuillez d'abord ajouter ou prendre une nouvelle photo pour ouvrir le Studio Photo IA.";
            setTimeout(() => {
                if (loadingBox.style.display === 'flex' && !loadingBox.dataset.loading) {
                    loadingBox.style.display = 'none';
                }
            }, 3500);
        }
        const galleryBtn = document.getElementById('gallery-file-input');
        if (galleryBtn) galleryBtn.click();
    }
};

window.runAiQualityCheck = async function(file) {
    if (!file) {
        const files = typeof window.getSelectedEditFiles === 'function' ? window.getSelectedEditFiles() : [];
        if (files.length > 0) {
            file = files[0];
        } else {
            alert('Veuillez d\'abord ajouter ou prendre une photo du produit.');
            return;
        }
    }

    currentSourceFile = file;

    const loadingBox = document.getElementById('ai-quality-loading');
    const loadingText = document.getElementById('ai-quality-loading-text');
    const triggerBtn = document.getElementById('btn-trigger-ai-studio');

    if (loadingBox) {
        loadingBox.dataset.loading = '1';
        loadingBox.style.display = 'flex';
        if (loadingText) {
            loadingText.textContent = "Studio Photo IA en cours : Détourage haute précision, cadrage 1:1 et création des packshots (1 à 2s)...";
        }
    }
    if (triggerBtn) {
        triggerBtn.disabled = true;
        triggerBtn.innerHTML = `
            <span style="width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;display:inline-block;animation:zanika-spin 0.8s linear infinite"></span>
            <span>Studio en cours...</span>
        `;
    }

    const formData = new FormData();
    formData.append('photo', file);

    const titleVal = document.getElementById('title')?.value;
    if (titleVal && titleVal.trim().length > 0) {
        formData.append('category_hint', titleVal.trim());
    }

    try {
        const res = await fetch('{{ route('ai.products.verify_quality') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await res.json();

        if (data.status === 'success' && data.variations && data.variations.length > 0) {
            currentAiVariations = data.variations;
            window.currentCutoutPath = data.cutout_path || null;
            window.currentSourceUrl = data.source_url || (file ? URL.createObjectURL(file) : '');

            const catHint = document.getElementById('ai-modal-category-hint');
            const catText = document.getElementById('ai-modal-detected-product');
            if (catHint && catText) {
                if (data.product_title) {
                    catHint.style.display = 'flex';
                    catText.textContent = data.product_title + ' (' + (data.category || 'e-commerce') + ')';
                } else {
                    catHint.style.display = 'flex';
                    catText.textContent = (data.category || 'Article') + ' • Prêt pour la vente';
                }
            }

            const scorePill = document.getElementById('ai-modal-quality-score-pill');
            if (scorePill && data.quality_audit) {
                scorePill.textContent = '✓ ' + (data.quality_audit.score || 98) + '% Qualité E-commerce Pro';
            }

            window.renderAiStudioModal();
        } else {
            alert(data.error || 'Impossible de traiter la photo avec le Studio IA pour le moment.');
        }
    } catch (err) {
        console.error('Erreur Studio IA:', err);
        alert('Erreur lors de la communication avec le Studio Photo IA Zanika.');
    } finally {
        if (loadingBox) {
            delete loadingBox.dataset.loading;
            loadingBox.style.display = 'none';
        }
        if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3Z"/></svg>
                <span>Améliorer avec l'IA</span>
            `;
        }
    }
};

window.switchAiStudioTab = function(tab) {
    const viewCompare = document.getElementById('ai-studio-view-compare');
    const viewGallery = document.getElementById('ai-studio-view-gallery');
    const btnCompare = document.getElementById('tab-btn-compare');
    const btnGallery = document.getElementById('tab-btn-gallery');

    if (tab === 'compare') {
        if (viewCompare) viewCompare.style.display = 'block';
        if (viewGallery) viewGallery.style.display = 'none';
        if (btnCompare) {
            btnCompare.style.background = 'rgba(255,255,255,0.12)';
            btnCompare.style.borderColor = 'rgba(255,255,255,0.2)';
            btnCompare.style.color = '#ffffff';
        }
        if (btnGallery) {
            btnGallery.style.background = 'transparent';
            btnGallery.style.borderColor = 'transparent';
            btnGallery.style.color = 'var(--text-soft, #a1a1aa)';
        }
    } else {
        if (viewCompare) viewCompare.style.display = 'none';
        if (viewGallery) viewGallery.style.display = 'block';
        if (btnGallery) {
            btnGallery.style.background = 'rgba(255,255,255,0.12)';
            btnGallery.style.borderColor = 'rgba(255,255,255,0.2)';
            btnGallery.style.color = '#ffffff';
        }
        if (btnCompare) {
            btnCompare.style.background = 'transparent';
            btnCompare.style.borderColor = 'transparent';
            btnCompare.style.color = 'var(--text-soft, #a1a1aa)';
        }
    }
};

window.selectCompareStyle = function(index) {
    if (index < 0 || index >= currentAiVariations.length) return;
    activeCompareIndex = index;
    const item = currentAiVariations[index];

    currentAiVariations.forEach((v, i) => {
        v.selected = (i === index);
    });

    const targetImg = document.getElementById('ai-compare-target-img');
    const badge = document.getElementById('ai-compare-style-badge');
    const title = document.getElementById('ai-compare-style-title');
    const confirmBtnText = document.getElementById('btn-confirm-text');

    if (targetImg) targetImg.src = item.url;
    if (badge) badge.textContent = item.badge || 'Studio Pro';
    if (title) title.textContent = item.title;
    if (confirmBtnText) {
        confirmBtnText.textContent = item.is_source ? 'Conserver cette photo originale' : 'Utiliser ce packshot (' + item.title + ')';
    }

    const container = document.getElementById('ai-compare-style-buttons');
    if (container) {
        const buttons = container.querySelectorAll('button');
        buttons.forEach((btn, bIdx) => {
            if (bIdx === index) {
                btn.style.background = 'linear-gradient(135deg, #9e1b32, #c5223d)';
                btn.style.borderColor = '#c5223d';
                btn.style.color = '#ffffff';
                btn.style.fontWeight = '700';
            } else {
                btn.style.background = 'rgba(255,255,255,0.06)';
                btn.style.borderColor = 'rgba(255,255,255,0.12)';
                btn.style.color = 'var(--text, #fff)';
                btn.style.fontWeight = '500';
            }
        });
    }

    window.renderAiGalleryGrid();
};

window.renderAiStudioModal = function() {
    const modal = document.getElementById('ai-variations-modal');
    if (!modal) return;

    const sourceVar = currentAiVariations.find(v => v.is_source);
    const sourceImg = document.getElementById('ai-compare-source-img');
    if (sourceImg) {
        sourceImg.src = sourceVar ? sourceVar.url : window.currentSourceUrl;
    }

    let initialIndex = currentAiVariations.findIndex(v => !v.is_source);
    if (initialIndex < 0) initialIndex = 0;

    const styleBtnBox = document.getElementById('ai-compare-style-buttons');
    if (styleBtnBox) {
        styleBtnBox.innerHTML = '';
        currentAiVariations.forEach((item, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.padding = '6px 12px';
            btn.style.borderRadius = '20px';
            btn.style.border = '1px solid rgba(255,255,255,0.12)';
            btn.style.background = 'rgba(255,255,255,0.06)';
            btn.style.color = '#fff';
            btn.style.fontSize = '0.78rem';
            btn.style.cursor = 'pointer';
            btn.style.display = 'inline-flex';
            btn.style.alignItems = 'center';
            btn.style.gap = '5px';
            btn.style.transition = 'all .15s ease';

            btn.innerHTML = `<span>${item.badge || item.title}</span>`;
            btn.onclick = function() {
                window.selectCompareStyle(idx);
            };
            styleBtnBox.appendChild(btn);
        });
    }

    window.renderAiGalleryGrid();
    window.selectCompareStyle(initialIndex);
    window.switchAiStudioTab('compare');

    modal.style.display = 'flex';
};

window.renderAiGalleryGrid = function() {
    const grid = document.getElementById('ai-variations-grid');
    if (!grid) return;

    grid.innerHTML = '';

    currentAiVariations.forEach((item, idx) => {
        const card = document.createElement('div');
        card.className = 'ai-var-card';
        card.style.position = 'relative';
        card.style.aspectRatio = '1 / 1';
        card.style.borderRadius = '16px';
        card.style.overflow = 'hidden';
        card.style.background = '#ffffff';
        card.style.cursor = 'pointer';
        card.style.boxSizing = 'border-box';
        card.style.transition = 'all .2s ease';
        card.style.border = item.selected ? '2.5px solid #c5223d' : '1px solid rgba(255,255,255,0.15)';
        card.style.boxShadow = item.selected 
            ? '0 0 0 1px #c5223d, 0 8px 24px rgba(197, 34, 61, 0.4)' 
            : '0 4px 14px rgba(0,0,0,0.25)';

        card.onclick = function() {
            window.selectCompareStyle(idx);
        };

        const eyeBtn = document.createElement('button');
        eyeBtn.type = 'button';
        eyeBtn.title = 'Agrandir en haute résolution';
        eyeBtn.style.position = 'absolute';
        eyeBtn.style.top = '8px';
        eyeBtn.style.left = '8px';
        eyeBtn.style.width = '30px';
        eyeBtn.style.height = '30px';
        eyeBtn.style.borderRadius = '50%';
        eyeBtn.style.background = 'rgba(0,0,0,0.65)';
        eyeBtn.style.border = '1px solid rgba(255,255,255,0.2)';
        eyeBtn.style.color = '#ffffff';
        eyeBtn.style.display = 'grid';
        eyeBtn.style.placeItems = 'center';
        eyeBtn.style.cursor = 'pointer';
        eyeBtn.style.zIndex = '4';
        eyeBtn.style.backdropFilter = 'blur(4px)';
        eyeBtn.innerHTML = `
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        `;
        eyeBtn.onclick = function(e) {
            e.stopPropagation();
            window.zoomAiVariation(item.url);
        };
        card.appendChild(eyeBtn);

        const badgeSpan = document.createElement('span');
        badgeSpan.style.position = 'absolute';
        badgeSpan.style.top = '8px';
        badgeSpan.style.left = '44px';
        badgeSpan.style.fontSize = '0.72rem';
        badgeSpan.style.fontWeight = '700';
        badgeSpan.style.padding = '3px 8px';
        badgeSpan.style.borderRadius = '6px';
        badgeSpan.style.backdropFilter = 'blur(4px)';
        badgeSpan.style.zIndex = '4';

        if (item.is_source) {
            badgeSpan.style.background = 'rgba(0,0,0,0.75)';
            badgeSpan.style.color = '#ffffff';
            badgeSpan.textContent = 'Photo source';
        } else {
            badgeSpan.style.background = 'linear-gradient(135deg, rgba(158, 27, 50, 0.95), rgba(197, 34, 61, 0.95))';
            badgeSpan.style.color = '#ffffff';
            badgeSpan.style.boxShadow = '0 2px 6px rgba(158, 27, 50, 0.35)';
            badgeSpan.textContent = item.badge || 'Studio Pro';
        }
        card.appendChild(badgeSpan);

        const checkBadge = document.createElement('div');
        checkBadge.style.position = 'absolute';
        checkBadge.style.top = '8px';
        checkBadge.style.right = '8px';
        checkBadge.style.width = '26px';
        checkBadge.style.height = '26px';
        checkBadge.style.borderRadius = '50%';
        checkBadge.style.display = 'grid';
        checkBadge.style.placeItems = 'center';
        checkBadge.style.zIndex = '4';

        if (item.selected) {
            checkBadge.style.background = 'linear-gradient(135deg, #9e1b32, #c5223d)';
            checkBadge.style.color = '#ffffff';
            checkBadge.style.boxShadow = '0 2px 8px rgba(158, 27, 50, 0.45)';
            checkBadge.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            `;
        } else {
            checkBadge.style.background = 'rgba(0,0,0,0.45)';
            checkBadge.style.border = '2px solid rgba(255,255,255,0.7)';
            checkBadge.innerHTML = '';
        }
        card.appendChild(checkBadge);

        const img = document.createElement('img');
        img.src = item.url;
        img.alt = item.title;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.display = 'block';
        card.appendChild(img);

        const titleBar = document.createElement('div');
        titleBar.style.position = 'absolute';
        titleBar.style.bottom = '0';
        titleBar.style.insetInline = '0';
        titleBar.style.padding = '8px 10px';
        titleBar.style.background = 'linear-gradient(to top, rgba(0,0,0,0.85), transparent)';
        titleBar.style.color = '#ffffff';
        titleBar.style.fontSize = '0.78rem';
        titleBar.style.fontWeight = '600';
        titleBar.style.zIndex = '3';
        titleBar.textContent = item.title;
        card.appendChild(titleBar);

        grid.appendChild(card);
    });

    const addCard = document.createElement('div');
    addCard.id = 'ai-card-generate-another';
    addCard.style.aspectRatio = '1 / 1';
    addCard.style.borderRadius = '16px';
    addCard.style.border = '2px dashed rgba(197, 34, 61, 0.45)';
    addCard.style.background = 'rgba(197, 34, 61, 0.05)';
    addCard.style.display = 'flex';
    addCard.style.flexDirection = 'column';
    addCard.style.alignItems = 'center';
    addCard.style.justifyContent = 'center';
    addCard.style.gap = '.55rem';
    addCard.style.cursor = 'pointer';
    addCard.style.padding = '.75rem';
    addCard.style.textAlign = 'center';
    addCard.style.transition = 'all .2s ease';

    addCard.innerHTML = `
        <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg, #9e1b32, #c5223d);display:grid;place-items:center;color:#fff;box-shadow:0 4px 12px rgba(158,27,50,0.35);flex-shrink:0">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <strong style="color:#f43f5e;font-size:.9rem;display:block">Autre ambiance studio</strong>
        <span style="color:var(--text-soft, #a1a1aa);font-size:.78rem">Studio Carbone • 6 crédits</span>
    `;

    addCard.onclick = function() {
        window.requestAnotherAiVariant(null, 'studio_dark');
    };

    grid.appendChild(addCard);
};

window.requestAnotherAiVariant = async function(basePath, chosenStyle = 'studio_dark') {
    const retryBtn = document.getElementById('btn-retry-variant');
    const originalRetryHtml = retryBtn ? retryBtn.innerHTML : '';
    if (retryBtn) {
        retryBtn.disabled = true;
        retryBtn.innerHTML = `
            <span style="width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;display:inline-block;animation:zanika-spin 0.8s linear infinite"></span>
            <span>Rendu (1s)...</span>
        `;
    }

    try {
        const formData = new FormData();
        if (window.currentCutoutPath) {
            formData.append('cutout_path', window.currentCutoutPath);
        }
        formData.append('style', chosenStyle);
        if (basePath) {
            formData.append('photo_path', basePath);
        } else if (currentSourceFile) {
            formData.append('photo', currentSourceFile);
        }

        const res = await fetch('{{ route('ai.products.generate_variant') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await res.json();
        if (data.status === 'success' && data.variation) {
            const sourceIndex = currentAiVariations.findIndex(v => v.is_source);
            if (sourceIndex >= 0) {
                currentAiVariations.splice(sourceIndex, 0, data.variation);
            } else {
                currentAiVariations.push(data.variation);
            }

            const newIndex = sourceIndex >= 0 ? sourceIndex : currentAiVariations.length - 1;
            window.renderAiStudioModal();
            window.selectCompareStyle(newIndex);
        } else {
            alert(data.error || 'Impossible de générer une autre variante.');
        }
    } catch (err) {
        console.error(err);
        alert('Erreur lors de la création de la variante studio.');
    } finally {
        if (retryBtn) {
            retryBtn.disabled = false;
            retryBtn.innerHTML = originalRetryHtml;
        }
    }
};

window.confirmAiPhotoSelection = async function() {
    const selected = currentAiVariations.find(v => v.selected) || currentAiVariations[activeCompareIndex];
    if (!selected) {
        alert('Veuillez sélectionner un packshot.');
        return;
    }

    const btn = document.getElementById('btn-confirm-ai-selection');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerText = 'Application au produit...';

    try {
        if (selected.is_source && currentSourceFile) {
            if (typeof window.setSelectedEditFiles === 'function') {
                window.setSelectedEditFiles([currentSourceFile]);
            }
        } else {
            const response = await fetch(selected.url);
            const blob = await response.blob();
            const fileName = 'packshot_studio_' + Date.now() + '.jpg';
            const studioFile = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });

            if (typeof window.setSelectedEditFiles === 'function') {
                window.setSelectedEditFiles([studioFile]);
            }

            const hiddenPath = document.getElementById('ai_temp_photo_path');
            if (hiddenPath && selected.path) {
                hiddenPath.value = selected.path;
            }
        }

        window.closeAiVariationsModal();

        alert('Packshot professionnel appliqué ! Cliquez sur "Enregistrer les modifications" pour enregistrer.');
    } catch (err) {
        console.error(err);
        alert('Erreur lors de l\'intégration de la photo choisie.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
};

window.keepOriginalPhoto = function() {
    window.closeAiVariationsModal();
};

window.closeAiVariationsModal = function() {
    const modal = document.getElementById('ai-variations-modal');
    if (modal) modal.style.display = 'none';
};

window.zoomAiVariation = function(url) {
    const img = document.getElementById('ai-lightbox-img');
    const box = document.getElementById('ai-photo-lightbox');
    if (img && box) {
        img.src = url;
        box.style.display = 'flex';
    }
};

window.closeAiLightbox = function() {
    const box = document.getElementById('ai-photo-lightbox');
    if (box) box.style.display = 'none';
};
</script>
@include('partials.product_live_preview_modal')
@endsection
