@extends('layouts.app')

@section('title', 'Studio Vidéo 9:16 : ' . $ad->channel_label . ' - ' . config('platform.name'))

@section('content')
<div class="container studio-container" style="max-width:1120px;padding:1.5rem 1rem 3rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="sparkles" :size="26" /></span>
                <h1 style="font-size:1.85rem;margin:0;font-weight:800">Studio Vidéo Publicitaire 9:16</h1>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                @php
                    $vc = $ad->visual_config ?? [];
                    $angle = $vc['angle'] ?? 'nouveautes';
                    $tone = $vc['tone'] ?? $ad->tone ?? 'dynamique';
                    $isStore = ! empty($vc['is_store_presentation']);
                    $productsCount = count($vc['products'] ?? []);
                    $shopPhone = $shop->phone ?: ($shop->whatsapp ?: '');
                @endphp
                <span class="badge badge-ok" style="text-transform:uppercase">{{ $angle }}</span>
                <span class="badge badge-primary">{{ ucfirst($tone) }}</span>
                <span class="badge badge-draft">{{ $productsCount }} {{ $isStore ? 'catégories / univers' : 'articles présentés' }}</span>
                <span class="badge badge-draft">Format 9:16 Vertical</span>
            </div>
        </div>

        <div class="header-actions" style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('ads.create') }}" class="btn btn-primary btn-sm">+ Nouvelle publicité</a>
            <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="folder" :size="15" />
                <span>Mes vidéos</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SECTION STUDIO VIDÉO PUBLICITAIRE 9:16                                    -->
    <!-- ========================================================================= -->
    <section class="card studio-card" style="padding:1.5rem;border-radius:var(--radius-md);margin-bottom:2.5rem">
        <div class="studio-header-banner">
            <div>
                <div class="studio-format-pill">
                    <x-icon name="video" :size="15" />
                    <span>Publicité Verticale 9:16 (TikTok • Instagram Reels • WhatsApp)</span>
                </div>
                <h2 class="studio-header-title">
                    {{ $isStore ? 'Vidéo Découverte : ' . $shop->name : 'Publicité Produits : ' . $shop->name }}
                </h2>
                <p class="studio-header-desc">
                    {{ $isStore
                        ? 'Cette vidéo présente votre univers de marque, votre logo et vos catégories phares sans afficher de prix.'
                        : 'Cette vidéo met en scène vos vrais articles avec leurs photos, leurs prix réels et le bouton WhatsApp.' }}
                </p>
            </div>

            <div class="studio-unlimited-badge">
                <span class="unlimited-dot"></span>
                <span>Rendu 100% Propre • Sans filigrane</span>
            </div>
        </div>

        <div class="studio-grid">
            <!-- COLONNE GAUCHE : LECTEUR SIMULATEUR SMARTPHONE 9:16 -->
            <div class="studio-player-col">
                <div class="phone-frame">
                    <div class="phone-notch"></div>

                    <!-- Badge de plan en haut à gauche -->
                    <div class="phone-scene-badge" id="ad-scene-indicator">
                        Chargement de la vidéo...
                    </div>

                    <!-- Canvas HD 9:16 (720 x 1280) -->
                    <canvas id="ad-video-canvas" width="720" height="1280" class="ad-canvas"></canvas>

                    <!-- Overlay Play/Pause discret -->
                    <div id="ad-center-play-overlay" class="center-play-overlay">
                        <div class="center-play-icon">
                            <x-icon name="play" :size="20" />
                            <span>Lancer la vidéo</span>
                        </div>
                    </div>
                </div>

                <!-- Barre de contrôle du lecteur -->
                <div class="player-controls-bar">
                    <button type="button" id="ad-btn-play" class="btn-ctrl-play" aria-label="Lecture / Pause">
                        <span id="ad-play-icon"><x-icon name="play" :size="18" /></span>
                        <span id="ad-pause-icon" style="display:none"><x-icon name="pause" :size="18" /></span>
                    </button>

                    <input type="range" id="ad-seek-bar" class="timeline-slider" min="0" max="100" value="0" step="0.1" aria-label="Progression de la vidéo">

                    <span id="ad-time-display" class="time-readout">00:00.0 / 00:20.0</span>

                    <button type="button" id="ad-btn-mute" class="btn-ctrl-mute active" title="Son activé / désactivé" aria-label="Activer ou couper le son">
                        <span id="icon-sound-on"><x-icon name="volume-2" :size="18" /></span>
                        <span id="icon-sound-off" style="display:none"><x-icon name="volume-x" :size="18" /></span>
                    </button>
                </div>

                <!-- Sélecteur de format d'export : MP4 vs WebM -->
                <div class="format-choice-container">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;gap:.5rem;flex-wrap:wrap">
                        <label style="font-size:.8rem;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin:0">
                            Format d'exportation :
                        </label>
                        <span id="ad-format-tag" class="badge badge-ok" style="font-size:.68rem;padding:.15rem .45rem">MP4 sélectionné</span>
                    </div>

                    <div class="format-choice-grid">
                        <button type="button" class="btn-format-pick selected" data-format-choice="mp4" id="btn-format-mp4">
                            <div class="format-pick-radio">
                                <span class="format-radio-dot"></span>
                            </div>
                            <div class="format-pick-info">
                                <div class="format-pick-header">
                                    <strong class="format-pick-name">Format MP4</strong>
                                    <span class="badge badge-ok format-badge">Recommandé</span>
                                </div>
                                <small class="format-pick-desc">Idéal TikTok, WhatsApp & Instagram</small>
                            </div>
                        </button>

                        <button type="button" class="btn-format-pick" data-format-choice="webm" id="btn-format-webm">
                            <div class="format-pick-radio">
                                <span class="format-radio-dot"></span>
                            </div>
                            <div class="format-pick-info">
                                <div class="format-pick-header">
                                    <strong class="format-pick-name">Format WebM</strong>
                                    <span class="badge badge-draft format-badge">Léger</span>
                                </div>
                                <small class="format-pick-desc">Fichier ultra compressé</small>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Bouton Téléchargement Vidéo HD -->
                <div class="export-btn-wrapper">
                    <button type="button" id="ad-btn-export" class="btn-export-video">
                        <div class="btn-export-glow"></div>
                        <span class="btn-export-content">
                            <x-icon name="download" :size="20" />
                            <span>
                                <strong id="ad-export-title">Télécharger la Vidéo MP4</strong>
                                <small id="ad-export-subtitle">Format 9:16 vertical • Recommandé TikTok & WhatsApp</small>
                            </span>
                        </span>
                    </button>

                    <!-- Jauge de progression d'enregistrement -->
                    <div id="ad-export-progress-wrap" class="export-progress-container" style="display:none">
                        <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.85rem;font-weight:600">
                            <span>Génération de votre vidéo HD en cours...</span>
                            <span id="ad-export-progress-percent">0%</span>
                        </div>
                        <div class="export-progress-track">
                            <div id="ad-export-progress-bar" class="export-progress-fill"></div>
                        </div>
                        <p style="font-size:.78rem;color:var(--text-soft);margin:.4rem 0 0;text-align:center">
                            Enregistrement direct haute fidélité avec musique et voix synchronisées.
                        </p>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE : RÉGLAGES, ACTIONS & PERSONNALISATION -->
            <div class="studio-settings-col">
                <!-- Actions Principales : Régénérer / Modifier les produits -->
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem">
                    <button type="button" id="ad-btn-regenerate" class="btn btn-outline" style="flex:1;min-width:180px;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;font-weight:600">
                        <x-icon name="sparkles" :size="16" style="color:var(--brand-1)" />
                        <span>Régénérer une variation</span>
                    </button>

                    <button type="button" class="btn btn-ghost" onclick="openProductEditModal()" style="flex:1;min-width:180px;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;border:1px solid var(--border);font-weight:600">
                        <x-icon name="edit" :size="16" />
                        <span>Modifier les produits</span>
                    </button>
                </div>

                <!-- 1. Choix du Scénario / Script Synchronisé -->
                <div class="settings-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem">
                        <h3 class="settings-title" style="margin:0">
                            <x-icon name="sparkles" :size="16" style="color:var(--brand-2)" />
                            <span>1. Déroulé du Script Synchronisé</span>
                        </h3>
                        <span id="ad-voice-status" class="badge badge-ok" style="font-size:.68rem">Voix prête</span>
                    </div>
                    <p class="settings-desc">Chaque plan affiche le produit exact énoncé par la voix :</p>

                    <div class="script-cards-grid">
                        @foreach ($ad->generated_scripts as $idx => $script)
                            <div class="script-pick-card {{ $idx === 0 ? 'selected' : '' }}" data-script-choice="{{ $idx }}">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;margin-bottom:.35rem">
                                    <strong style="font-size:.92rem;flex:1;line-height:1.35">{{ $script['title'] }}</strong>
                                    <span class="badge badge-ok" style="font-size:.72rem;flex-shrink:0;padding:.15rem .45rem">
                                        {{ count($script['scenes'] ?? $script['steps'] ?? []) }} plans
                                    </span>
                                </div>
                                <div style="font-size:.82rem;color:var(--text-soft);line-height:1.4">
                                    {{ $script['concept'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 2. Ton et Rythme en direct -->
                <div class="settings-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem">
                        <h3 class="settings-title" style="margin:0">
                            <x-icon name="volume-2" :size="16" style="color:var(--brand-1)" />
                            <span>2. Ton & Rythme Visuel</span>
                        </h3>
                        <span class="badge badge-draft" style="font-size:.7rem;text-transform:uppercase">{{ $tone }}</span>
                    </div>
                    <p class="settings-desc">Changez instantanément le rythme des cuts, les animations et la musique :</p>

                    <div class="tone-buttons-grid">
                        <button type="button" class="btn-tone-chip {{ $tone === 'dynamique' ? 'selected' : '' }}" data-studio-tone="dynamique">
                            <span>⚡ Dynamique</span>
                            <small>Cuts rapides • 124 BPM</small>
                        </button>

                        <button type="button" class="btn-tone-chip {{ $tone === 'luxe' ? 'selected' : '' }}" data-studio-tone="luxe">
                            <span>✨ Luxe & Sobre</span>
                            <small>Ken Burns lent • Feutré</small>
                        </button>

                        <button type="button" class="btn-tone-chip {{ $tone === 'fun' ? 'selected' : '' }}" data-studio-tone="fun">
                            <span>🎉 Fun & Pétillant</span>
                            <small>Rebonds vifs • Entraînant</small>
                        </button>

                        <button type="button" class="btn-tone-chip {{ $tone === 'urgent' ? 'selected' : '' }}" data-studio-tone="urgent">
                            <span>⏳ Urgent (Offre)</span>
                            <small>Tempo serré • 128 BPM</small>
                        </button>

                        <button type="button" class="btn-tone-chip {{ $tone === 'chaleureux' ? 'selected' : '' }}" data-studio-tone="chaleureux">
                            <span>💛 Familial</span>
                            <small>Glissements doux</small>
                        </button>
                    </div>
                </div>

                <!-- 3. Produits actuellement présentés -->
                <div class="settings-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem">
                        <h3 class="settings-title" style="margin:0">
                            <x-icon name="package" :size="16" style="color:var(--brand-1)" />
                            <span>3. Produits dans cette vidéo ({{ count($vc['products'] ?? []) }})</span>
                        </h3>
                        <button type="button" onclick="openProductEditModal()" class="btn btn-ghost btn-sm" style="font-size:.78rem;padding:.2rem .6rem">
                            Modifier
                        </button>
                    </div>

                    <div class="selected-products-preview-list">
                        @forelse ($vc['products'] ?? [] as $pItem)
                            <div class="preview-product-item">
                                <div class="preview-product-img-wrap">
                                    @if (!empty($pItem['imageUrl']))
                                        <img src="{{ $pItem['imageUrl'] }}" alt="{{ $pItem['title'] }}">
                                    @else
                                        <span>🛍️</span>
                                    @endif
                                </div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-weight:700;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ $pItem['title'] }}
                                    </div>
                                    <div style="font-size:.76rem;color:var(--text-soft)">
                                        @if ($isStore)
                                            <span class="badge badge-draft" style="font-size:.65rem">{{ $pItem['category'] ?? 'Catégorie' }}</span>
                                        @else
                                            <strong style="color:var(--brand-1)">{{ $pItem['price'] }}</strong>
                                            @if (!empty($pItem['old_price']))
                                                <span style="text-decoration:line-through;color:var(--text-soft);margin-left:.3rem">{{ $pItem['old_price'] }}</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p style="font-size:.85rem;color:var(--text-soft);margin:0">Aucun produit sélectionné.</p>
                        @endforelse
                    </div>
                </div>

                <!-- 4. Partage direct WhatsApp -->
                <div style="background:rgba(37,211,102,0.08);border:1px solid rgba(37,211,102,0.3);padding:1.2rem;border-radius:var(--radius-sm)">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem">
                        <div>
                            <div style="font-weight:700;font-size:.92rem;color:#128C7E;display:flex;align-items:center;gap:.4rem">
                                <x-icon name="whatsapp" :size="18" />
                                <span>Partager sur votre statut WhatsApp</span>
                            </div>
                            <small style="color:var(--text-soft);display:block;margin-top:.2rem">
                                Téléchargez la vidéo MP4 ci-dessus, puis publiez-la avec votre lien boutique.
                            </small>
                        </div>
                        <a href="https://wa.me/?text={{ urlencode('Découvrez notre nouvelle sélection disponible chez ' . $shop->name . ' ! Visitez la boutique : ' . $shop->public_url) }}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366;color:#fff">
                            Ouvrir WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- SECTION TEXTES & LÉGENDES ASSORTIS (RÉSEAUX SOCIAUX)                      -->
    <!-- ========================================================================= -->
    <div style="margin-bottom:3rem">
        <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
            <x-icon name="edit" :size="18" style="color:var(--brand-1)" />
            <span>Textes et Légendes assortis pour vos réseaux sociaux</span>
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1.2rem">
            @foreach ($ad->generated_texts as $index => $text)
                <div class="card" style="padding:1.4rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;border-bottom:1px solid var(--border);padding-bottom:.4rem">
                        <strong style="font-size:.9rem;color:var(--brand-2)">Légende #{{ $index + 1 }}</strong>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(`{{ addslashes($text) }}`); alert('Texte copié !');" style="padding:.2rem .5rem;font-size:.75rem">
                            <x-icon name="copy" :size="13" /> Copier
                        </button>
                    </div>
                    <div style="font-size:.9rem;line-height:1.6;color:var(--text);white-space:pre-line">
                        {{ $text }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL : MODIFIER LES PRODUITS SÉLECTIONNÉS DANS LA VIDÉO                  -->
<!-- ========================================================================= -->
<div id="modal-edit-products" class="custom-modal" style="display:none">
    <div class="custom-modal-backdrop" onclick="closeProductEditModal()"></div>
    <div class="custom-modal-dialog">
        <div class="custom-modal-header">
            <h3 style="margin:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem">
                <x-icon name="package" :size="18" style="color:var(--brand-1)" />
                <span>Modifier les produits de la vidéo</span>
            </h3>
            <button type="button" class="btn-close-modal" onclick="closeProductEditModal()">✕</button>
        </div>

        <form id="form-update-products" onsubmit="submitProductUpdate(event)">
            <div class="custom-modal-body">
                <p style="font-size:.85rem;color:var(--text-soft);margin-top:0;margin-bottom:1rem">
                    Cochez les produits que vous souhaitez afficher dans la vidéo (3 à 5 recommandés). La vidéo et les voix seront immédiatement réactualisées.
                </p>

                <div class="products-selection-grid">
                    @php
                        $selectedIds = collect($vc['products'] ?? [])->pluck('id')->toArray();
                    @endphp
                    @foreach ($shopProducts as $sp)
                        @php
                            $pImg = $sp->primaryImage ?? $sp->images->first();
                            $hasDiscount = $sp->compare_at_price && $sp->compare_at_price > $sp->price;
                        @endphp
                        <label class="product-pick-item">
                            <input type="checkbox" name="selected_product_ids[]" value="{{ $sp->id }}"
                                   {{ in_array($sp->id, $selectedIds) ? 'checked' : '' }}
                                   style="accent-color:var(--brand-1)">
                            <div class="product-pick-img">
                                @if ($pImg)
                                    <img src="{{ $pImg->url }}" alt="{{ $sp->title }}">
                                @else
                                    <span>📦</span>
                                @endif
                            </div>
                            <div class="product-pick-details">
                                <div class="product-pick-title">{{ $sp->title }}</div>
                                <div class="product-pick-price">
                                    {{ number_format($sp->price, 0, ',', ' ') }} {{ $shop->currency ?: 'FCFA' }}
                                    @if ($hasDiscount)
                                        <span class="badge badge-ok" style="font-size:.65rem;margin-left:.25rem">Promo</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="custom-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeProductEditModal()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="btn-save-products">
                    Mettre à jour la vidéo
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* STYLES DU STUDIO VIDÉO */
    .studio-header-banner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }
    .studio-format-pill {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .8rem;
        font-weight: 700;
        color: var(--brand-1);
        background: rgba(109, 61, 242, 0.08);
        padding: .25rem .7rem;
        border-radius: 20px;
        margin-bottom: .4rem;
    }
    .studio-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 .3rem;
    }
    .studio-header-desc {
        color: var(--text-soft);
        font-size: .88rem;
        margin: 0;
        max-width: 680px;
    }
    .studio-unlimited-badge {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        font-weight: 700;
        color: #10b981;
        background: rgba(16, 185, 129, 0.1);
        padding: .35rem .75rem;
        border-radius: 20px;
    }
    .unlimited-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        box-shadow: 0 0 8px #10b981;
    }

    .studio-grid {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 2rem;
        align-items: start;
    }

    /* SMARTPHONE FRAME 9:16 */
    .studio-player-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        max-width: 340px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    .phone-frame {
        width: 100%;
        max-width: 320px;
        height: 568px; /* Ratio 9:16 compact */
        background: #000;
        border-radius: 38px;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4), inset 0 0 0 5px #1e1e24;
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: center;
        align-items: center;
        box-sizing: border-box;
        margin: 0 auto;
    }
    .phone-notch {
        position: absolute;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        width: 90px;
        height: 18px;
        background: #111;
        border-radius: 12px;
        z-index: 10;
    }
    .phone-scene-badge {
        position: absolute;
        top: 36px;
        left: 16px;
        z-index: 10;
        font-size: .68rem;
        font-weight: 700;
        color: #fff;
        background: rgba(0, 0, 0, 0.65);
        padding: .2rem .5rem;
        border-radius: 12px;
        pointer-events: none;
    }
    .ad-canvas {
        width: 100%;
        height: 100%;
        object-fit: cover;
        cursor: pointer;
    }
    .center-play-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.25);
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity .25s ease;
        z-index: 8;
        cursor: pointer;
    }
    .center-play-icon {
        background: rgba(255, 255, 255, 0.96);
        color: #0f172a;
        font-size: .82rem;
        font-weight: 700;
        padding: .55rem 1.15rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        transition: transform .15s ease;
    }
    .center-play-icon:hover {
        transform: scale(1.04);
    }

    /* PLAYER CONTROLS */
    .player-controls-bar {
        display: flex;
        align-items: center;
        gap: .5rem;
        width: 100%;
        max-width: 320px;
        margin: .85rem auto 0;
        background: var(--bg-soft);
        padding: .5rem .7rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        box-sizing: border-box;
    }
    .btn-ctrl-play, .btn-ctrl-mute {
        background: none;
        border: none;
        color: var(--text);
        cursor: pointer;
        padding: .3rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .timeline-slider {
        flex: 1;
        accent-color: var(--brand-1);
        cursor: pointer;
        min-width: 0;
    }
    .time-readout {
        font-size: .74rem;
        font-family: monospace;
        color: var(--text-soft);
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* FORMAT PICKER */
    .format-choice-container {
        width: 100%;
        max-width: 320px;
        margin: .85rem auto 0;
        box-sizing: border-box;
    }
    .format-choice-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
        width: 100%;
        box-sizing: border-box;
    }
    .btn-format-pick {
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .55rem .5rem;
        display: flex;
        align-items: flex-start;
        gap: .45rem;
        cursor: pointer;
        text-align: left;
        min-width: 0;
        box-sizing: border-box;
        transition: all .15s ease;
    }
    .btn-format-pick.selected {
        border-color: var(--brand-1);
        background: rgba(109, 61, 242, 0.06);
    }
    .format-pick-radio {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: .15rem;
    }
    .btn-format-pick.selected .format-pick-radio {
        border-color: var(--brand-1);
    }
    .btn-format-pick.selected .format-radio-dot {
        width: 6px;
        height: 6px;
        background: var(--brand-1);
        border-radius: 50%;
    }
    .format-pick-info {
        min-width: 0;
        flex: 1;
        overflow: hidden;
    }
    .format-pick-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .25rem;
        flex-wrap: wrap;
    }
    .format-pick-name {
        font-size: .84rem;
        color: var(--text);
        white-space: nowrap;
    }
    .format-badge {
        font-size: .62rem;
        padding: .1rem .35rem;
        flex-shrink: 0;
    }
    .format-pick-desc {
        font-size: .68rem;
        color: var(--text-soft);
        display: block;
        margin-top: .15rem;
        line-height: 1.25;
        word-break: break-word;
    }

    /* EXPORT BUTTON */
    .export-btn-wrapper {
        width: 100%;
        max-width: 320px;
        margin: .85rem auto 0;
        box-sizing: border-box;
    }
    .btn-export-video {
        width: 100%;
        background: linear-gradient(135deg, var(--brand-1), #9333ea);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        padding: .85rem .9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(109, 61, 242, 0.35);
        transition: transform .15s ease;
        box-sizing: border-box;
    }
    .btn-export-video:hover {
        transform: translateY(-2px);
    }
    .btn-export-content {
        display: flex;
        align-items: center;
        gap: .65rem;
        text-align: left;
        min-width: 0;
    }
    .btn-export-content strong {
        display: block;
        font-size: .92rem;
        line-height: 1.25;
        word-break: break-word;
    }
    .btn-export-content small {
        display: block;
        font-size: .7rem;
        opacity: .9;
        line-height: 1.25;
        word-break: break-word;
    }
    .export-progress-container {
        width: 100%;
        max-width: 320px;
        background: var(--bg-soft);
        padding: .85rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        box-sizing: border-box;
        margin: .85rem auto 0;
    }
    .export-progress-track {
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }
    .export-progress-fill {
        height: 100%;
        width: 0%;
        background: var(--brand-1);
        transition: width .1s linear;
    }

    /* SETTINGS COLUMN */
    .studio-settings-col {
        display: flex;
        flex-direction: column;
        gap: 1.2rem;
    }
    .settings-box {
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 1.1rem;
    }
    .settings-title {
        font-size: .95rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .settings-desc {
        font-size: .78rem;
        color: var(--text-soft);
        margin: .25rem 0 .8rem;
    }

    .script-cards-grid {
        display: flex;
        flex-direction: column;
        gap: .6rem;
    }
    .script-pick-card {
        background: var(--bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .8rem;
        cursor: pointer;
        transition: border-color .15s;
    }
    .script-pick-card.selected {
        border-color: var(--brand-1);
        background: rgba(109, 61, 242, 0.04);
    }

    .tone-buttons-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: .5rem;
    }
    .btn-tone-chip {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: .6rem .7rem;
        cursor: pointer;
        text-align: left;
    }
    .btn-tone-chip.selected {
        border-color: var(--brand-1);
        background: rgba(109, 61, 242, 0.06);
    }
    .btn-tone-chip span {
        display: block;
        font-size: .85rem;
        font-weight: 700;
        color: var(--text);
    }
    .btn-tone-chip small {
        display: block;
        font-size: .7rem;
        color: var(--text-soft);
        margin-top: .15rem;
    }

    .selected-products-preview-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .preview-product-item {
        display: flex;
        align-items: center;
        gap: .7rem;
        background: var(--bg);
        padding: .5rem .7rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
    }
    .preview-product-img-wrap {
        width: 38px;
        height: 38px;
        border-radius: 6px;
        overflow: hidden;
        background: var(--bg-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .preview-product-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* MODAL */
    .custom-modal {
        position: fixed;
        inset: 0;
        z-index: 999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .custom-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
    }
    .custom-modal-dialog {
        position: relative;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        width: 100%;
        max-width: 580px;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        z-index: 10;
    }
    .custom-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem;
        border-bottom: 1px solid var(--border);
    }
    .btn-close-modal {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        color: var(--text-soft);
    }
    .custom-modal-body {
        padding: 1.2rem;
        overflow-y: auto;
        flex: 1;
    }
    .custom-modal-footer {
        padding: 1rem 1.2rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: .8rem;
    }

    .products-selection-grid {
        display: flex;
        flex-direction: column;
        gap: .6rem;
    }
    .product-pick-item {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .7rem;
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
    }
    .product-pick-img {
        width: 44px;
        height: 44px;
        border-radius: 6px;
        overflow: hidden;
        background: #000;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .product-pick-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-pick-details {
        flex: 1;
        min-width: 0;
    }
    .product-pick-title {
        font-size: .88rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .product-pick-price {
        font-size: .8rem;
        color: var(--brand-1);
        font-weight: 600;
        margin-top: .15rem;
    }

    @media (max-width: 820px) {
        .studio-container {
            padding: 1rem .75rem 4.5rem !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
        }
        .studio-card {
            padding: 1.1rem .85rem !important;
            margin-bottom: 2rem !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
        }
        .studio-grid {
            grid-template-columns: 1fr !important;
            gap: 1.75rem !important;
            width: 100% !important;
        }
        .studio-player-col {
            width: 100% !important;
            max-width: 340px !important;
            margin: 0 auto !important;
            align-items: center !important;
        }
    }

    @media (max-width: 640px) {
        .studio-container {
            padding: .6rem .4rem 5.5rem !important;
            overflow-x: hidden !important;
        }
        .studio-container h1 {
            font-size: 1.3rem !important;
        }
        .studio-card {
            padding: 1rem .6rem !important;
            border-radius: var(--radius-sm) !important;
            overflow-x: hidden !important;
        }
        .studio-header-banner {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: .75rem !important;
        }
        .studio-header-title {
            font-size: 1.22rem !important;
            word-break: break-word !important;
        }
        .studio-header-desc {
            font-size: .82rem !important;
        }
        .studio-unlimited-badge {
            align-self: flex-start !important;
        }
        .settings-box {
            padding: .9rem .75rem !important;
        }
    }

    @media (max-width: 400px) {
        .phone-frame {
            max-width: 295px !important;
            height: 524px !important;
            border-radius: 30px !important;
        }
        .player-controls-bar,
        .format-choice-container,
        .export-btn-wrapper,
        .export-progress-container {
            max-width: 295px !important;
        }
        .format-choice-grid {
            grid-template-columns: 1fr !important;
            gap: .45rem !important;
        }
        .time-readout {
            font-size: .68rem !important;
        }
        .btn-tone-chip span {
            font-size: .8rem !important;
        }
    }
</style>

<!-- MODAL SCRIPTS -->
<script>
    function openProductEditModal() {
        const m = document.getElementById('modal-edit-products');
        if (m) m.style.display = 'flex';
    }
    function closeProductEditModal() {
        const m = document.getElementById('modal-edit-products');
        if (m) m.style.display = 'none';
    }

    function submitProductUpdate(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-products');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Mise à jour...';
        }

        const form = document.getElementById('form-update-products');
        const formData = new FormData(form);
        const selectedIds = formData.getAll('selected_product_ids[]');

        const currentAdId = @json($ad->id);
        const url = `/publicite/${currentAdId}/update-products`;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token())
            },
            body: JSON.stringify({
                selected_product_ids: selectedIds
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeProductEditModal();
                if (window.AdVideoStudio && data.visual_config) {
                    window.AdVideoStudio.updateSelectedProducts(data.visual_config.products, data.scripts);
                }
                // Recharger la page pour synchroniser l'UI complète
                window.location.reload();
            } else {
                alert(data.error || 'Erreur lors de la mise à jour des produits');
            }
        })
        .catch(err => {
            alert('Erreur réseau');
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Mettre à jour la vidéo';
            }
        });
    }
