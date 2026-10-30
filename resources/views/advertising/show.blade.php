@extends('layouts.app')

@section('title', 'Studio Vidéo & Publicité IA : ' . $ad->channel_label . ' - ' . config('platform.name'))

@section('content')
<div class="container studio-container" style="max-width:1080px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="sparkles" :size="24" /></span>
                <h1 style="font-size:1.85rem;margin:0;font-weight:800">Studio Vidéo & Publicité IA</h1>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <span class="badge badge-ok">{{ $ad->channel_label }}</span>
                <span class="badge badge-draft">{{ $ad->objective_label }}</span>
                <span class="badge badge-draft">{{ $ad->tone_label }}</span>
                @if ($ad->product)
                    <span class="badge badge-draft">Produit : {{ $ad->product->title }}</span>
                @endif
            </div>
        </div>

        <div class="header-actions" style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('ads.create') }}" class="btn btn-primary btn-sm">+ Nouvelle publicité</a>
            <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="folder" :size="15" />
                <span>Bibliothèque</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    @php
        $vc = $ad->visual_config ?? [];
        $productTitle = $vc['product_title'] ?? ($ad->product?->title ?: $shop->name);
        $priceDisplay = $vc['price'] ?? ($ad->product ? number_format($ad->product->price, 0, ',', ' ') . ' FCFA' : '');
        $oldPriceDisplay = $vc['old_price'] ?? null;
        $promoBadge = $vc['headline'] ?? '⚡ NOUVEAUTÉ';
        $shopPhone = $shop->phone ?: ($shop->whatsapp ?: '+228 90 00 00 00');
    @endphp

    <!-- ========================================================================= -->
    <!-- SECTION VEDETTE : STUDIO VIDÉO PUBLICITAIRE IA 9:16 (TIKTOK / REELS / WA)  -->
    <!-- ========================================================================= -->
    <section class="card studio-card">
        <div class="studio-header-banner">
            <div>
                <div class="studio-format-pill">
                    <x-icon name="video" :size="15" />
                    <span>Format 9:16 Vertical (TikTok • Reels • WhatsApp)</span>
                </div>
                <h2 class="studio-header-title">
                    Générez votre Vidéo Publicitaire Animée en 1 Clic
                </h2>
                <p class="studio-header-desc">
                    L'IA a composé le rythme, le zoom dynamique sur votre produit et le bouton de commande WhatsApp. Prévisualisez et téléchargez votre vidéo gratuitement.
                </p>
            </div>

            <div class="studio-unlimited-badge">
                <span class="unlimited-dot"></span>
                <span>Rendu Navigateur Illimité • 0 FCFA</span>
            </div>
        </div>

        <div class="studio-grid">
            <!-- COLONNE GAUCHE : LECTEUR SIMULATEUR SMARTPHONE 9:16 -->
            <div class="studio-player-col">
                <div class="phone-frame">
                    <!-- Dynamic Island / Caméra encoche -->
                    <div class="phone-notch"></div>

                    <!-- Badge de scène en haut à gauche -->
                    <div class="phone-scene-badge" id="ad-scene-indicator">
                        Scène 1 : Accroche Flash ⚡
                    </div>

                    <!-- Canvas HD 9:16 (720 x 1280) -->
                    <canvas id="ad-video-canvas" width="720" height="1280" class="ad-canvas"></canvas>

                    <!-- Overlay Play/Pause central -->
                    <div id="ad-center-play-overlay" class="center-play-overlay">
                        <div class="center-play-icon">
                            <x-icon name="play" :size="38" />
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

                    <span id="ad-time-display" class="time-readout">00:00.0 / 00:24.0</span>

                    <button type="button" id="ad-btn-mute" class="btn-ctrl-mute active" title="Son activé / désactivé" aria-label="Activer ou couper le son">
                        <span id="icon-sound-on"><x-icon name="volume-2" :size="18" /></span>
                        <span id="icon-sound-off" style="display:none"><x-icon name="volume-x" :size="18" /></span>
                    </button>
                </div>

                <!-- Sélecteur de format d'export : MP4 vs WebM -->
                <!-- Sélecteur de format d'export : MP4 vs WebM -->
                <div class="format-choice-container">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;gap:.5rem;flex-wrap:wrap">
                        <label style="font-size:.8rem;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin:0">
                            Format d'exportation vidéo :
                        </label>
                        <span id="ad-format-tag" class="badge badge-ok" style="font-size:.68rem;padding:.15rem .45rem">MP4 sélectionné</span>
                    </div>

                    <div class="format-choice-grid">
                        <button type="button" class="btn-format-pick selected" data-format-choice="mp4" id="btn-format-mp4">
                            <div class="format-pick-radio">
                                <span class="format-radio-dot"></span>
                            </div>
                            <div class="format-pick-info">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:.4rem">
                                    <strong style="font-size:.9rem;color:var(--text)">Format MP4</strong>
                                    <span class="badge badge-ok" style="font-size:.65rem;padding:.12rem .45rem;flex-shrink:0;white-space:nowrap">Top WhatsApp</span>
                                </div>
                                <small style="font-size:.74rem;color:var(--text-soft);display:block;margin-top:.2rem">Recommandé TikTok, WhatsApp & Reels</small>
                            </div>
                        </button>

                        <button type="button" class="btn-format-pick" data-format-choice="webm" id="btn-format-webm">
                            <div class="format-pick-radio">
                                <span class="format-radio-dot"></span>
                            </div>
                            <div class="format-pick-info">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:.4rem">
                                    <strong style="font-size:.9rem;color:var(--text)">Format WebM</strong>
                                    <span class="badge badge-draft" style="font-size:.65rem;padding:.12rem .45rem;flex-shrink:0;white-space:nowrap">Ultra-léger</span>
                                </div>
                                <small style="font-size:.74rem;color:var(--text-soft);display:block;margin-top:.2rem">Plus compressé pour les navigateurs</small>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Bouton Téléchargement Vidéo Haute Conversion -->
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
                            Enregistrement direct haute fidélité avec musique et voix-off intégrées. Téléchargement automatique.
                        </p>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE : PERSONNALISATION & RÉGLAGES STUDIO -->
            <div class="studio-settings-col">
                <!-- 1. Choix du Script IA -->
                <div class="settings-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;flex-wrap:wrap;gap:.5rem">
                        <h3 class="settings-title" style="margin:0">
                            <x-icon name="sparkles" :size="16" style="color:var(--brand-2)" />
                            <span>1. Script & Déroulé Vidéo</span>
                        </h3>
                        <form method="POST" action="{{ route('ads.regenerate', $ad) }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-secondary" style="font-size:.76rem;padding:.3rem .7rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer" title="Ré-analyser le produit et générer de nouveaux scripts sur-mesure">
                                <x-icon name="sparkles" :size="13" style="color:var(--brand-1)" />
                                <span>Régénérer avec l'IA</span>
                            </button>
                        </form>
                    </div>
                    <p class="settings-desc">Sélectionnez le scénario qui correspond le mieux à votre objectif du jour :</p>

                    <div class="script-cards-grid">
                        @foreach ($ad->generated_scripts as $idx => $script)
                            <div class="script-pick-card {{ $idx === 0 ? 'selected' : '' }}" data-script-choice="{{ $idx }}">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;margin-bottom:.35rem">
                                    <strong style="font-size:.92rem;flex:1;min-width:0;line-height:1.35">{{ $script['title'] }}</strong>
                                    <span class="badge badge-ok" style="font-size:.72rem;flex-shrink:0;white-space:nowrap;padding:.15rem .45rem">20-25 sec</span>
                                </div>
                                <div style="font-size:.82rem;color:var(--text-soft);line-height:1.4">
                                    {{ $script['concept'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 2. Style de Réalisation Vidéo (CapCut, Présentateur UGC, Lookbook Luxe) -->
                <div class="settings-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem">
                        <h3 class="settings-title" style="margin:0">
                            <x-icon name="video" :size="16" style="color:var(--brand-1)" />
                            <span>2. Style de Réalisation Vidéo</span>
                        </h3>
                        <span id="ad-style-badge" class="badge badge-ok" style="font-size:.7rem">TikTok CapCut Pro</span>
                    </div>
                    <p class="settings-desc">Choisissez l'univers visuel et le rythme de mise en avant du produit :</p>

                    <div class="video-styles-grid">
                        <button type="button" class="video-style-card selected" data-video-style="capcut" id="style-btn-capcut">
                            <div class="video-style-icon-wrap" style="background:linear-gradient(135deg, #ec4899, #8b5cf6)">
                                <x-icon name="sparkles" :size="20" style="color:#fff" />
                            </div>
                            <div class="video-style-content">
                                <div style="display:flex;align-items:center;justify-content:space-between">
                                    <strong style="font-size:.92rem;color:var(--text)">TikTok / CapCut Pro</strong>
                                    <span class="badge badge-ok" style="font-size:.65rem">Recommandé</span>
                                </div>
                                <small style="font-size:.76rem;color:var(--text-soft);display:block;margin-top:.2rem">Plein écran immersif, zooms cinématiques 3D & textes géants animés</small>
                            </div>
                        </button>

                        <button type="button" class="video-style-card" data-video-style="ugc" id="style-btn-ugc">
                            <div class="video-style-icon-wrap" style="background:linear-gradient(135deg, #10b981, #06b6d4)">
                                <x-icon name="user" :size="20" style="color:#fff" />
                            </div>
                            <div class="video-style-content">
                                <div style="display:flex;align-items:center;justify-content:space-between">
                                    <strong style="font-size:.92rem;color:var(--text)">Présentateur UGC IA</strong>
                                    <span class="badge badge-primary" style="font-size:.65rem">Tendance</span>
                                </div>
                                <small style="font-size:.76rem;color:var(--text-soft);display:block;margin-top:.2rem">Avis créateur face caméra avec onde vocale, avatar & sous-titres</small>
                            </div>
                        </button>

                        <button type="button" class="video-style-card" data-video-style="lookbook" id="style-btn-lookbook">
                            <div class="video-style-icon-wrap" style="background:linear-gradient(135deg, #f59e0b, #b45309)">
                                <x-icon name="layers" :size="20" style="color:#fff" />
                            </div>
                            <div class="video-style-content">
                                <div style="display:flex;align-items:center;justify-content:space-between">
                                    <strong style="font-size:.92rem;color:var(--text)">Showcase Lookbook Luxe</strong>
                                    <span class="badge badge-draft" style="font-size:.65rem">Prestige</span>
                                </div>
                                <small style="font-size:.76rem;color:var(--text-soft);display:block;margin-top:.2rem">Esthétique épurée, reflets dorés, zoom matière & finitions VIP</small>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 3. Thème Visuel & Palette -->
                <div class="settings-box">
                    <h3 class="settings-title">
                        <x-icon name="palette" :size="16" style="color:var(--brand-1)" />
                        <span>3. Thème Graphique & Ambiance</span>
                    </h3>
                    <div class="theme-chips-grid">
                        <button type="button" class="theme-chip selected" data-theme-choice="boutique">
                            <span class="theme-dot" style="background:{{ $shop->brand_color ?: '#6d3df2' }}"></span>
                            <div>
                                <div style="font-weight:700;font-size:.85rem">Boutique Pulse</div>
                                <div style="font-size:.75rem;color:var(--text-soft)">Aux couleurs de votre boutique</div>
                            </div>
                        </button>

                        <button type="button" class="theme-chip" data-theme-choice="gold">
                            <span class="theme-dot" style="background:linear-gradient(135deg, #f59e0b, #d97706)"></span>
                            <div>
                                <div style="font-weight:700;font-size:.85rem">Luxe & Or</div>
                                <div style="font-size:.75rem;color:var(--text-soft)">Obsidienne et doré prestige</div>
                            </div>
                        </button>

                        <button type="button" class="theme-chip" data-theme-choice="flash">
                            <span class="theme-dot" style="background:#10b981"></span>
                            <div>
                                <div style="font-weight:700;font-size:.85rem">Vente Flash</div>
                                <div style="font-size:.75rem;color:var(--text-soft)">Vert WhatsApp électrique</div>
                            </div>
                        </button>

                        <button type="button" class="theme-chip" data-theme-choice="urgent">
                            <span class="theme-dot" style="background:#ef4444"></span>
                            <div>
                                <div style="font-weight:700;font-size:.85rem">Promo Urgente</div>
                                <div style="font-size:.75rem;color:var(--text-soft)">Rouge feu et déstockage</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 4. Musique de fond & Rythme -->
                <div class="settings-box">
                    <h3 class="settings-title">
                        <x-icon name="volume-2" :size="16" style="color:#25D366" />
                        <span>4. Rythme & Bande Son</span>
                    </h3>
                    <div class="audio-chips-grid">
                        <button type="button" class="audio-chip selected" data-audio-choice="afrobeat">
                            <span>🎵 Afrobeat Pop</span>
                            <small>Entraînant & Chaleureux</small>
                        </button>

                        <button type="button" class="audio-chip" data-audio-choice="amapiano">
                            <span>🥁 Amapiano Dance</span>
                            <small>Basses modernes & Tendance</small>
                        </button>

                        <button type="button" class="audio-chip" data-audio-choice="electro">
                            <span>⚡ Electro Flash</span>
                            <small>Punchy & Vendeur</small>
                        </button>

                        <button type="button" class="audio-chip" data-audio-choice="none">
                            <span>🔇 Sans musique</span>
                            <small>Silencieux</small>
                        </button>
                    </div>
                </div>

                <!-- 5. Voix-Off IA Narratrice intégrée -->
                <div class="settings-box" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
                    <div>
                        <div style="font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:.4rem">
                            <x-icon name="user" :size="16" />
                            <span>5. Voix-Off IA (Français)</span>
                            <span id="ad-voice-status" class="badge badge-ok" style="font-size:.68rem;padding:.15rem .45rem">Voix prête</span>
                        </div>
                        <p style="font-size:.8rem;color:var(--text-soft);margin:.2rem 0 0">
                            Voix narrative française haute fidélité intégrée directement dans le fichier vidéo MP4/WebM téléchargé.
                        </p>
                    </div>
                    <label class="switch-toggle" title="Activer / désactiver la voix-off">
                        <input type="checkbox" id="ad-toggle-voiceover" checked>
                        <span class="slider-round"></span>
                    </label>
                </div>

                <!-- Conseils pour vendre sur WhatsApp -->
                <div style="background:rgba(37,211,102,0.08);border:1px dashed rgba(37,211,102,0.35);padding:1rem 1.2rem;border-radius:var(--radius-sm)">
                    <div style="font-weight:700;font-size:.9rem;color:#128C7E;display:flex;align-items:center;gap:.4rem;margin-bottom:.3rem">
                        <x-icon name="whatsapp" :size="16" />
                        <span>Astuce de vente Statut WhatsApp Togo :</span>
                    </div>
                    <p style="font-size:.82rem;color:var(--text-soft);margin:0;line-height:1.5">
                        Postez cette vidéo directement en statut le matin entre <strong>7h et 9h</strong> ou le soir entre <strong>18h et 21h</strong> pour toucher le maximum de vos contacts connectés.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- BLOC 1 : VARIANTES DE TEXTES PUBLICITAIRES PRÊTS À PUBLIER (CDC H-02)       -->
    <!-- ========================================================================= -->
    <div style="margin-bottom:3rem">
        <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
            <span class="svg-icon" style="color:var(--brand-1)"><x-icon name="edit" :size="18" /></span>
            <span>3 Variantes de Textes Prêts à Publier (H-02)</span>
        </h2>
        <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
            Choisissez la variante qui correspond le mieux à votre style et copiez-la ou partagez-la directement.
        </p>

        <div style="display:grid;grid-template-columns:1fr;gap:1.5rem">
            @foreach ($ad->generated_texts as $index => $text)
                <div class="card" style="padding:1.5rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.6rem">
                        <strong style="font-size:.95rem;color:var(--brand-2)">Proposition #{{ $index + 1 }}</strong>
                        <div style="font-size:.8rem;color:var(--text-soft)">{{ mb_strlen($text) }} caractères</div>
                    </div>

                    <div id="ad-text-{{ $index }}" style="font-size:1rem;line-height:1.7;color:var(--text);white-space:pre-line;background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);margin-bottom:1.2rem;border:1px solid var(--border)">
                        {{ $text }}
                    </div>

                    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('ad-text-{{ $index }}').innerText); window.BoutiqToast ? window.BoutiqToast('Texte publicitaire copié dans le presse-papier !', 'success') : alert('Texte copié !');" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="copy" :size="15" />
                            <span>Copier le texte</span>
                        </button>

                        <a href="https://wa.me/?text={{ urlencode($text) }}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="whatsapp" :size="16" />
                            <span>Partager sur mon statut WhatsApp</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- BLOC 2 : SCRIPTS DÉTAILLÉS DE TOURNAGE TIKTOK / REELS (CDC H-04)           -->
    <!-- ========================================================================= -->
    @if (! empty($ad->generated_scripts))
        <div style="margin-bottom:3rem">
            <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="video" :size="20" /></span>
                <span>Idées & Scripts de Vidéos TikTok / Reels (H-04)</span>
            </h2>
            <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
                Si vous préférez filmer vous-même le produit en main ou face caméra, suivez ces étapes minutées.
            </p>

            <div style="display:grid;grid-template-columns:1fr;gap:1.5rem">
                @foreach ($ad->generated_scripts as $script)
                    <div class="card" style="padding:1.5rem">
                        <h3 style="margin-top:0;font-size:1.15rem">{{ $script['title'] }}</h3>
                        <p style="font-size:.9rem;color:var(--text-soft);margin-bottom:1.2rem;display:flex;align-items:center;gap:.4rem">
                            <x-icon name="sparkles" :size="14" style="color:var(--brand-2)" />
                            <em>{{ $script['concept'] }}</em>
                        </p>

                        <div style="display:flex;flex-direction:column;gap:1rem">
                            @foreach ($script['steps'] as $step)
                                <div style="display:flex;gap:1rem;align-items:start;background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);border:1px solid var(--border)">
                                    <span style="font-weight:700;font-family:monospace;font-size:.85rem;background:var(--surface);padding:.2rem .6rem;border-radius:6px;flex-shrink:0">
                                        {{ $step['time'] }}
                                    </span>
                                    <div style="flex:1">
                                        <div style="font-size:.9rem;color:var(--text-soft);margin-bottom:.3rem;display:flex;align-items:center;gap:.4rem">
                                            <x-icon name="camera" :size="14" />
                                            <span><strong>Visuel caméra :</strong> {{ $step['visual'] }}</span>
                                        </div>
                                        <div style="font-size:.95rem;font-weight:600;color:var(--text);display:flex;align-items:center;gap:.4rem">
                                            <x-icon name="user" :size="14" />
                                            <span><strong>Voix off / Paroles :</strong> « {{ $step['voiceover'] }} »</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- BLOC 3 : CARTE VISUELLE RÉSEAUX SOCIAUX (CDC H-03)                        -->
    <!-- ========================================================================= -->
    @if (! empty($ad->visual_config))
        <div>
            <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
                <span class="svg-icon" style="color:var(--brand-1)"><x-icon name="palette" :size="20" /></span>
                <span>Visuel Promotionnel Statique (H-03)</span>
            </h2>
            <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
                Affiche prête pour vos publications statiques et stories Instagram.
            </p>

            <div style="display:flex;justify-content:center">
                <div style="width:340px;background:linear-gradient(135deg, {{ $vc['brand_color'] ?? '#6d3df2' }}, #0d0d1a);border-radius:24px;padding:2rem 1.5rem;color:#fff;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.3);position:relative;overflow:hidden">
                    <div style="position:absolute;top:-40px;right:-40px;width:120px;height:120px;background:rgba(255,255,255,0.1);border-radius:50%"></div>

                    <div style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;font-weight:700;opacity:.8;margin-bottom:.5rem">
                        {{ $vc['shop_name'] ?? $shop->name }}
                    </div>

                    <div style="display:inline-block;background:var(--danger);color:#fff;font-size:.8rem;font-weight:700;padding:.3rem .8rem;border-radius:20px;margin-bottom:1.5rem">
                        {{ $vc['headline'] ?? 'NOUVEAUTÉ' }}
                    </div>

                    <div style="width:160px;height:160px;background:rgba(255,255,255,0.15);border-radius:18px;margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;border:2px dashed rgba(255,255,255,0.3);overflow:hidden">
                        @if (! empty($productImageUrl))
                            <img src="{{ $productImageUrl }}" alt="{{ $vc['product_title'] ?? '' }}" style="width:100%;height:100%;object-fit:cover">
                        @else
                            <x-icon name="shopping-bag" :size="54" />
                        @endif
                    </div>

                    <h3 style="font-size:1.25rem;margin:0 0 .8rem;line-height:1.3">
                        {{ $vc['product_title'] ?? $productTitle }}
                    </h3>

                    <div style="background:rgba(0,0,0,0.3);padding:.8rem 1rem;border-radius:12px;display:inline-block;margin-bottom:1.5rem">
                        <span style="font-size:1.5rem;font-weight:700;font-family:var(--font-display)">
                            {{ $vc['price'] ?? $priceDisplay }}
                        </span>
                        @if (! empty($vc['old_price']))
                            <span style="font-size:.9rem;opacity:.7;text-decoration:line-through;margin-left:.5rem">
                                {{ $vc['old_price'] }}
                            </span>
                        @endif
                    </div>

                    <div style="font-size:.85rem;opacity:.9;display:flex;align-items:center;justify-content:center;gap:.4rem">
                        <x-icon name="whatsapp" :size="15" />
                        <span>Commande rapide WhatsApp : <strong>{{ $vc['phone'] ?: 'Contact en bio' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- ========================================================================= -->
<!-- MODAL DE SUCCÈS D'EXPORTATION VIDÉO                                        -->
<!-- ========================================================================= -->
<div id="ad-export-success-modal" class="custom-modal-backdrop" style="display:none">
    <div class="custom-modal-card">
        <div style="width:64px;height:64px;background:rgba(37,211,102,0.15);color:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
            <x-icon name="check" :size="34" />
        </div>
        <h3 style="font-size:1.4rem;font-weight:800;margin:0 0 .5rem;text-align:center">
            Vidéo Générée & Téléchargée !
        </h3>
        <p style="font-size:.92rem;color:var(--text-soft);text-align:center;margin:0 0 1.2rem">
            Le fichier <strong id="ad-modal-filename" style="color:var(--text)">pub_video.webm</strong> a été enregistré sur votre appareil.
        </p>

        <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.5rem">
            <strong style="font-size:.85rem;color:var(--brand-2);display:block;margin-bottom:.5rem">Comment la publier maintenant :</strong>
            <ol style="margin:0;padding-left:1.2rem;font-size:.85rem;color:var(--text);line-height:1.6">
                <li>Ouvrez <strong>WhatsApp</strong> > onglet <strong>Actus / Statut</strong>.</li>
                <li>Appuyez sur la caméra pour ajouter un statut et sélectionnez la vidéo téléchargée.</li>
                <li>Copiez l'un des textes publicitaires ci-dessus en légende et validez !</li>
            </ol>
        </div>

        <div style="display:flex;gap:.8rem;justify-content:center">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('ad-export-success-modal').style.display='none'">
                Fermer
            </button>
            <a href="https://web.whatsapp.com" target="_blank" class="btn btn-primary" style="background:#25D366;border-color:#25D366;display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="whatsapp" :size="16" />
                <span>Ouvrir WhatsApp</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* STYLES DU STUDIO VIDÉO */
    .studio-container {
        padding: 2rem 1rem;
        box-sizing: border-box;
        width: 100%;
        max-width: 1080px;
        margin: 0 auto;
    }

    .studio-card {
        padding: 1.8rem;
        margin-bottom: 3rem;
        background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: 0 12px 35px rgba(0,0,0,0.06);
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
        width: 100%;
    }

    .studio-header-banner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        width: 100%;
        box-sizing: border-box;
    }

    .studio-format-pill {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        background: linear-gradient(135deg, rgba(109,61,242,0.15), rgba(236,72,153,0.15));
        color: var(--brand-1);
        padding: .35rem .85rem;
        border-radius: 20px;
        font-size: .82rem;
        font-weight: 700;
        margin-bottom:.6rem;
        border: 1px solid rgba(109,61,242,0.25);
        max-width: 100%;
        box-sizing: border-box;
    }

    .studio-header-title {
        font-size: 1.5rem;
        margin: 0 0 .4rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .studio-header-desc {
        color: var(--text-soft);
        margin: 0;
        font-size: .95rem;
        line-height: 1.45;
    }

    .studio-unlimited-badge {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        background: rgba(16,185,129,0.1);
        color: #10b981;
        border: 1px solid rgba(16,185,129,0.25);
        padding: .4rem .9rem;
        border-radius: 20px;
        font-size: .82rem;
        font-weight: 700;
        max-width: 100%;
        box-sizing: border-box;
    }

    .unlimited-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        box-shadow: 0 0 8px #10b981;
        flex-shrink: 0;
    }

    .studio-grid {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 2rem;
        align-items: start;
        margin-top: 1rem;
        width: 100%;
        box-sizing: border-box;
    }

    .studio-player-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        max-width: 340px;
        margin: 0 auto;
        width: 100%;
        box-sizing: border-box;
    }

    /* Cadre Smartphone 9:16 réaliste */
    .phone-frame {
        width: 100%;
        max-width: 320px;
        aspect-ratio: 9 / 16;
        border-radius: 36px;
        background: #090610;
        border: 8px solid #1f1b2e;
        box-shadow: 0 20px 50px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.08);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        box-sizing: border-box;
    }

    .phone-notch {
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        width: 86px;
        height: 18px;
        background: #000;
        border-radius: 12px;
        z-index: 10;
    }

    .phone-scene-badge {
        position: absolute;
        top: 42px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10;
        font-size: .72rem;
        font-weight: 700;
        padding: .25rem .75rem;
        border-radius: 14px;
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        backdrop-filter: blur(8px);
        white-space: nowrap;
        pointer-events: none;
        transition: all .3s ease;
    }

    .ad-canvas {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        cursor: pointer;
    }

    .center-play-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.35);
        cursor: pointer;
        transition: opacity .25s ease;
        z-index: 5;
    }

    .center-play-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: rgba(255,255,255,0.9);
        color: var(--brand-1);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 30px rgba(0,0,0,0.4);
        padding-left: 4px;
        transition: transform .2s ease;
    }

    .center-play-icon:hover {
        transform: scale(1.08);
    }

    /* Barre de contrôle */
    .player-controls-bar {
        display: flex;
        align-items: center;
        gap: .55rem;
        width: 100%;
        max-width: 320px;
        margin: .85rem auto 0;
        background: var(--surface);
        border: 1px solid var(--border);
        padding: .5rem .75rem;
        border-radius: 16px;
        box-sizing: border-box;
    }

    .btn-ctrl-play, .btn-ctrl-mute {
        background: none;
        border: none;
        color: var(--text);
        cursor: pointer;
        padding: .35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: background .2s ease;
    }

    .btn-ctrl-play:hover, .btn-ctrl-mute:hover {
        background: var(--bg-soft);
    }

    .timeline-slider {
        flex: 1;
        accent-color: var(--brand-1);
        cursor: pointer;
        height: 5px;
    }

    .time-readout {
        font-family: monospace;
        font-size: .75rem;
        color: var(--text-soft);
        white-space: nowrap;
    }

    /* Sélecteur de format MP4 vs WebM */
    .format-choice-container {
        width: 100%;
        max-width: 320px;
        margin: 1rem auto .8rem;
        box-sizing: border-box;
    }

    .format-choice-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .55rem;
        width: 100%;
        box-sizing: border-box;
    }

    .btn-format-pick {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .65rem .8rem;
        background: var(--surface);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        text-align: left;
        transition: all .2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .btn-format-pick:hover {
        border-color: rgba(109,61,242,0.4);
    }

    .btn-format-pick.selected {
        border-color: var(--brand-1);
        background: rgba(109,61,242,0.08);
        box-shadow: 0 2px 8px rgba(109,61,242,0.15);
    }

    .format-pick-radio {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: border-color .2s ease;
    }

    .btn-format-pick.selected .format-pick-radio {
        border-color: var(--brand-1);
    }

    .format-radio-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: transparent;
        transition: background .2s ease;
    }

    .btn-format-pick.selected .format-radio-dot {
        background: var(--brand-1);
    }

    .export-btn-wrapper {
        width: 100%;
        max-width: 320px;
        margin: 0 auto;
        box-sizing: border-box;
    }

    /* Bouton Téléchargement Haute Conversion */
    .btn-export-video {
        position: relative;
        width: 100%;
        max-width: 320px;
        margin: 0 auto;
        display: block;
        box-sizing: border-box;
        border: none;
        border-radius: 18px;
        padding: .95rem 1.1rem;
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        color: #fff;
        cursor: pointer;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(109,61,242,0.35);
        transition: all .25s ease;
    }

    .btn-export-video:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(109,61,242,0.45);
    }

    .btn-export-content {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .8rem;
        text-align: left;
    }

    .btn-export-content strong {
        display: block;
        font-size: 1.02rem;
        letter-spacing: -.01em;
    }

    .btn-export-content small {
        display: block;
        font-size: .78rem;
        opacity: .9;
        margin-top: .15rem;
    }

    /* Barre de progression d'enregistrement */
    .export-progress-container {
        margin-top: .8rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: .8rem;
    }

    .export-progress-track {
        width: 100%;
        height: 8px;
        background: var(--bg-soft);
        border-radius: 4px;
        overflow: hidden;
    }

    .export-progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #10b981, #25D366);
        border-radius: 4px;
        transition: width .15s ease;
    }

    /* COLONNE RÉGLAGES STUDIO */
    .studio-settings-col {
        display: flex;
        flex-direction: column;
        gap: 1.2rem;
    }

    .settings-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 1.2rem;
    }

    .settings-title {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 .4rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .settings-desc {
        font-size: .85rem;
        color: var(--text-soft);
        margin: 0 0 1rem;
    }

    /* Cartes Scripts */
    .script-cards-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .8rem;
    }

    .script-pick-card {
        padding: .9rem 1rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all .2s ease;
    }

    .script-pick-card:hover {
        border-color: rgba(109,61,242,0.4);
    }

    .script-pick-card.selected {
        border-color: var(--brand-1);
        background: rgba(109,61,242,0.06);
    }

    /* Styles de Réalisation Vidéo (CapCut, UGC, Lookbook) */
    .video-styles-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .65rem;
    }

    .video-style-card {
        display: flex;
        align-items: center;
        gap: .85rem;
        padding: .8rem .95rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        text-align: left;
        width: 100%;
        transition: all .2s ease;
    }

    .video-style-card:hover {
        border-color: rgba(109,61,242,0.4);
        transform: translateY(-1px);
    }

    .video-style-card.selected {
        border-color: var(--brand-1);
        background: rgba(109,61,242,0.08);
        box-shadow: 0 4px 14px rgba(109,61,242,0.15);
    }

    .video-style-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    }

    .video-style-content {
        flex: 1;
        min-width: 0;
    }

    /* Thèmes Visuels */
    .theme-chips-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .7rem;
    }

    .theme-chip {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .75rem .9rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        text-align: left;
        color: var(--text);
        transition: all .2s ease;
    }

    .theme-chip:hover {
        border-color: rgba(109,61,242,0.4);
    }

    .theme-chip.selected {
        border-color: var(--brand-1);
        background: rgba(109,61,242,0.08);
    }

    .theme-dot {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    /* Puces Audio */
    .audio-chips-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .7rem;
    }

    .audio-chip {
        padding: .75rem .9rem;
        background: var(--bg-soft);
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        text-align: left;
        color: var(--text);
        transition: all .2s ease;
    }

    .audio-chip:hover {
        border-color: rgba(37,211,102,0.4);
    }

    .audio-chip.selected {
        border-color: #25D366;
        background: rgba(37,211,102,0.08);
    }

    .audio-chip span {
        display: block;
        font-weight: 700;
        font-size: .85rem;
    }

    .audio-chip small {
        display: block;
        font-size: .75rem;
        color: var(--text-soft);
        margin-top: .15rem;
    }

    /* Switch toggle */
    .switch-toggle {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
        flex-shrink: 0;
    }

    .switch-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider-round {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: var(--border);
        transition: .3s;
        border-radius: 24px;
    }

    .slider-round:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    .switch-toggle input:checked + .slider-round {
        background-color: var(--brand-1);
    }

    .switch-toggle input:checked + .slider-round:before {
        transform: translateX(22px);
    }

    /* Modal de succès */
    .custom-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.65);
        backdrop-filter: blur(5px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .custom-modal-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 24px;
        max-width: 480px;
        width: 100%;
        padding: 2rem 1.8rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    }

    @media (max-width: 860px) {
        .studio-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .theme-chips-grid, .audio-chips-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .studio-container {
            padding: 1rem .65rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .studio-card {
            padding: 1.1rem .8rem !important;
            border-radius: 18px !important;
            margin-bottom: 2rem !important;
            box-sizing: border-box !important;
        }
        .studio-header-title {
            font-size: 1.25rem !important;
        }
        .studio-header-desc {
            font-size: .85rem !important;
        }
        .studio-player-col {
            max-width: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            box-sizing: border-box !important;
        }
        .phone-frame {
            max-width: 270px !important;
            border: 6px solid #1f1b2e !important;
            border-radius: 28px !important;
            margin: 0 auto !important;
            box-sizing: border-box !important;
        }
        .player-controls-bar {
            max-width: 270px !important;
            padding: .45rem .6rem !important;
            gap: .35rem !important;
            margin: .85rem auto 0 !important;
            box-sizing: border-box !important;
        }
        .player-controls-bar .time-readout {
            font-size: .68rem !important;
        }
        .format-choice-container {
            max-width: 270px !important;
            margin: 1rem auto .8rem !important;
            box-sizing: border-box !important;
        }
        .format-choice-grid {
            grid-template-columns: 1fr !important;
            gap: .6rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .btn-format-pick {
            padding: .65rem .75rem !important;
            box-sizing: border-box !important;
        }
        .export-btn-wrapper {
            max-width: 270px !important;
            margin: 0 auto !important;
            box-sizing: border-box !important;
        }
        .btn-export-video {
            max-width: 270px !important;
            padding: .85rem .95rem !important;
            margin: 0 auto !important;
            box-sizing: border-box !important;
        }
        .btn-export-content strong {
            font-size: .95rem !important;
        }
        .btn-export-content small {
            font-size: .72rem !important;
        }
        .export-progress-container {
            max-width: 270px !important;
            margin: .8rem auto 0 !important;
            box-sizing: border-box !important;
        }
        .studio-settings-col {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .settings-box {
            padding: 1rem .85rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .script-pick-card {
            padding: .85rem .9rem !important;
            box-sizing: border-box !important;
        }
    }
</style>

<!-- Initialisation du Studio Vidéo Publicitaire IA -->
<script src="{{ asset('js/ad-video-studio.js') }}?v={{ file_exists(public_path('js/ad-video-studio.js')) ? filemtime(public_path('js/ad-video-studio.js')) : '1.3' }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.AdVideoStudio !== 'undefined') {
            window.AdVideoStudio.init({
                canvasId: 'ad-video-canvas',
                shopName: @json($shop->name),
                shopPhone: @json($shopPhone),
                shopColor: @json($shop->brand_color ?: '#6d3df2'),
                productTitle: @json($productTitle),
                price: @json($priceDisplay),
                oldPrice: @json($oldPriceDisplay),
                promoBadge: @json($promoBadge),
                imageUrl: @json($productImageUrl ?: ''),
                ugcAvatarUrl: @json($ugcAvatarUrl),
                scripts: @json($ad->generated_scripts ?: []),
                exportUrl: @json(route('ads.log_video_export', $ad)),
                csrfToken: @json(csrf_token()),
            });
        }
    });
</script>
@endsection
