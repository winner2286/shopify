@extends('layouts.app')

@section('title', 'Créer une Publicité Vidéo IA : ' . config('platform.name'))

@section('content')
<div class="container" style="max-width:880px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="sparkles" :size="26" /></span>
                <h1 style="font-size:1.85rem;margin:0;font-weight:800">Studio Publicitaire Vidéo 9:16</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">Générez une vraie vidéo publicitaire verticale adaptée à votre boutique, vos produits réels et votre univers.</p>
        </div>

        <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="folder" :size="15" />
            <span>Mes publicités générées</span>
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="padding:1.8rem;border-radius:var(--radius-md)">
        <form method="POST" action="{{ route('ads.store') }}" id="video-ad-form">
            @csrf

            <!-- ======================================================== -->
            <!-- 1. ANGLE DE LA PUBLICITÉ (LE FACTEUR CLÉ QUI CHANGE TOUT) -->
            <!-- ======================================================== -->
            <div style="margin-bottom:2rem">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.6rem;flex-wrap:wrap;gap:.4rem">
                    <label class="label" style="font-size:1.05rem;font-weight:700;margin:0">
                        1. Angle de la publicité *
                    </label>
                    <span style="font-size:.8rem;color:var(--text-soft)">Détermine les produits sélectionnés et le script</span>
                </div>

                <div class="angle-choice-grid">
                    <!-- Promotions -->
                    <label class="angle-card">
                        <input type="radio" name="angle" value="promotions" {{ old('angle', 'promotions') === 'promotions' ? 'checked' : '' }} onchange="toggleAnglePanels(this.value)">
                        <div class="angle-card-body">
                            <div class="angle-icon" style="background:#fee2e2;color:#ef4444">🏷️</div>
                            <div class="angle-info">
                                <div class="angle-title">Promotions & Rabais choc</div>
                                <div class="angle-desc">Sélectionne automatiquement les articles avec les plus grosses remises réelles de votre boutique.</div>
                            </div>
                        </div>
                    </label>

                    <!-- Nouveautés -->
                    <label class="angle-card">
                        <input type="radio" name="angle" value="nouveautes" {{ old('angle') === 'nouveautes' ? 'checked' : '' }} onchange="toggleAnglePanels(this.value)">
                        <div class="angle-card-body">
                            <div class="angle-icon" style="background:#fef3c7;color:#d97706">✨</div>
                            <div class="angle-info">
                                <div class="angle-title">Nouveautés du catalogue</div>
                                <div class="angle-desc">Met en avant les derniers produits ajoutés pour stimuler la curiosité de vos clients.</div>
                            </div>
                        </div>
                    </label>

                    <!-- Best-sellers -->
                    <label class="angle-card">
                        <input type="radio" name="angle" value="bestsellers" {{ old('angle') === 'bestsellers' ? 'checked' : '' }} onchange="toggleAnglePanels(this.value)">
                        <div class="angle-card-body">
                            <div class="angle-icon" style="background:#e0e7ff;color:#4f46e5">🏆</div>
                            <div class="angle-info">
                                <div class="angle-title">Best-sellers & Incontournables</div>
                                <div class="angle-desc">Présente vos articles les plus demandés et les plus populaires pour rassurer et convertir.</div>
                            </div>
                        </div>
                    </label>

                    <!-- Coup de cœur / Produit unique -->
                    <label class="angle-card">
                        <input type="radio" name="angle" value="coup_de_coeur" {{ (old('angle') === 'coup_de_coeur' || $selectedProductId) ? 'checked' : '' }} onchange="toggleAnglePanels(this.value)">
                        <div class="angle-card-body">
                            <div class="angle-icon" style="background:#fce7f3;color:#db2777">❤️</div>
                            <div class="angle-info">
                                <div class="angle-title">Coup de cœur / Produit unique</div>
                                <div class="angle-desc">Zoom immersif dédié à 1 seul article vedette pour un impact maximal.</div>
                            </div>
                        </div>
                    </label>

                    <!-- Présentation de la boutique (Cas Spécial) -->
                    <label class="angle-card angle-card-special">
                        <input type="radio" name="angle" value="boutique" {{ old('angle') === 'boutique' ? 'checked' : '' }} onchange="toggleAnglePanels(this.value)">
                        <div class="angle-card-body">
                            <div class="angle-icon" style="background:#ecfdf5;color:#059669">🏪</div>
                            <div class="angle-info">
                                <div style="display:flex;align-items:center;gap:.5rem">
                                    <span class="angle-title">Présentation de la boutique</span>
                                    <span class="badge badge-ok" style="font-size:.68rem">Sans prix par défaut</span>
                                </div>
                                <div class="angle-desc">Mettez en avant votre marque, votre logo, vos univers de catégories et vos atouts réels.</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- PANNEAU CONDITIONNEL 1 : CHOIX DU PRODUIT UNIQUE (SI COUP DE CŒUR) -->
            <div id="panel-coup-de-coeur" class="conditional-panel" style="display:none;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1.2rem;margin-bottom:2rem">
                <label for="product_id" class="label" style="font-weight:700">Produit vedette à mettre en valeur *</label>
                <select id="product_id" name="product_id" class="input" style="font-size:.95rem">
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" {{ $selectedProductId === $p->id ? 'selected' : '' }}>
                            {{ $p->title }} — {{ number_format($p->price, 0, ',', ' ') }} {{ $shop->currency ?: 'FCFA' }}
                            @if ($p->compare_at_price && $p->compare_at_price > $p->price)
                                (-{{ round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100) }}%)
                            @endif
                        </option>
                    @endforeach
                </select>
                <p class="hint" style="margin-top:.4rem">Ce produit sera la vedette absolue de toute la vidéo (zoom cinématique, détails et bouton de commande).</p>
            </div>

            <!-- PANNEAU CONDITIONNEL 2 : PARAMÈTRES SPÉCIAUX DE LA PRÉSENTATION BOUTIQUE -->
            <div id="panel-boutique" class="conditional-panel" style="display:none;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1.4rem;margin-bottom:2rem">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem">
                    <x-icon name="info" :size="18" style="color:var(--brand-1)" />
                    <strong style="font-size:.95rem">Paramètres spécifiques au mode Présentation Boutique :</strong>
                </div>

                <div style="margin-bottom:1.2rem">
                    <label for="custom_message" class="label" style="font-size:.88rem">
                        Un message particulier à faire passer ? (Facultatif)
                    </label>
                    <input type="text" id="custom_message" name="custom_message" class="input"
                           placeholder="Ex : Nouvelle boutique à Casablanca, Livraison dans tout le Maroc, Retrait en magasin gratuit..."
                           value="{{ old('custom_message') }}" maxlength="200">
                    <p class="hint">Ce message sera prononcé par la voix et affiché comme argument fort de votre enseigne.</p>
                </div>

                @if ($categories->isNotEmpty())
                    <div>
                        <label class="label" style="font-size:.88rem;margin-bottom:.4rem">Catégories à illustrer (3 à 4 recommandées) :</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:.6rem">
                            @foreach ($categories as $cat)
                                <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;background:var(--bg);padding:.6rem .8rem;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" checked style="accent-color:var(--brand-1)">
                                    <span>{{ $cat->name }}</span>
                                    <span style="color:var(--text-soft);font-size:.75rem">({{ $cat->products_count }} art.)</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- ======================================================== -->
            <!-- 2. TON & AMBIANCE DE LA VIDÉO -->
            <!-- ======================================================== -->
            <div style="margin-bottom:2rem">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.6rem;flex-wrap:wrap;gap:.4rem">
                    <label class="label" style="font-size:1.05rem;font-weight:700;margin:0">
                        2. Ton et rythme visuel *
                    </label>
                    <span style="font-size:.8rem;color:var(--text-soft)">Change le tempo des plans, les transitions et la musique</span>
                </div>

                <div class="tone-choice-grid">
                    <label class="tone-card">
                        <input type="radio" name="tone" value="dynamique" {{ old('tone', 'dynamique') === 'dynamique' ? 'checked' : '' }}>
                        <div class="tone-card-body">
                            <div class="tone-header">⚡ Dynamique & Vendeur</div>
                            <div class="tone-desc">Cuts énergiques toutes les 2s, animations percutantes, rythme entraînant.</div>
                        </div>
                    </label>

                    <label class="tone-card">
                        <input type="radio" name="tone" value="luxe" {{ old('tone') === 'luxe' ? 'checked' : '' }}>
                        <div class="tone-card-body">
                            <div class="tone-header">✨ Luxe & Élégant</div>
                            <div class="tone-desc">Zooms lents Ken Burns, typographie raffinée épurée, musique feutrée.</div>
                        </div>
                    </label>

                    <label class="tone-card">
                        <input type="radio" name="tone" value="fun" {{ old('tone') === 'fun' ? 'checked' : '' }}>
                        <div class="tone-card-body">
                            <div class="tone-header">🎉 Fun & Pétillant</div>
                            <div class="tone-desc">Transitions chaleureuses et vivantes, vocabulaire enthousiaste et direct.</div>
                        </div>
                    </label>

                    <label class="tone-card">
                        <input type="radio" name="tone" value="urgent" {{ old('tone') === 'urgent' ? 'checked' : '' }}>
                        <div class="tone-card-body">
                            <div class="tone-header">⏳ Urgent (Offre limitée)</div>
                            <div class="tone-desc">Rythme accéléré, contrastes forts, alerte stock et appel pressant à l'action.</div>
                        </div>
                    </label>

                    <label class="tone-card">
                        <input type="radio" name="tone" value="chaleureux" {{ old('tone') === 'chaleureux' ? 'checked' : '' }}>
                        <div class="tone-card-body">
                            <div class="tone-header">💛 Familial & Chaleureux</div>
                            <div class="tone-desc">Transitions douces, ton rassurant et bienveillant axé sur la proximité.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 3. SECTEUR DE LA BOUTIQUE & PARAMÈTRES AUDIO -->
            <!-- ======================================================== -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1.4rem;margin-bottom:2rem">
                <!-- Secteur d'activité -->
                <div>
                    <label for="business_type" class="label" style="font-weight:700">3. Secteur de la boutique</label>
                    @php
                        $curCat = mb_strtolower($shop->business_category ?: '');
                    @endphp
                    <select id="business_type" name="business_type" class="input">
                        <option value="mode" {{ str_contains($curCat, 'mode') || str_contains($curCat, 'vetement') ? 'selected' : '' }}>Mode, Vêtements & Tissus</option>
                        <option value="beaute" {{ str_contains($curCat, 'beaut') || str_contains($curCat, 'cosmet') ? 'selected' : '' }}>Beauté, Cosmétiques & Soins</option>
                        <option value="food" {{ str_contains($curCat, 'resto') || str_contains($curCat, 'food') || str_contains($curCat, 'snack') ? 'selected' : '' }}>Restauration, Fast-food & Boissons</option>
                        <option value="tech" {{ str_contains($curCat, 'tech') || str_contains($curCat, 'electron') || str_contains($curCat, 'telephon') ? 'selected' : '' }}>High-Tech, Téléphonie & Gadgets</option>
                        <option value="shoes" {{ str_contains($curCat, 'chauss') || str_contains($curCat, 'sneaker') ? 'selected' : '' }}>Chaussures, Baskets & Maroquinerie</option>
                        <option value="home" {{ str_contains($curCat, 'maison') || str_contains($curCat, 'meuble') || str_contains($curCat, 'deco') ? 'selected' : '' }}>Maison, Décoration & Mobilier</option>
                        <option value="jewelry" {{ str_contains($curCat, 'bijou') || str_contains($curCat, 'montre') ? 'selected' : '' }}>Bijoux, Montres & Accessoires</option>
                        <option value="general" {{ empty($curCat) ? 'selected' : '' }}>Commerce général / Multi-produits</option>
                    </select>
                    <p class="hint">Adapte le vocabulaire et les arguments publicitaires.</p>
                </div>

                <!-- Voix-off IA -->
                <div>
                    <label for="voice" class="label" style="font-weight:700">4. Voix-Off IA</label>
                    <select id="voice" name="voice" class="input">
                        <option value="femme" {{ old('voice') === 'femme' ? 'selected' : '' }}>Voix Féminine (Chaleureuse & Professionnelle)</option>
                        <option value="homme" {{ old('voice') === 'homme' ? 'selected' : '' }}>Voix Masculine (Dynamique & Assurée)</option>
                        <option value="douce" {{ old('voice') === 'douce' ? 'selected' : '' }}>Voix Douce (Luxe & Raffinée)</option>
                    </select>
                    <p class="hint">Synchronisée mot pour mot avec chaque plan affiché.</p>
                </div>

                <!-- Durée cible de la vidéo -->
                <div>
                    <label for="target_duration" class="label" style="font-weight:700">5. Durée cible</label>
                    <select id="target_duration" name="target_duration" class="input">
                        <option value="15" {{ old('target_duration') === '15' ? 'selected' : '' }}>15 secondes (Flash percutant • Statut WhatsApp)</option>
                        <option value="20" {{ old('target_duration', '20') === '20' ? 'selected' : '' }} selected>20 secondes (Recommandé • Équilibré & Complet)</option>
                        <option value="25" {{ old('target_duration') === '25' ? 'selected' : '' }}>25 secondes (Immersif • Showcase détaillé)</option>
                    </select>
                    <p class="hint">Format vertical 9:16 idéal TikTok, Reels & WhatsApp.</p>
                </div>
            </div>

            <!-- Canal principal de diffusion -->
            <input type="hidden" name="channel" value="tiktok">

            <div class="ad-submit-actions">
                <a href="{{ route('ads.index') }}" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary btn-submit-ad" id="btn-submit-generate">
                    <x-icon name="sparkles" :size="18" />
                    <span class="btn-full-text">Générer la Vidéo Publicitaire 9:16</span>
                    <span class="btn-short-text">Générer la vidéo</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .angle-choice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: .85rem;
    }
    .angle-card {
        display: block;
        position: relative;
        cursor: pointer;
        margin: 0;
    }
    .angle-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .angle-card-body {
        display: flex;
        align-items: flex-start;
        gap: .85rem;
        padding: 1rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        transition: all .2s ease;
        height: 100%;
        box-sizing: border-box;
    }
    .angle-card input:checked + .angle-card-body {
        border-color: var(--brand-1);
        background: rgba(109, 61, 242, 0.05);
        box-shadow: 0 4px 14px rgba(109, 61, 242, 0.12);
    }
    .angle-icon {
        font-size: 1.35rem;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .angle-title {
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: .25rem;
    }
    .angle-desc {
        font-size: .78rem;
        color: var(--text-soft);
        line-height: 1.35;
    }
    .angle-card-special .angle-card-body {
        border-style: dashed;
    }

    .tone-choice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: .75rem;
    }
    .tone-card {
        display: block;
        position: relative;
        cursor: pointer;
        margin: 0;
    }
    .tone-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .tone-card-body {
        padding: .85rem .95rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        transition: all .2s ease;
        height: 100%;
        box-sizing: border-box;
    }
    .tone-card input:checked + .tone-card-body {
        border-color: var(--brand-1);
        background: rgba(109, 61, 242, 0.05);
    }
    .tone-header {
        font-size: .88rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: .25rem;
    }
    .tone-desc {
        font-size: .74rem;
        color: var(--text-soft);
        line-height: 1.3;
    }

    .ad-submit-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    .btn-submit-ad {
        font-size: 1.05rem;
        padding: .85rem 1.8rem;
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-weight: 700;
    }
    .btn-submit-ad .btn-short-text { display: none; }

    @media (max-width: 640px) {
        .ad-submit-actions {
            flex-direction: column-reverse;
            width: 100%;
        }
        .ad-submit-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .btn-submit-ad .btn-full-text { display: none; }
        .btn-submit-ad .btn-short-text { display: inline; }
    }
</style>

<script>
    function toggleAnglePanels(angle) {
        const coupPanel = document.getElementById('panel-coup-de-coeur');
        const boutiquePanel = document.getElementById('panel-boutique');

        if (coupPanel) {
            coupPanel.style.display = (angle === 'coup_de_coeur') ? 'block' : 'none';
        }
        if (boutiquePanel) {
            boutiquePanel.style.display = (angle === 'boutique') ? 'block' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const checkedAngle = document.querySelector('input[name="angle"]:checked');
        if (checkedAngle) {
            toggleAnglePanels(checkedAngle.value);
        }

        const form = document.getElementById('video-ad-form');
        const submitBtn = document.getElementById('btn-submit-generate');
        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.innerHTML = '<span class="spinner-sm" style="display:inline-block;width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite"></span> Génération de votre vidéo en cours...';
            });
        }
    });
</script>
<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
@endsection
