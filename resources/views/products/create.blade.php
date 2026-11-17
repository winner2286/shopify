@extends('layouts.app')

@section('title', 'Nouveau Produit : '.config('platform.name'))

@section('content')
<div class="container dash product-editor">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Ajouter un produit</h1>
        </div>
        <div style="display:flex;gap:.6rem;align-items:center">
            <button type="button" class="btn btn-ghost" onclick="openProductLivePreview()" style="display:inline-flex;align-items:center;gap:.45rem;font-weight:700;border:1px solid var(--border);border-radius:10px;padding:.55rem .95rem;background:var(--surface)">
                <x-icon name="eye" size="17" />
                <span>Aperçu de la fiche vitrine</span>
            </button>
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
        <input type="hidden" name="ai_temp_photo_path" id="ai_temp_photo_path" value="">

        <!-- BANNIÈRE IA : GÉNÉRATION AUTOMATIQUE DE FICHE DEPUIS UNE PHOTO (MODULE 4 & 5) -->
        <div class="ai-product-vision-card" style="background:linear-gradient(135deg, rgba(225, 29, 72, 0.08), rgba(124, 58, 237, 0.08));border:1px solid rgba(225, 29, 72, 0.35);border-radius:14px;padding:1.25rem;margin-bottom:1.5rem">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#e11d48,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 4px 14px rgba(225,29,72,0.35);flex-shrink:0">
                        <x-icon name="sparkles" :size="22" />
                    </div>
                    <div>
                        <strong style="font-size:1.05rem;color:var(--text);display:block">Remplir automatiquement depuis une photo avec l'IA</strong>
                        <span style="font-size:.85rem;color:var(--text-soft)">Déposez la photo d'un article : l'IA identifie le produit, rédige le titre et la description, suggère la catégorie et calcule le prix en FCFA.</span>
                    </div>
                </div>
                <label for="ai-photo-input" class="btn btn-sm" id="btn-trigger-ai-photo" style="background:linear-gradient(135deg,#e11d48,#be123c);color:#fff;font-weight:700;border:none;padding:.55rem 1.1rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem">
                    <x-icon name="sparkles" size="14" />
                    <span>Déposer une photo</span>
                </label>
            </div>

            <input type="file" id="ai-photo-input" accept="image/*" style="position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;width:1px;height:1px;" onchange="previewAiPhoto(this)">

            <!-- Dropzone escamotable -->
            <div id="ai-vision-dropzone-wrap" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.08)">
                <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">
                    <div>
                        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                            <label for="ai-photo-input" class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-flex;align-items:center;gap:.35rem">
                                <x-icon name="camera" size="14" />
                                <span>Changer de photo</span>
                            </label>
                            <span id="ai-selected-filename" style="font-size:.82rem;color:var(--text-soft)"></span>
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <input type="text" id="ai-user-hint" class="input" placeholder="Indication facultative (ex: Robe de soirée en tissu pagne wax)..." style="font-size:.85rem">
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;align-items:center;justify-content:center">
                        <div id="ai-photo-thumb-preview" style="width:80px;height:80px;border-radius:8px;border:1px dashed var(--border);display:grid;place-items:center;overflow:hidden;background:rgba(0,0,0,0.2)">
                            <span style="font-size:.7rem;color:var(--text-soft)">Aperçu</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="ai-analyze-submit-btn" onclick="runAiVisionAnalysis()" style="width:100%">
                            Analyser avec l'IA
                        </button>
                    </div>
                </div>
                <div id="ai-vision-status-msg" style="display:none;margin-top:.75rem;font-size:.85rem;color:#10b981"></div>
            </div>
        </div>

        <div class="product-form-grid">
            
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
                    <p style="font-size:.9rem;color:var(--text-soft);margin-bottom:1rem">
                        La première photo sera la photo principale de votre fiche produit. Vous pouvez prendre une photo directement avec votre appareil ou choisir depuis votre galerie.
                    </p>

                    <!-- Bannière Retouche & Ajustement automatique Studio -->
                    <div style="background:rgba(234, 88, 12, 0.08);border:1px solid rgba(234, 88, 12, 0.25);border-radius:12px;padding:.75rem 1rem;margin-bottom:1.1rem;display:flex;align-items:center;gap:.75rem">
                        <div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg, #ea580c, #f97316);display:grid;place-items:center;color:#fff;flex-shrink:0">
                            <x-icon name="sparkles" size="18" />
                        </div>
                        <div style="font-size:.85rem;line-height:1.35">
                            <strong style="color:var(--text);display:block">Ajustement & Retouche automatique activés</strong>
                            <span style="color:var(--text-soft)">La luminosité est rééquilibrée, le contraste renforcé et le produit cadré sur fond studio blanc pour une présentation professionnelle.</span>
                        </div>
                    </div>

                    <!-- Boutons d'action : Appareil photo & Galerie (Labels natifs HTML pour compatibilité iOS & Android 100%) -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.75rem;margin-bottom:1rem">
                        <label for="camera-file-input" id="btn-snap-camera" class="btn" style="background:linear-gradient(135deg, #ea580c, #f97316);color:#fff;border:none;display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.9rem 1.2rem;font-weight:600;border-radius:12px;box-shadow:0 4px 14px rgba(234, 88, 12, 0.35);cursor:pointer;transition:transform .15s, box-shadow .15s;">
                            <x-icon name="camera" size="22" />
                            <span>Prendre une photo</span>
                        </label>

                        <label for="gallery-file-input" id="btn-open-gallery" class="btn btn-ghost" style="border:1px solid var(--border);background:var(--bg-soft);color:var(--text);display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.9rem 1.2rem;font-weight:600;border-radius:12px;cursor:pointer;transition:border-color .15s, background .15s;">
                            <x-icon name="gallery" size="22" style="color:var(--brand-primary)" />
                            <span>Choisir dans la galerie</span>
                        </label>
                    </div>

                    <!-- Zone de glisser-déposer / dropzone desktop -->
                    <label for="gallery-file-input" id="photo-dropzone" style="border:2px dashed var(--border);border-radius:12px;padding:1.4rem 1rem;text-align:center;background:rgba(255,255,255,0.015);cursor:pointer;transition:all .2s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4rem;width:100%;box-sizing:border-box;">
                        <div style="color:var(--text-soft);display:flex;align-items:center;gap:.5rem;font-size:.92rem;font-weight:500">
                            <x-icon name="upload" size="20" style="color:var(--brand-primary)" />
                            <span>Ou glissez-déposez vos photos ici</span>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-faint)">Formats acceptés : JPG, PNG, WebP — 5 Mo max par photo</div>
                    </label>

                    <!-- Inputs fichiers masqués mais actifs dans le DOM (reconnus par iOS Safari et Android) -->
                    <input type="file" id="camera-file-input" accept="image/*" capture="environment" onchange="window.handleProductPhotoInput && window.handleProductPhotoInput(this)" style="position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;width:1px;height:1px;">
                    <input type="file" id="gallery-file-input" multiple accept="image/jpeg,image/png,image/webp,image/*" onchange="window.handleProductPhotoInput && window.handleProductPhotoInput(this)" style="position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;width:1px;height:1px;">
                    <input type="file" id="master-photos-input" name="photos[]" multiple style="position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;width:1px;height:1px;">

                    <!-- Message d'alerte limite -->
                    <div id="photo-limit-warning" class="alert alert-warn" style="display:none;margin-top:1rem;font-size:.85rem;padding:.6rem .9rem;">
                        Limite atteinte : Vous avez sélectionné le maximum de 8 photos.
                    </div>

                    <!-- Grille de prévisualisation des photos sélectionnées -->
                    <div id="photos-preview-grid" class="gallery-grid" style="display:none;margin-top:1.2rem"></div>

                    <!-- Bannière / Retoucher une photo (Zanika Luxury Theme) -->
                    <div id="ai-quality-card" class="photo-studio-entry"><div><strong>Une photo pr?te pour votre catalogue</strong><p>Conservez le d?cor ou choisissez un nouveau fond. Vous validez le r?sultat avant de l?utiliser.</p></div><button type="button" onclick="triggerAiStudioFromButton()" id="btn-trigger-ai-studio" class="btn btn-primary">Ouvrir le studio photo</button></div>

                    <!-- Indicateur de chargement IA -->
                    <div id="ai-quality-loading" style="display:none;margin-top:.75rem;padding:.85rem 1.1rem;background:rgba(158,27,50,0.08);border:1px solid rgba(158,27,50,0.3);border-radius:12px;color:var(--text);font-size:.88rem;align-items:center;gap:.75rem">
                        <span style="width:18px;height:18px;border:2.5px solid #c5223d;border-top-color:transparent;border-radius:50%;display:inline-block;animation:zanika-spin 0.8s linear infinite;flex-shrink:0"></span>
                        <span id="ai-quality-loading-text">Ajoutez une photo, puis choisissez le rendu souhait? dans le studio.</span>
                    </div>
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
                        <label for="status">Statut de publication</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', 'active') === 'active')>Actif (visible immédiatement sur la boutique)</option>
                            <option value="hidden" @selected(old('status') === 'hidden')>Masqué (enregistrer en brouillon privé)</option>
                        </select>
                        <div class="hint" style="font-size:.78rem;color:var(--text-soft);margin-top:.35rem">
                            Un produit en brouillon est sauvegardé sans être visible par vos clients sur la vitrine.
                        </div>
                    </div>

                    <!-- Actions d'enregistrement et de prévisualisation -->
                    <div style="display:flex;flex-direction:column;gap:.75rem;margin-top:1.5rem">
                        <button type="button" class="btn btn-ghost" onclick="openProductLivePreview()" style="width:100%;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;gap:.5rem;font-weight:700;padding:.85rem;background:var(--bg-soft);border-radius:10px">
                            <x-icon name="eye" size="18" />
                            <span>Prévisualiser avant de publier</span>
                        </button>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                            <button type="button" class="btn btn-ghost" onclick="confirmProductSubmission('hidden')" style="font-size:.84rem;padding:.75rem .4rem;text-align:center;border-radius:10px;border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                                <x-icon name="file-text" size="16" />
                                <span>Enregistrer en brouillon</span>
                            </button>
                            <button type="button" class="btn btn-primary" onclick="confirmProductSubmission('active')" style="background:linear-gradient(135deg, #10b981, #059669);border-color:#059669;font-size:.84rem;padding:.75rem .4rem;font-weight:700;text-align:center;border-radius:10px;box-shadow:0 3px 12px rgba(16,185,129,0.3);display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                                <x-icon name="check-circle" size="16" />
                                <span>Publier en ligne</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </form>
