@extends('layouts.app')

@section('title', 'Nouveau Produit : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Ajouter un produit</h1>
        </div>
        <div style="display:flex;gap:.6rem;align-items:center">
            <button type="button" class="btn btn-ghost" onclick="openProductLivePreview()" style="display:inline-flex;align-items:center;gap:.45rem;font-weight:700;border:1px solid var(--border);border-radius:10px;padding:.55rem .95rem;background:var(--surface)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span>👁️ Aperçu de la fiche vitrine</span>
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
                <button type="button" class="btn btn-sm" onclick="toggleAiVisionDropzone()" style="background:linear-gradient(135deg,#e11d48,#be123c);color:#fff;font-weight:700;border:none;padding:.5rem 1.1rem;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem">
                    <x-icon name="sparkles" size="14" /> Déposer une photo
                </button>
            </div>

            <!-- Dropzone escamotable -->
            <div id="ai-vision-dropzone-wrap" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.08)">
                <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">
                    <div>
                        <div class="field" style="margin-bottom:.75rem">
                            <label style="font-size:.85rem;font-weight:600">Photo du produit (format JPG, PNG ou WebP)</label>
                            <input type="file" id="ai-photo-input" accept="image/*" class="input" style="padding:.5rem" onchange="previewAiPhoto(this)">
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

                    <!-- Bannière / Studio Photo IA Professionnel (Zanika Luxury Theme) -->
                    <div id="ai-quality-card" style="margin-top:1.25rem;background:var(--surface);border:1px solid rgba(158,27,50,0.3);border-radius:14px;padding:1rem 1.15rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:all .2s;box-shadow:0 4px 16px rgba(0,0,0,0.2);flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:.85rem;min-width:240px;flex:1">
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg, #9e1b32, #c5223d);display:grid;place-items:center;color:#fff;box-shadow:0 4px 14px rgba(158,27,50,0.35);flex-shrink:0">
                                <x-icon name="sparkles" size="22" />
                            </div>
                            <div style="min-width:0">
                                <div style="display:flex;align-items:center;gap:.5rem">
                                    <strong style="font-size:1.02rem;color:var(--text);display:block">Studio Photo IA Professionnel</strong>
                                    <span style="font-size:.7rem;font-weight:700;color:#10b981;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);padding:1px 6px;border-radius:4px">100% Fidèle</span>
                                </div>
                                <span style="font-size:.82rem;color:var(--text-soft);display:block;margin-top:2px;line-height:1.3">Détourage net, cadrage 1:1, ombres physiques & décor adapté • +12 crédits</span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0">
                            <button type="button" onclick="triggerAiStudioFromButton()" id="btn-trigger-ai-studio" class="btn" style="background:linear-gradient(135deg, #9e1b32, #c5223d);color:#fff;border:none;font-weight:700;padding:.65rem 1.2rem;border-radius:10px;font-size:.9rem;display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;box-shadow:0 4px 14px rgba(158,27,50,0.35);transition:all .15s">
                                <x-icon name="sparkles" size="16" />
                                <span>Améliorer avec l'IA</span>
                            </button>
                            <label class="zanika-switch" title="Activer la vérification automatique après chaque cliché">
                                <input type="checkbox" id="toggle-ai-quality" onchange="handleAiQualityToggle(this)">
                                <span class="zanika-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Indicateur de chargement IA -->
                    <div id="ai-quality-loading" style="display:none;margin-top:.75rem;padding:.85rem 1.1rem;background:rgba(158,27,50,0.08);border:1px solid rgba(158,27,50,0.3);border-radius:12px;color:var(--text);font-size:.88rem;align-items:center;gap:.75rem">
                        <span style="width:18px;height:18px;border:2.5px solid #c5223d;border-top-color:transparent;border-radius:50%;display:inline-block;animation:zanika-spin 0.8s linear infinite;flex-shrink:0"></span>
                        <span id="ai-quality-loading-text">Studio IA en cours : Analyse du produit, détourage haute précision et création des 4 packshots (1 à 2s)...</span>
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
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <span>👁️ Prévisualiser avant de publier</span>
                        </button>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
                            <button type="button" class="btn btn-ghost" onclick="confirmProductSubmission('hidden')" style="font-size:.82rem;padding:.75rem .4rem;text-align:center;border-radius:10px;border:1px solid var(--border)">
                                💾 Enregistrer en brouillon
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

            // Si le contrôle qualité IA est activé, lancer la vérification comparative
            const toggleQuality = document.getElementById('toggle-ai-quality');
            if (toggleQuality && toggleQuality.checked) {
                window.runAiQualityCheck(files[0]);
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
        const reader = new FileReader();
        reader.onload = function(e) {
            const thumb = document.getElementById('ai-photo-thumb-preview');
            if (thumb) {
                thumb.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
            }
        };
        reader.readAsDataURL(input.files[0]);
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
// VÉRIFICATION QUALITÉ IA & SÉLECTEUR DE VARIANTES COMPARATIF (THÈME ZANIKA)
// ==========================================================================
let currentAiVariations = [];
let currentSourceFile = null;

window.handleAiQualityToggle = function(input) {
    if (input.checked) {
        const files = window.getSelectedFiles ? window.getSelectedFiles() : [];
        if (files.length > 0) {
            window.runAiQualityCheck(files[0]);
        } else {
            const loadingBox = document.getElementById('ai-quality-loading');
            if (loadingBox) {
                loadingBox.style.display = 'flex';
                loadingBox.innerHTML = `<span>Sélectionnez ou prenez une photo : le comparateur qualité IA s'ouvrira immédiatement.</span>`;
                setTimeout(() => {
                    if (loadingBox.style.display === 'flex' && !loadingBox.dataset.loading) {
// ==========================================================================
// ZANIKA AI PRODUCT PHOTOGRAPHY STUDIO — MOTEUR INTERACTIF JAVASCRIPT
// ==========================================================================
let currentAiVariations = [];
let currentSourceFile = null;
let activeCompareIndex = 0;

window.triggerAiStudioFromButton = function() {
    const files = window.getSelectedFiles ? window.getSelectedFiles() : [];
    if (files.length > 0) {
        window.runAiQualityCheck(files[0]);
    } else {
        const loadingBox = document.getElementById('ai-quality-loading');
        if (loadingBox) {
            loadingBox.style.display = 'flex';
            const txt = document.getElementById('ai-quality-loading-text');
            if (txt) txt.textContent = "Veuillez d'abord prendre ou choisir une photo pour ouvrir le Studio Photo IA.";
            setTimeout(() => {
                if (loadingBox.style.display === 'flex' && !loadingBox.dataset.loading) {
                    loadingBox.style.display = 'none';
                }
            }, 3500);
        }
        // Ouvrir automatiquement la galerie ou caméra
        const galleryBtn = document.getElementById('gallery-file-input');
        if (galleryBtn) galleryBtn.click();
    }
};

window.handleAiQualityToggle = function(input) {
    if (input.checked) {
        const files = window.getSelectedFiles ? window.getSelectedFiles() : [];
        if (files.length > 0) {
            window.runAiQualityCheck(files[0]);
        }
    }
};

window.runAiQualityCheck = async function(file) {
    if (!file) {
        const files = window.getSelectedFiles ? window.getSelectedFiles() : [];
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

    // Indication contextuelle (titre du produit ou catégorie saisie)
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

            // Contexte produit détecté
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

            // Quality score pill
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3Z"/></svg>
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

    // Marquer la sélection
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

    // Mettre à jour l'état visuel des puces de style
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

    // Rafraîchir les coches de la grille galerie
    window.renderAiGalleryGrid();
};

window.renderAiStudioModal = function() {
    const modal = document.getElementById('ai-variations-modal');
    if (!modal) return;

    // 1. Photo originale source
    const sourceVar = currentAiVariations.find(v => v.is_source);
    const sourceImg = document.getElementById('ai-compare-source-img');
    if (sourceImg) {
        sourceImg.src = sourceVar ? sourceVar.url : window.currentSourceUrl;
    }

    // 2. Première variation studio sélectionnée par défaut (ou index 0)
    let initialIndex = currentAiVariations.findIndex(v => !v.is_source);
    if (initialIndex < 0) initialIndex = 0;

    // 3. Rendu des boutons puces de style sous le comparateur
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

    // 4. Rendu de la grille des styles
    window.renderAiGalleryGrid();

    // 5. Activer le comparatif avec le premier style
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

        // Bouton loupe plein écran
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

        // Badge
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

        // Coche de sélection
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

        // Image
        const img = document.createElement('img');
        img.src = item.url;
        img.alt = item.title;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.display = 'block';
        card.appendChild(img);

        // Titre flottant en bas
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

    // Carte : Générer une autre ambiance
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

            // Sélectionner la nouvelle variante créée
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
            // Le commerçant a choisi sa photo originale sans modification
            if (window.setSelectedFiles) {
                window.setSelectedFiles([currentSourceFile]);
            }
        } else {
            // Le commerçant a choisi le packshot studio IA
            const response = await fetch(selected.url);
            const blob = await response.blob();
            const fileName = 'packshot_studio_' + Date.now() + '.jpg';
            const studioFile = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });

            if (window.setSelectedFiles) {
                window.setSelectedFiles([studioFile]);
            }

            // Mémoriser le chemin serveur s'il existe
            const hiddenPath = document.getElementById('ai_temp_photo_path');
            if (hiddenPath && selected.path) {
                hiddenPath.value = selected.path;
            }
        }

        window.closeAiVariationsModal();

        // Notification visuelle
        const alertBox = document.createElement('div');
        alertBox.className = 'alert alert-ok';
        alertBox.style.marginTop = '1rem';
        alertBox.innerHTML = `<strong>✓ Photo sélectionnée validée :</strong> Packshot professionnel appliqué à votre fiche produit (format 1:1, produit 100% conservé).`;
        const container = document.getElementById('product-photos-section');
        if (container) {
            container.appendChild(alertBox);
            setTimeout(() => alertBox.remove(), 4500);
        }

    } catch (err) {
        console.error(err);
        alert('Erreur lors de l\'intégration de la photo choisie.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
};

window.keepOriginalPhoto = function() {
    // Règle 6.8 & 6.9 : Le commerçant a le dernier mot, on conserve sa photo originale
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
@endsection
