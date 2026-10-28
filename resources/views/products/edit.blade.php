@extends('layouts.app')

@section('title', 'Modifier : '.$product->title.' - '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Modifier le produit</h1>
        </div>
        <div>
            <form method="POST" action="{{ route('products.destroy', $product) }}" style="display:inline" onsubmit="return confirm('Archiver ce produit ? Il ne sera plus proposé sur la boutique.');">
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
        
        <div class="dash-grid" style="grid-template-columns: minmax(320px, 2fr) minmax(300px, 1fr);">
            
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Détails produit -->
                <section class="card panel">
                    <h3>Détails du produit</h3>
                    
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
                                    <button type="button" onclick="if(confirm('Supprimer cette photo ?')) document.getElementById('delete-img-{{ $img->id }}').submit();" title="Supprimer" style="display:grid;place-items:center"><x-icon name="x" size="14" /></button>
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
                            <p style="font-size:.85rem;color:var(--text-soft);margin-bottom:1rem">
                                Vous pouvez encore ajouter jusqu'à {{ $remainingPhotos }} photo(s).
                            </p>

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
                        <label for="status">Statut du produit</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', $product->status) === 'active')>Actif</option>
                            <option value="hidden" @selected(old('status', $product->status) === 'hidden')>Masqué</option>
                            <option value="archived" @selected(old('status', $product->status) === 'archived')>Archivé</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem">Mettre à jour le produit</button>
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
            badge.style.background = 'rgba(234, 88, 12, 0.9)';
            badge.style.color = '#fff';
            badge.textContent = '+ Nouvelle';
            item.appendChild(badge);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.title = 'Retirer cette photo';
            delBtn.innerHTML = '×';
            delBtn.style.fontSize = '18px';
            delBtn.style.lineHeight = '1';
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
@endsection