</div>

<style>
.zanika-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    cursor: pointer;
    flex-shrink: 0;
}
.zanika-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.zanika-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(255, 255, 255, 0.16);
    transition: .25s ease;
    border-radius: 34px;
    border: 1px solid var(--border);
}
.zanika-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: #ffffff;
    transition: .25s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}
.zanika-switch input:checked + .zanika-slider {
    background: linear-gradient(135deg, #9e1b32, #c5223d);
    border-color: #c5223d;
    box-shadow: 0 0 12px rgba(158, 27, 50, 0.4);
}
.zanika-switch input:checked + .zanika-slider:before {
    transform: translateX(22px);
}
@keyframes zanika-spin {
    to { transform: rotate(360deg); }
}
</style>

<!-- ======================================================================= -->
<!-- MODALE STUDIO PHOTO IA PROFESSIONNEL (THÈME ZANIKA)                     -->
<!-- ======================================================================= -->
@include('partials.ai_photo_studio_modal')
@include('partials.product_live_preview_modal')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const maxPhotos = 8;
    const compressThresholdBytes = Math.round(4.5 * 1024 * 1024); // Compression auto au-delà de 4,5 Mo
    const hardMaxSizeBytes = 15 * 1024 * 1024; // 15 Mo max brut avant compression
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

    window.handleProductPhotoInput = function(input) {
        if (!input || !input.files || input.files.length === 0) return;
        const files = Array.from(input.files);
        input.value = '';
        handleNewFiles(files);
    };

    // Clic sur "Prendre une photo" (Label natif HTML pour iOS Safari et Android)
    if (btnCamera && cameraInput) {
        btnCamera.addEventListener('click', function(e) {
            if (selectedFiles.length >= maxPhotos) {
                e.preventDefault();
                alert('Vous avez déjà atteint la limite de ' + maxPhotos + ' photos.');
                return;
            }
        });

        cameraInput.addEventListener('change', function() {
            window.handleProductPhotoInput(this);
        });
    }

    // Clic sur "Choisir dans la galerie" (Label natif HTML pour explorateur de fichiers)
    if (btnGallery && galleryInput) {
        btnGallery.addEventListener('click', function(e) {
            if (selectedFiles.length >= maxPhotos) {
                e.preventDefault();
                alert('Vous avez déjà atteint la limite de ' + maxPhotos + ' photos.');
                return;
            }
        });

        galleryInput.addEventListener('change', function() {
            window.handleProductPhotoInput(this);
        });
    }

    // Dropzone desktop & mobile (Label natif pour galerie)
    if (dropzone) {
        dropzone.addEventListener('click', function(e) {
            if (selectedFiles.length >= maxPhotos) {
                e.preventDefault();
                alert('Vous avez déjà atteint la limite de ' + maxPhotos + ' photos.');
                return;
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

    function isAcceptedImageFile(file) {
        if (!file) return false;
        if (file.type && file.type.startsWith('image/')) return true;
        return /\.(jpe?g|png|webp|heic|heif|avif|gif)$/i.test(file.name || '');
    }

    function prepareImageFile(file) {
        return new Promise((resolve) => {
            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = function() {
                const maxDim = 1200;
                const scale = Math.min(1, maxDim / Math.max(img.width || 1, img.height || 1));
                const w = Math.max(1, Math.round((img.width || 800) * scale));
                const h = Math.max(1, Math.round((img.height || 800) * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                const exportMime = /image\/(png|webp)/i.test(file.type || '') ? 'image/png' : 'image/jpeg';
                if (exportMime === 'image/jpeg') {ctx.fillStyle = '#ffffff';ctx.fillRect(0, 0, w, h);}
                ctx.drawImage(img, 0, 0, w, h);

                const baseName = (file.name || 'photo').replace(/\.[^/.]+$/, '');
                const exportAt = (quality) => {
                    canvas.toBlob(function(blob) {
                        if (blob && blob.size > compressThresholdBytes && quality > 0.75) {
                            exportAt(0.72);
                            return;
                        }
                        URL.revokeObjectURL(url);
                        if (blob && blob.size <= 5 * 1024 * 1024) {
                            resolve(new File([blob], baseName + (exportMime === 'image/png' ? '.png' : '.jpg'), {
                                type: exportMime,
                                lastModified: Date.now()
                            }));
                        } else if (file.size <= 5 * 1024 * 1024) {
                            resolve(file);
                        } else {
                            alert('La photo "' + file.name + '" dépasse la taille maximale autorisée (5 Mo).');
                            resolve(null);
                        }
                    }, exportMime, quality);
                };

                exportAt(0.88);
            };

            img.onerror = function() {
                URL.revokeObjectURL(url);
                const isHeicLike = /image\/(hei[cf])|\.(hei[cf])$/i.test((file.type || '') + ' ' + (file.name || ''));
                if (!isHeicLike && file.size <= 5 * 1024 * 1024) {
                    resolve(file);
                } else {
                    alert('Ce format photo mobile (' + (file.name || 'HEIC') + ') n\'a pas pu être converti par le navigateur. Utilisez JPG, PNG ou WebP.');
                    resolve(null);
                }
            };

            img.src = url;
        });
    }

    window.prepareProductPhotoForAi = prepareImageFile;

    // Traitement des nouveaux fichiers ajoutés
    async function handleNewFiles(files) {
        let added = 0;
        let firstAddedFile = null;

        for (const file of files) {
            if (!isAcceptedImageFile(file)) {
                alert('Le fichier "' + file.name + '" n\'est pas une image valide.');
                continue;
            }
            if (file.size > hardMaxSizeBytes) {
                alert('La photo "' + file.name + '" dépasse 15 Mo et ne peut pas être importée.');
                continue;
            }
            if (selectedFiles.length >= maxPhotos) {
                alert('Limite de ' + maxPhotos + ' photos atteinte.');
                break;
            }

            const prepared = await prepareImageFile(file);
            if (!prepared) continue;
            if (selectedFiles.length >= maxPhotos) {
                alert('Limite de ' + maxPhotos + ' photos atteinte.');
                break;
            }

            selectedFiles.push(prepared);
            if (!firstAddedFile) firstAddedFile = prepared;
            added++;
        }

        if (added > 0) {
            syncMasterInput();
            renderPreviews();
            if (previewGrid) {
                previewGrid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Si le contrôle qualité IA est activé, lancer la vérification comparative
            const toggleQuality = document.getElementById('toggle-ai-quality');
            if (toggleQuality && toggleQuality.checked && firstAddedFile) {
                window.runAiQualityCheck(firstAddedFile);
            }
        }
    }

    // Expose helpers pour la sélection des photos IA
    window.getSelectedFiles = function() { return selectedFiles; };
    window.setSelectedFiles = function(files) {
        selectedFiles = files;
        syncMasterInput();
        renderPreviews();
    };

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

    // Rotation d'une photo à 90° (utile si prise de vue smartphone de travers)
    window.rotateProductPhoto = function(index) {
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
            const exportMime = /image\/(png|webp)/i.test(file.type || '') ? 'image/png' : 'image/jpeg';
            canvas.toBlob(function(blob) {
                if (blob) {
                    const rotatedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + (exportMime === 'image/png' ? '.png' : '.jpg'), {
                        type: exportMime,
                        lastModified: Date.now()
                    });
                    selectedFiles[index] = rotatedFile;
                    syncMasterInput();
                    renderPreviews();
                }
                URL.revokeObjectURL(url);
            }, exportMime, 0.92);
        };
        img.src = url;
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

            // Badge principale ou numéro
            const badge = document.createElement('span');
            badge.style.position = 'absolute';
            badge.style.bottom = '6px';
            badge.style.left = '6px';
            badge.style.fontSize = '0.7rem';
            badge.style.fontWeight = '700';
            badge.style.padding = '2px 6px';
            badge.style.borderRadius = '4px';
            badge.style.backdropFilter = 'blur(6px)';
            badge.style.zIndex = '2';

            if (idx === 0) {
                badge.style.background = 'rgba(234, 88, 12, 0.95)';
                badge.style.color = '#fff';
                badge.textContent = '★ Principale';
            } else {
                badge.style.background = 'rgba(0, 0, 0, 0.75)';
                badge.style.color = '#fff';
                badge.textContent = '#' + (idx + 1);
            }
            item.appendChild(badge);

            // Bouton Studio IA interactif sur chaque photo
            const aiStudioBtn = document.createElement('button');
            aiStudioBtn.type = 'button';
            aiStudioBtn.title = 'Améliorer cette photo avec le Studio Photo IA';
            aiStudioBtn.style.position = 'absolute';
            aiStudioBtn.style.top = 'auto';
            aiStudioBtn.style.bottom = '6px';
            aiStudioBtn.style.right = '6px';
            aiStudioBtn.style.width = 'auto';
            aiStudioBtn.style.height = 'auto';
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
            rotBtn.style.right = 'auto';
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
                window.rotateProductPhoto(idx);
            };
            item.appendChild(rotBtn);

            // Bouton supprimer avec croix
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.title = 'Supprimer cette photo';
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
                window.removeProductPhoto(idx);
            };
            item.appendChild(delBtn);

            previewGrid.appendChild(item);
        });
    }

    // Glisser-déposer sur la bannière IA Vision
    const aiVisionCard = document.querySelector('.ai-product-vision-card');
    const aiVisionInput = document.getElementById('ai-photo-input');
    if (aiVisionCard && aiVisionInput) {
        ['dragenter', 'dragover'].forEach(name => {
            aiVisionCard.addEventListener(name, function(e) {
                e.preventDefault();
                e.stopPropagation();
                aiVisionCard.style.borderColor = '#e11d48';
                aiVisionCard.style.background = 'rgba(225, 29, 72, 0.15)';
            }, false);
        });
        ['dragleave', 'drop'].forEach(name => {
            aiVisionCard.addEventListener(name, function(e) {
                e.preventDefault();
                e.stopPropagation();
                aiVisionCard.style.borderColor = 'rgba(225, 29, 72, 0.35)';
                aiVisionCard.style.background = 'linear-gradient(135deg, rgba(225, 29, 72, 0.08), rgba(124, 58, 237, 0.08))';
            }, false);
        });
        aiVisionCard.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                const f = dt.files[0];
                if (f.type.startsWith('image/')) {
                    try {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(f);
                        aiVisionInput.files = dataTransfer.files;
                    } catch (err) {}
                    window.previewAiPhoto({ files: [f] });
                } else {
                    alert('Le fichier glissé n\'est pas une image valide.');
                }
            }
        });
    }
});