</script>

<!-- INITIALISATION DU STUDIO VIDÉO PUBLICITAIRE IA -->
<script src="{{ asset('js/ad-video-studio.js') }}?v={{ file_exists(public_path('js/ad-video-studio.js')) ? filemtime(public_path('js/ad-video-studio.js')) : '2.0' }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.AdVideoStudio !== 'undefined') {
            window.AdVideoStudio.init({
                canvasId: 'ad-video-canvas',
                shopName: @json($shop->name),
                shopPhone: @json($shopPhone),
                shopColor: @json($shop->brand_color ?: '#6d3df2'),
                shopLogoUrl: @json($vc['shop_logo_url'] ?? ''),
                shopCity: @json($shop->contact['city'] ?? ''),
                angle: @json($vc['angle'] ?? 'promotions'),
                tone: @json($vc['tone'] ?? 'dynamique'),
                voice: @json($vc['voice'] ?? 'femme'),
                targetDuration: @json($vc['target_duration'] ?? 20),
                isStorePresentation: @json(! empty($vc['is_store_presentation'])),
                customMessage: @json($vc['custom_message'] ?? ''),
                assetPhrase: @json($vc['asset_phrase'] ?? ''),
                products: @json($vc['products'] ?? []),
                scripts: @json($ad->generated_scripts ?: []),
                exportUrl: @json(route('ads.log_video_export', $ad)),
                csrfToken: @json(csrf_token()),
            });
        }
    });
</script>
@endsection