// ==========================================================================
// AI PHOTO-TO-PRODUCT VISION LOGIC
// ==========================================================================
window.toggleAiVisionDropzone = function() {
    const wrap = document.getElementById('ai-vision-dropzone-wrap');
    if (!wrap) return;
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
};

window.previewAiPhoto = function(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const wrap = document.getElementById('ai-vision-dropzone-wrap');
        if (wrap) wrap.style.display = 'block';

        const nameLabel = document.getElementById('ai-selected-filename');
        if (nameLabel) nameLabel.textContent = file.name;

        const reader = new FileReader();
        reader.onload = function(e) {
            const thumb = document.getElementById('ai-photo-thumb-preview');
            if (thumb) {
                thumb.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">`;
            }
        };
        reader.readAsDataURL(file);

        // Si aucune photo n'est encore dans la galerie du produit, l'y injecter aussi pour aperçu immédiat
        if (typeof window.getSelectedFiles === 'function'
            && typeof window.handleProductPhotoInput === 'function'
            && window.getSelectedFiles().length === 0) {
            window.handleProductPhotoInput({ files: [file], value: '' });
        }
    }
};

window.runAiVisionAnalysis = async function() {
    const input = document.getElementById('ai-photo-input');
    if (!input.files || !input.files[0]) {
        alert('Veuillez sélectionner une photo avant de lancer l\'analyse.');
        return;
    }

    const btn = document.getElementById('ai-analyze-submit-btn');
    const statusMsg = document.getElementById('ai-vision-status-msg');
    btn.disabled = true;
    btn.innerText = 'Analyse en cours (3s)...';
    statusMsg.style.display = 'block';
    statusMsg.style.color = '#38bdf8';
    statusMsg.innerText = 'L\'IA examine la matière, la coupe, la couleur et le style de l\'article...';

    const formData = new FormData();
    formData.append('photo', input.files[0]);
    formData.append('user_hint', document.getElementById('ai-user-hint').value);

    try {
        const res = await fetch('{{ route('ai.products.analyze_photo') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await res.json();

        if (data.status === 'success' && data.analysis) {
            const a = data.analysis;
            
            // Pré-remplissage automatique des champs du formulaire
            if (a.title) document.getElementById('title').value = a.title;
            if (a.description) document.getElementById('description').value = a.description;
            if (a.price_fcfa) document.getElementById('price').value = a.price_fcfa;
            if (a.compare_at_price_fcfa) document.getElementById('compare_at_price').value = a.compare_at_price_fcfa;

            // Sélection automatique de la catégorie correspondante si trouvée
            if (data.matched_category_id) {
                const catCheckbox = document.querySelector(`input[name="category_ids[]"][value="${data.matched_category_id}"]`);
                if (catCheckbox) catCheckbox.checked = true;
            }

            // Mémorisation de la photo analysée pour association automatique
            if (data.temp_photo_path) {
                document.getElementById('ai_temp_photo_path').value = data.temp_photo_path;
            }

            statusMsg.style.color = '#10b981';
            statusMsg.innerHTML = `<strong>✓ Fiche produit complétée avec succès !</strong> Titre, description, caractéristiques et prix suggéré (${formatNumber(a.price_fcfa)} FCFA) ont été injectés. Vous pouvez ajuster les champs librement.`;

            // Scroll fluide vers le formulaire complété
            document.getElementById('title').scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            statusMsg.style.color = '#ef4444';
            statusMsg.innerText = 'Erreur lors de l\'analyse de la photo. Veuillez réessayer.';
        }
    } catch (err) {
        statusMsg.style.color = '#ef4444';
        statusMsg.innerText = 'Erreur de connexion au serveur IA.';
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Analyser avec l\'IA';
    }
};

function formatNumber(num) {
    return new Intl.NumberFormat('fr-FR').format(num || 0);
}

// ==========================================================================
// ZANIKA AI PRODUCT PHOTOGRAPHY STUDIO — MOTEUR INTERACTIF JAVASCRIPT
// ==========================================================================
</script>
@endsection
