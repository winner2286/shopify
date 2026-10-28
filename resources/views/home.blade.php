@extends('layouts.app')

@section('title', config('platform.name') . ' : La plateforme de commerce tout-en-un pour développer votre entreprise')
@section('description', 'Créez votre boutique en ligne, vendez sur WhatsApp et TikTok, encaissez par T-Money, Flooz et cartes, et gérez vos stocks et livraisons facilement.')

@section('content')
<!-- ==========================================================================
     1. HERO SECTION DYNAMIQUE (Style signature Shopify)
     ========================================================================== -->
<section class="shopify-hero" style="position:relative;overflow:hidden">
    <canvas id="braille-ambient-canvas" class="hero-braille-canvas" aria-hidden="true"></canvas>
    <div class="aurora-orb aurora-orb-1" aria-hidden="true"></div>
    <div class="aurora-orb aurora-orb-2" aria-hidden="true"></div>
    <div class="container shopify-hero-grid" style="position:relative;z-index:2">
        <!-- Colonne gauche : Titre, Promesse de valeur, Formulaire email -->
        <div class="hero-content reveal">
            <span class="badge badge-ok floating-badge" style="margin-bottom:1.4rem">
                <span class="braille-loader" title="Moteur Zanika Core">⠋</span>
                <span class="dot"></span>
                <span>Plateforme e-commerce conçue pour l'Afrique</span>
            </span>

            <h1>La plateforme de commerce <span class="cypher-word" data-word="tout-en-un">tout-en-un</span> pour <span class="cypher-word" data-word="développer">développer</span> votre entreprise.</h1>

            <p class="lead">
                <span class="desktop-only">Créez votre boutique en ligne en quelques minutes, vendez sur WhatsApp, TikTok et les réseaux sociaux, encaissez par T-Money, Flooz et cartes, et gérez vos stocks et livraisons sur une seule interface intuitive.</span>
                <span class="mobile-only">Vendez sur le web et WhatsApp. Encaissez par T-Money & Flooz. Gérez tout facilement.</span>
            </p>

            <!-- Formulaire d'inscription instantané (Signature Shopify) -->
            <form action="{{ route('register') }}" method="GET" class="hero-email-form">
                <div class="hero-input-wrap">
                    <input type="email" name="email" placeholder="Entrez votre adresse e-mail" required class="hero-email-input" aria-label="Adresse e-mail">
                    <button id="hero-register" type="submit" class="btn btn-primary hero-email-btn">
                        <span>Démarrer l'essai gratuit</span>
                        <x-icon name="arrow-right" :size="16" />
                    </button>
                </div>
            </form>

            <div class="hero-trust-row">
                <span><x-icon name="check-circle" :size="15" /> 14 jours d'essai gratuit</span>
                <span><x-icon name="check-circle" :size="15" /> Sans carte bancaire</span>
                <span><x-icon name="check-circle" :size="15" /> <span class="desktop-only">Prêt en 3 minutes chrono</span><span class="mobile-only">Prêt en 3 min</span></span>
            </div>
        </div>

        <!-- Colonne droite : Mockup visuel interactif dynamique avec commutateur d'onglets -->
        <div class="reveal">
            <div class="hero-mockup-frame">
                <!-- Barre supérieure navigateur -->
                <div class="mockup-top-bar">
                    <div class="mockup-dots">
                        <span class="mockup-dot red"></span>
                        <span class="mockup-dot yellow"></span>
                        <span class="mockup-dot green"></span>
                    </div>
                    <div class="mockup-url">
                        <span>zanika.tg/wax-lome</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.35rem;color:var(--brand-1);font-weight:600;font-size:.78rem">
                        <span class="live-pulse-indicator"></span>
                        <span>En direct</span>
                    </div>
                </div>

                <!-- Onglets interactifs de changement de vue en direct -->
                <div class="mockup-tabs-bar">
                    <button type="button" class="mockup-tab-btn active" data-mockup-target="view-dashboard">
                        <x-icon name="chart" :size="14" />
                        <span><span class="tab-text-full">Ventes & Commandes</span><span class="tab-text-short">Ventes</span></span>
                    </button>
                    <button type="button" class="mockup-tab-btn" data-mockup-target="view-storefront">
                        <x-icon name="phone" :size="14" />
                        <span><span class="tab-text-full">Vitrine WhatsApp</span><span class="tab-text-short">Vitrine</span></span>
                    </button>
                    <button type="button" class="mockup-tab-btn" data-mockup-target="view-delivery">
                        <x-icon name="truck" :size="14" />
                        <span><span class="tab-text-full">Suivi Livraisons</span><span class="tab-text-short">Livraisons</span></span>
                    </button>
                    <button type="button" class="mockup-tab-btn" data-mockup-target="view-ascii" style="color:#fb7185">
                        <span style="font-family:monospace;font-weight:700"><span class="tab-text-full">⠃ Mode ASCII</span><span class="tab-text-short">⠃ ASCII</span></span>
                    </button>
                </div>

                <!-- Corps du mockup avec vues interchangeables -->
                <div class="mockup-body">
                    <!-- VUE 1 : Tableau de bord des ventes & Graphique interactif (Active par défaut) -->
                    <div id="view-dashboard" class="mockup-view-panel active">
                        <!-- KPI Cards Shopify -->
                        <div class="mockup-kpis">
                            <div class="mockup-kpi">
                                <div class="mockup-kpi-label">Ventes du mois</div>
                                <div class="mockup-kpi-val">2 850 000 F</div>
                                <div class="mockup-kpi-badge">+34.8% vs m-1</div>
                            </div>
                            <div class="mockup-kpi">
                                <div class="mockup-kpi-label">Commandes</div>
                                <div class="mockup-kpi-val">186</div>
                                <div class="mockup-kpi-badge">+18 aujourd'hui</div>
                            </div>
                            <div class="mockup-kpi">
                                <div class="mockup-kpi-label">Mobile Money</div>
                                <div class="mockup-kpi-val">100%</div>
                                <div class="mockup-kpi-badge">T-Money & Flooz</div>
                            </div>
                        </div>

                        <!-- Graphique des ventes de la semaine animé -->
                        <div class="sales-chart-wrap">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                                <span style="font-size:.75rem;font-weight:700">Chiffre d'affaires cette semaine (FCFA)</span>
                                <span style="font-size:.72rem;color:var(--brand-1);font-weight:600">Moyenne : 410 000 F/j</span>
                            </div>
                            <div class="chart-bars">
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:42%" title="Lundi : 210 000 FCFA"></div>
                                    <span class="chart-bar-label">Lun</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:55%" title="Mardi : 280 000 FCFA"></div>
                                    <span class="chart-bar-label">Mar</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:48%" title="Mercredi : 240 000 FCFA"></div>
                                    <span class="chart-bar-label">Mer</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:68%" title="Jeudi : 350 000 FCFA"></div>
                                    <span class="chart-bar-label">Jeu</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:82%" title="Vendredi : 420 000 FCFA"></div>
                                    <span class="chart-bar-label">Ven</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar current" style="height:100%" title="Samedi : 580 000 FCFA"></div>
                                    <span class="chart-bar-label">Sam</span>
                                </div>
                                <div class="chart-bar-col">
                                    <div class="chart-bar" style="height:62%" title="Dimanche : 310 000 FCFA"></div>
                                    <span class="chart-bar-label">Dim</span>
                                </div>
                            </div>
                        </div>

                        <!-- Flux des commandes récentes -->
                        <div class="mockup-order-feed">
                            <div style="font-size:.8rem;font-weight:700;color:var(--text);margin-bottom:.2rem">Flux en temps réel</div>
                            <div class="mockup-order-item">
                                <div>
                                    <div style="font-size:.82rem;font-weight:600">Robe Wax Prestige 2026</div>
                                    <div style="font-size:.72rem;color:var(--text-soft)">Lomé Nyékonakpoè • Payé T-Money</div>
                                </div>
                                <span class="badge badge-ok" style="font-size:.7rem;padding:.15rem .45rem">28 000 F</span>
                            </div>
                            <div class="mockup-order-item">
                                <div>
                                    <div style="font-size:.82rem;font-weight:600">Pack Soins Karité Bio</div>
                                    <div style="font-size:.72rem;color:var(--text-soft)">Adidogomé • En livraison</div>
                                </div>
                                <span class="badge badge-ok" style="font-size:.7rem;padding:.15rem .45rem">15 500 F</span>
                            </div>
                        </div>
                    </div>

                    <!-- VUE 2 : Vitrine WhatsApp Interactive Client -->
                    <div id="view-storefront" class="mockup-view-panel">
                        <div class="interactive-phone-frame">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem">
                                <div>
                                    <strong style="font-size:.9rem">Élégance Wax Lomé</strong>
                                    <div style="font-size:.75rem;color:var(--text-soft)">Sélectionnez votre taille pour tester</div>
                                </div>
                                <span class="badge badge-ok" style="font-size:.7rem">En stock</span>
                            </div>

                            <div style="height:110px;background:linear-gradient(135deg, rgba(158, 27, 50, 0.25), rgba(115, 16, 34, 0.45));border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fda4af;margin-bottom:.8rem">
                                <x-icon name="shirt" :size="48" />
                            </div>

                            <div style="display:flex;justify-content:space-between;align-items:baseline">
                                <strong style="font-size:.95rem">Robe Wax Cintrée "Lomé Glam"</strong>
                                <span id="phone-preview-price" style="font-size:1.1rem;font-weight:800;color:var(--brand-1);transition:transform .2s">28 000 FCFA</span>
                            </div>

                            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">Tailles disponibles (Cliquez pour tester) :</div>
                            <div class="interactive-pills-row">
                                <button type="button" class="interactive-pill-btn active" data-price="28 000 FCFA">Taille S</button>
                                <button type="button" class="interactive-pill-btn" data-price="28 000 FCFA">Taille M</button>
                                <button type="button" class="interactive-pill-btn" data-price="30 000 FCFA">Taille L (+2000 F)</button>
                                <button type="button" class="interactive-pill-btn" data-price="32 000 FCFA">Taille XL (+4000 F)</button>
                            </div>

                            <button id="btn-simulate-wa" type="button" class="btn btn-primary btn-block btn-sm" style="background:#25D366;border-color:#25D366;margin-top:.8rem;gap:.5rem">
                                <x-icon name="whatsapp" :size="16" />
                                <span>Tester la commande sur WhatsApp</span>
                            </button>
                        </div>
                    </div>

                    <!-- VUE 3 : Suivi Livraisons Lomé & Régions -->
                    <div id="view-delivery" class="mockup-view-panel">
                        <div class="delivery-timeline">
                            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:.5rem">
                                <strong style="font-size:.9rem">Suivi du Colis #1084</strong>
                                <span class="badge badge-ok" style="font-size:.72rem">Livreur en route</span>
                            </div>

                            <div class="delivery-step-item done">
                                <div class="delivery-step-icon"><x-icon name="check" :size="14" /></div>
                                <div>
                                    <div style="font-weight:700;font-size:.85rem">14h10 : Commande confirmée</div>
                                    <div style="font-size:.75rem;color:var(--text-soft)">Payé par T-Money (28 000 FCFA)</div>
                                </div>
                            </div>

                            <div class="delivery-step-item done">
                                <div class="delivery-step-icon"><x-icon name="box" :size="14" /></div>
                                <div>
                                    <div style="font-weight:700;font-size:.85rem">14h25 : Colis emballé & étiqueté</div>
                                    <div style="font-size:.75rem;color:var(--text-soft)">Pris en charge par coursier express</div>
                                </div>
                            </div>

                            <div class="delivery-step-item active">
                                <div class="delivery-step-icon"><x-icon name="truck" :size="14" /></div>
                                <div>
                                    <div style="font-weight:700;font-size:.85rem">14h40 : En cours d'acheminement</div>
                                    <div style="font-size:.75rem;color:var(--brand-1);font-weight:600">Arrivée estimée à Nyékonakpoè dans 12 min</div>
                                </div>
                            </div>

                            <div class="delivery-step-item">
                                <div class="delivery-step-icon"><x-icon name="map-pin" :size="14" /></div>
                                <div>
                                    <div style="font-weight:600;font-size:.85rem;color:var(--text-soft)">Remise en main propre</div>
                                    <div style="font-size:.75rem;color:var(--text-faint)">Signature client requise</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- VUE 4 : Mode ASCII & Braille Art (Moteur Cyber Zanika) -->
                    <div id="view-ascii" class="mockup-view-panel">
                        <div class="mockup-ascii-box">
                            <div class="mockup-ascii-header">
                                <span style="display:inline-flex;align-items:center;gap:.4rem">
                                    <span class="braille-loader">⠋</span>
                                    <strong>MATRICE UNICODE BRAILLE (2×4)</strong>
                                </span>
                                <span class="mockup-ascii-tag">Moteur IA v2.5</span>
                            </div>

                            <pre class="mockup-ascii-art">
   ⣀⣤⣶⣶⣶⣶⣤⣄⡀      ⣠⣤⣤⣤⣤⣄      ⢀⣤⣶⣶⣶⣶⣤⣀   
 ⣼⣿⠟⠉    ⠉⠻⣿⣧   ⣼⣿⠋    ⠙⣿⣧   ⣼⣿⠟⠉    ⠉⠻⣿⣧ 
⢸⣿⡇  ⠐⣶⣶⠂  ⢸⣿⡇  ⣿⣿      ⣿⣿  ⢸⣿⡇  ⠐⣶⣶⠂  ⢸⣿⡇
⢸⣿⡇  ⢸⣿⣿   ⢸⣿⡇  ⣿⣿      ⣿⣿  ⢸⣿⡇  ⢸⣿⣿   ⢸⣿⡇
 ⠻⣿⣦⣄⣈⣉⣀⣤⣾⣿⠃   ⠻⣿⣦⣀⣀⣠⣴⣿⠟   ⠻⣿⣦⣄⣈⣉⣀⣤⣾⣿⠃ 
   ⠉⠛⠿⠿⠿⠿⠛⠉       ⠉⠛⠿⠿⠛⠉       ⠉⠛⠿⠿⠿⠿⠛⠉   
  [ ZANIKA CYBER-ENGINE ACTIVE • MATRICE BRAILLE 2×4 ]
                            </pre>

                            <div class="mockup-ascii-stream">
                                <div><span class="stream-ok">✓ [CORE]</span> Pipeline IA : Vectorisation des catalogues en cours...</div>
                                <div><span class="stream-ok">✓ [PAY]</span> Passerelles T-Money & Flooz : Synchro temps réel <span style="color:#fb7185">99.98%</span></div>
                                <div><span class="stream-ok">✓ [DATA]</span> Matrice Braille active : <span id="ascii-stream-coords">X: 128 Y: 64 • Matrice: ⠿⠷⠶</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     RUBAN DÉFILANT CONTINU (Infinite Marquee Ticker)
     ========================================================================== -->
<div class="marquee-container" aria-hidden="true">
    <div class="marquee-track">
        <span class="marquee-item"><x-icon name="whatsapp" :size="16" /> Commande WhatsApp 1-clic</span>
        <span class="marquee-item"><x-icon name="credit-card" :size="16" /> Encaissement T-Money & Flooz</span>
        <span class="marquee-item"><x-icon name="sparkles" :size="16" /> Fiches produits rédigées par l'IA</span>
        <span class="marquee-item"><x-icon name="truck" :size="16" /> Suivi de livraison en direct</span>
        <span class="marquee-item"><x-icon name="check-circle" :size="16" /> 0% de commission sur vos ventes</span>
        <span class="marquee-item"><x-icon name="laptop" :size="16" /> Boutique mobile ultra-rapide</span>
        <span class="marquee-item"><x-icon name="shopping-bag" :size="16" /> Gestion centralisée des stocks</span>
        <span class="marquee-item"><x-icon name="shield" :size="16" /> 14 jours d'essai sans engagement</span>
    </div>
    <div class="marquee-track" aria-hidden="true">
        <span class="marquee-item"><x-icon name="whatsapp" :size="16" /> Commande WhatsApp 1-clic</span>
        <span class="marquee-item"><x-icon name="credit-card" :size="16" /> Encaissement T-Money & Flooz</span>
        <span class="marquee-item"><x-icon name="sparkles" :size="16" /> Fiches produits rédigées par l'IA</span>
        <span class="marquee-item"><x-icon name="truck" :size="16" /> Suivi de livraison en direct</span>
        <span class="marquee-item"><x-icon name="check-circle" :size="16" /> 0% de commission sur vos ventes</span>
        <span class="marquee-item"><x-icon name="laptop" :size="16" /> Boutique mobile ultra-rapide</span>
        <span class="marquee-item"><x-icon name="shopping-bag" :size="16" /> Gestion centralisée des stocks</span>
        <span class="marquee-item"><x-icon name="shield" :size="16" /> 14 jours d'essai sans engagement</span>
    </div>
</div>

<!-- ==========================================================================
     TICKER FLOTTANT DE COMMANDES EN DIRECT (Social Proof Live)
     ========================================================================== -->
<div id="live-orders-toast" class="live-orders-toast">
    <span class="live-pulse-indicator"></span>
    <span id="live-toast-content">
        <strong>Lomé (Nyékonakpoè)</strong> : Robe Wax Prestige (28 000 FCFA) • <em>T-Money</em> <span style="color:var(--text-faint);margin-left:4px">il y a 8s</span>
    </span>
    <button type="button" onclick="document.getElementById('live-orders-toast').style.display='none'" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:0 0 0 .4rem;font-size:.85rem;line-height:1" title="Fermer" aria-label="Fermer">✕</button>
</div>

<!-- ==========================================================================
     2. BANDEAU DE PREUVE SOCIALE AVEC COMPTEURS ANIMÉS (COUNTUP)
     ========================================================================== -->
<section class="social-proof-band">
    <div class="container">
        <div class="stats-grid-shopify">
            <div class="stat-box-shopify reveal">
                <strong data-countup="2500" data-prefix="+">+0</strong>
                <span>Boutiques actives en Afrique</span>
            </div>
            <div class="stat-box-shopify reveal">
                <strong data-countup="15" data-suffix=" min">0 min</strong>
                <span>Pour publier sa boutique en ligne</span>
            </div>
            <div class="stat-box-shopify reveal">
                <strong style="color:var(--brand-1)">0 %</strong>
                <span>Commission cachée sur vos ventes</span>
            </div>
            <div class="stat-box-shopify reveal">
                <strong data-countup="99.98" data-suffix=" %">0 %</strong>
                <span>Disponibilité et paiements garantis</span>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     3. SIMULATEUR D'IA EN DIRECT (INTERACTION CONCRÈTE)
     ========================================================================== -->
<section class="section container">
    <div class="section-head reveal">
        <span class="pillar-tag"><x-icon name="sparkles" :size="16" /> Démonstration interactive</span>
        <h2>Testez l'assistant IA de Zanika en direct</h2>
        <p>Cliquez sur un exemple ou saisissez un produit : observez comment l'IA rédige votre argumentaire de vente et votre publicité WhatsApp en quelques secondes.</p>
    </div>

    <div class="ai-interactive-widget card-border-beam reveal">
        <div style="margin-bottom:.8rem;font-weight:700;font-size:.95rem">Choisissez un exemple à tester en direct :</div>
        <div class="ai-chips-list">
            <button type="button" class="ai-chip active" data-ai-sample="wax" data-label="Robe en pagne Wax Hollandais">
                <x-icon name="sparkles" :size="14" />
                <span>Robe Wax Hollandais</span>
            </button>
            <button type="button" class="ai-chip" data-ai-sample="karite" data-label="Savon noir bio au Karité">
                <x-icon name="sparkles" :size="14" />
                <span>Savon noir au Karité</span>
            </button>
            <button type="button" class="ai-chip" data-ai-sample="tech" data-label="Écouteurs sans fil Pro">
                <x-icon name="package" :size="14" />
                <span>Écouteurs sans fil Pro</span>
            </button>
            <button type="button" class="ai-chip" data-ai-sample="piment" data-label="Piment noir artisanal de Lomé">
                <x-icon name="flame" :size="14" />
                <span>Piment noir togolais</span>
            </button>
        </div>

        <div style="display:flex;gap:.6rem;margin-bottom:1.2rem">
            <input id="ai-sample-input" type="text" value="Robe en pagne Wax Hollandais" class="input" style="flex:1" placeholder="Ou décrivez votre propre produit...">
            <button id="ai-run-btn" type="button" class="btn btn-primary" style="gap:.4rem">
                <x-icon name="sparkles" :size="16" />
                <span>Générer avec l'IA</span>
            </button>
        </div>

        <div style="font-size:.8rem;font-weight:700;color:var(--brand-1);margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem">
            <x-icon name="check" :size="14" />
            <span>Résultat généré instantanément :</span>
        </div>

        <div id="ai-demo-output" class="ai-output-box">
✦ ÉLÉGANCE & ALLURE INTEMPORELLE ✦
Sublimez vos sorties avec cette robe confectionnée sur mesure dans un pagne Wax de qualité supérieure. Coupe flatteuse, finitions haute couture.

• Prix conseillé : 28 500 FCFA
• Tailles : Du S au XXL
• Livraison express : Lomé & toutes régions du Togo

Message WhatsApp prêt pour statut : « Alerte bon plan ! Robe Wax disponible en édition limitée chez nous. Commandez vite avant rupture ! »
        </div>
    </div>
</section>

<!-- ==========================================================================
     4. PILIERS DU COMMERCE TOUT-EN-UN (Style Shopify 2 colonnes)
     ========================================================================== -->
<section class="section container" id="solutions">
    <div class="section-head reveal">
        <span class="pillar-tag"><x-icon name="shop" :size="16" /> Tout pour votre commerce</span>
        <h2>Une seule plateforme pour tout gérer et vendre partout</h2>
        <p>
            <span class="desktop-only">De la création de votre catalogue jusqu'à la livraison entre les mains de votre client, Zanika rassemble tous les outils indispensables au succès de votre commerce.</span>
            <span class="mobile-only">Tout ce qu'il faut pour vendre en ligne et encaisser au Togo.</span>
        </p>
    </div>

    <!-- Sélecteur d'onglets tactile pour mobile (Économise 2000px de scroll) -->
    <div class="mobile-pillars-nav" id="mobile-pillars-nav">
        <button type="button" class="mobile-pillar-tab active" data-pillar-idx="0">
            <x-icon name="laptop" :size="15" />
            <span>01. Boutique</span>
        </button>
        <button type="button" class="mobile-pillar-tab" data-pillar-idx="1">
            <x-icon name="whatsapp" :size="15" />
            <span>02. WhatsApp</span>
        </button>
        <button type="button" class="mobile-pillar-tab" data-pillar-idx="2">
            <x-icon name="credit-card" :size="15" />
            <span>03. Paiements</span>
        </button>
        <button type="button" class="mobile-pillar-tab" data-pillar-idx="3">
            <x-icon name="sparkles" :size="15" />
            <span>04. IA Studio</span>
        </button>
    </div>

    <!-- Pilier 1 : Boutique en ligne -->
    <div class="pillar-row mobile-active reveal" id="fonctionnalites" data-pillar-row="0">
        <div class="pillar-content">
            <div class="pillar-tag"><x-icon name="laptop" :size="16" /> 01. Boutique Web & Mobile</div>
            <h3>
                <span class="desktop-only">Créez votre boutique en ligne sans aucune compétence technique</span>
                <span class="mobile-only">Votre boutique pro prête en quelques minutes</span>
            </h3>
            <p class="desktop-only">Offrez à vos clients un site e-commerce ultra-rapide, moderne et personnalisé à vos couleurs, accessible sur mobile comme sur ordinateur.</p>
            <ul class="pillar-checklist">
                <li><x-icon name="check" :size="16" /> <strong>Design responsive optimisé</strong> <span class="desktop-only">pour les connexions mobiles 3G/4G</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Catalogue & stocks en temps réel</strong> <span class="desktop-only">avec variantes de tailles et couleurs</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Adresse web personnalisée</strong> <span class="desktop-only">ou sous-domaine offert à vie (nom.zanika.tg)</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Fiches produits en 1 clic</strong> <span class="desktop-only">grâce à l'intelligence artificielle</span></li>
            </ul>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Créer ma boutique maintenant →</a>
        </div>
        <div class="pillar-visual">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:.8rem;margin-bottom:1.2rem">
                <strong style="font-size:1.05rem">Gestionnaire de Produits</strong>
                <span class="badge badge-ok"><x-icon name="check" :size="12" /> En ligne</span>
            </div>
            <div style="display:flex;gap:1rem;align-items:center;background:var(--bg-soft);padding:1rem;border-radius:12px;margin-bottom:.8rem">
                <div style="width:50px;height:50px;background:var(--surface);border:1px solid var(--border);border-radius:8px;display:grid;place-items:center;color:var(--brand-3)">
                    <x-icon name="shirt" :size="24" />
                </div>
                <div style="flex:1">
                    <div style="font-weight:700;font-size:.95rem">Ensemble Traditionnel Wax</div>
                    <div style="font-size:.82rem;color:var(--text-soft)">Tailles M, L, XL • Stock : 32 unités</div>
                </div>
                <strong style="color:var(--brand-3);font-size:1.05rem">35 000 F</strong>
            </div>
            <div style="display:flex;gap:1rem;align-items:center;background:var(--bg-soft);padding:1rem;border-radius:12px">
                <div style="width:50px;height:50px;background:var(--surface);border:1px solid var(--border);border-radius:8px;display:grid;place-items:center;color:var(--brand-3)">
                    <x-icon name="beauty" :size="24" />
                </div>
                <div style="flex:1">
                    <div style="font-weight:700;font-size:.95rem">Huile Pure d'Argan & Karité</div>
                    <div style="font-size:.82rem;color:var(--text-soft)">Flacon 100ml • Stock : 54 unités</div>
                </div>
                <strong style="color:var(--brand-3);font-size:1.05rem">12 500 F</strong>
            </div>
        </div>
    </div>

    <!-- Pilier 2 : WhatsApp & Vente Omnicanale -->
    <div class="pillar-row reversed reveal" data-pillar-row="1">
        <div class="pillar-content">
            <div class="pillar-tag"><x-icon name="whatsapp" :size="16" /> 02. Vente Omnicanale</div>
            <h3>
                <span class="desktop-only">Vendez là où vos clients passent leur temps : WhatsApp, TikTok, Réseaux Sociaux</span>
                <span class="mobile-only">Vendez directement sur WhatsApp et TikTok</span>
            </h3>
            <p class="desktop-only">Fini les discussions interminables pour répéter les prix et prendre les coordonnées manuellement. Vos clients commandent en 1 clic et vous recevez un panier propre et prêt à expédier.</p>
            <ul class="pillar-checklist">
                <li><x-icon name="check" :size="16" /> <strong>Commande WhatsApp en 1 clic</strong> <span class="desktop-only">: le client valide son panier et le message complet arrive sur votre WhatsApp</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Liens de produits instantanés</strong> <span class="desktop-only">à partager dans vos statuts, stories et bios TikTok</span></li>
                <li><x-icon name="check" :size="16" /> <strong>QR Codes marchands</strong> <span class="desktop-only">prêts à imprimer pour vos boutiques physiques et cartes</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Commandes centralisées</strong> <span class="desktop-only">: toutes vos commandes dans un seul fichier client</span></li>
            </ul>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Découvrir WhatsApp Commerce →</a>
        </div>
        <div class="pillar-visual">
            <div style="background:var(--surface-solid);border:1px solid rgba(158, 27, 50, 0.3);border-radius:16px;padding:1.5rem;box-shadow:var(--shadow-sm)">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
                    <div style="width:40px;height:40px;border-radius:50%;background:#25D366;color:#ffffff;display:grid;place-items:center">
                        <x-icon name="whatsapp" :size="22" />
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:.95rem">Message Commande Reçu</div>
                        <div style="font-size:.78rem;color:var(--text-soft)">Client : Afi K. (Lomé Agoè)</div>
                    </div>
                </div>
                <div style="background:#161a18;border-left:3px solid #25D366;padding:.85rem;border-radius:6px;font-size:.85rem;line-height:1.6;color:#e2e8f0">
                    « Bonjour ! Je souhaite commander :<br>
                    • 1x Robe Wax Prestige (Taille M) - 28 000 F<br>
                    • Livraison à Agoè Téléphone : +228 90 XX XX XX<br>
                    • Mode de paiement : T-Money »
                </div>
            </div>
        </div>
    </div>

    <!-- Pilier 3 : Paiements & Livraisons intégrés -->
    <div class="pillar-row reveal" data-pillar-row="2">
        <div class="pillar-content">
            <div class="pillar-tag"><x-icon name="credit-card" :size="16" /> 03. Paiements & Logistique</div>
            <h3>
                <span class="desktop-only">Encaissez par T-Money, Flooz et livrez sans tracas partout au Togo</span>
                <span class="mobile-only">Encaissez par T-Money, Flooz & Cash</span>
            </h3>
            <p class="desktop-only">Intégrez immédiatement les modes de paiement préférés de vos clients d'Afrique de l'Ouest et organisez vos livraisons avec vos livreurs habituels en toute transparence.</p>
            <ul class="pillar-checklist">
                <li><x-icon name="check" :size="16" /> <strong>Mobile Money T-Money, Flooz & Wave</strong> <span class="desktop-only">et cartes bancaires</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Paiement à la livraison</strong> <span class="desktop-only">(Cash on Delivery) supporté et sécurisé</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Zones de livraison personnalisées</strong> <span class="desktop-only">: Lomé, banlieues et régions</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Suivi de commande en direct</strong> <span class="desktop-only">envoyé par lien automatique au client</span></li>
            </ul>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Configurer mes paiements →</a>
        </div>
        <div class="pillar-visual">
            <div style="font-weight:700;font-size:1.1rem;margin-bottom:1.2rem">Modes de règlement acceptés</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
                <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:1rem;display:flex;align-items:center;gap:.75rem">
                    <x-icon name="phone" :size="20" style="color:var(--brand-1)" />
                    <div>
                        <div style="font-weight:700;font-size:.9rem">T-Money (Togocom)</div>
                        <div style="font-size:.75rem;color:var(--text-soft)">Instantané & 0% frais cachés</div>
                    </div>
                </div>
                <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:1rem;display:flex;align-items:center;gap:.75rem">
                    <x-icon name="phone" :size="20" style="color:#004c3f" />
                    <div>
                        <div style="font-weight:700;font-size:.9rem">Flooz (Moov Togo)</div>
                        <div style="font-size:.75rem;color:var(--text-soft)">Validation immédiate</div>
                    </div>
                </div>
                <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:1rem;display:flex;align-items:center;gap:.75rem">
                    <x-icon name="truck" :size="20" style="color:var(--brand-1)" />
                    <div>
                        <div style="font-weight:700;font-size:.9rem">Paiement à la livraison</div>
                        <div style="font-size:.75rem;color:var(--text-soft)">En espèces au livreur</div>
                    </div>
                </div>
                <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:1rem;display:flex;align-items:center;gap:.75rem">
                    <x-icon name="credit-card" :size="20" style="color:var(--text)" />
                    <div>
                        <div style="font-weight:700;font-size:.9rem">Cartes Bancaires</div>
                        <div style="font-size:.75rem;color:var(--text-soft)">Visa, Mastercard & Diaspora</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pilier 4 : Intelligence Artificielle & Marketing -->
    <div class="pillar-row reversed reveal" data-pillar-row="3">
        <div class="pillar-content">
            <div class="pillar-tag"><x-icon name="sparkles" :size="16" /> 04. Intelligence Artificielle</div>
            <h3>
                <span class="desktop-only">L'intelligence artificielle qui rédige vos fiches et conçoit vos publicités</span>
                <span class="mobile-only">L'IA qui rédige vos fiches et publicités</span>
            </h3>
            <p class="desktop-only">Gagnez des heures précieuses. Envoyez simplement les photos de vos articles : l'IA reconnaît le produit, rédige un argumentaire commercial percutant et génère vos textes publicitaires.</p>
            <ul class="pillar-checklist">
                <li><x-icon name="check" :size="16" /> <strong>Fiches produits en 5 secondes</strong> <span class="desktop-only">générées à partir de vos photos mobiles</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Studio publicitaire pour statuts WhatsApp</strong> <span class="desktop-only">et SMS captivants</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Scripts de vidéos TikTok & Reels</strong> <span class="desktop-only">guidés pour filmer au téléphone</span></li>
                <li><x-icon name="check" :size="16" /> <strong>Conseils personnalisés de marges</strong> <span class="desktop-only">pour écouler vos invendus</span></li>
            </ul>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Tester l'assistant IA →</a>
        </div>
        <div class="pillar-visual">
            <div style="background:var(--surface-solid);border:1px solid rgba(158, 27, 50, 0.3);border-radius:16px;padding:1.4rem;box-shadow:var(--shadow-sm)">
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--brand-3);font-weight:700;font-size:.9rem;margin-bottom:.8rem">
                    <x-icon name="sparkles" :size="18" />
                    <span>Texte promotionnel généré par l'IA pour WhatsApp</span>
                </div>
                <p style="font-size:.9rem;line-height:1.6;color:var(--text);margin:0;font-style:italic">
                    « Coucou la famille ! Regardez cette merveille qui vient tout juste d'arriver : notre Ensemble Wax Prestige ! Tissu haute qualité, finitions soignées. Disponible à 28 000 FCFA avec livraison express partout à Lomé. Cliquez ici pour commander avant rupture de stock ! »
                </p>
                <div style="margin-top:1rem;display:flex;gap:.5rem">
                    <span class="badge badge-ok"><x-icon name="copy" :size="12" /> Prêt à copier</span>
                    <span class="badge badge-draft"><x-icon name="whatsapp" :size="12" /> Optimisé Statut</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     5. DÉMO PAR SECTEUR D'ACTIVITÉ INTERACTIVE (Dynamique avec onglets)
     ========================================================================== -->
<section class="section section-subtle" id="secteurs">
    <div class="container">
        <div class="section-head reveal">
            <span class="pillar-tag"><x-icon name="tag" :size="16" /> Pour tous les commerces</span>
            <h2>Conçu sur mesure pour votre secteur</h2>
            <p>
                <span class="desktop-only">Cliquez sur votre domaine d'activité pour prévisualiser instantanément une boutique et des produits adaptés.</span>
                <span class="mobile-only">Touchez un domaine pour voir la boutique adaptée.</span>
            </p>
        </div>

        <!-- Boutons de commutation par secteur -->
        <div class="industry-nav-bar reveal">
            <button type="button" class="industry-nav-btn active" data-industry-tab="mode">
                <x-icon name="shirt" :size="16" />
                <span>Mode & Wax</span>
            </button>
            <button type="button" class="industry-nav-btn" data-industry-tab="beaute">
                <x-icon name="beauty" :size="16" />
                <span>Beauté & Cosmétiques</span>
            </button>
            <button type="button" class="industry-nav-btn" data-industry-tab="tech">
                <x-icon name="laptop" :size="16" />
                <span>High-Tech & Gadgets</span>
            </button>
            <button type="button" class="industry-nav-btn" data-industry-tab="food">
                <x-icon name="food" :size="16" />
                <span>Épicerie & Alimentation</span>
            </button>
            <button type="button" class="industry-nav-btn" data-industry-tab="artisanat">
                <x-icon name="pottery" :size="16" />
                <span>Maison & Artisanat</span>
            </button>
        </div>

        <!-- Canvas interactif qui se met à jour dynamiquement au clic -->
        <div id="industry-interactive-content" class="industry-canvas reveal">
            <span class="pillar-tag" style="margin-bottom:.5rem">Secteur Mode & Prêt-à-porter</span>
            <h3 style="font-size:1.6rem;margin-bottom:.8rem">Boutiques de Wax, Vêtements et Accessoires de Mode</h3>
            <p style="max-width:640px;margin-bottom:1.8rem;color:var(--text-soft)">
                Permettez à vos clients de choisir facilement leurs tailles, motifs et coloris de Wax avec une mise à jour des stocks en direct.
            </p>
            <div class="industry-cards-grid">
                <div class="industry-card-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
                        <span class="badge badge-ok" style="font-size:.75rem">Best-seller</span>
                        <span style="font-size:.78rem;color:var(--text-soft)">En stock</span>
                    </div>
                    <h4 style="font-size:1.05rem;margin:0 0 .5rem">Robe Soie & Wax Flamboyant</h4>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--brand-1);margin-bottom:1rem">28 000 FCFA</div>
                    <a href="{{ route('register') }}" class="btn btn-ghost btn-sm btn-block">Voir la démo en direct</a>
                </div>
                <div class="industry-card-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
                        <span class="badge badge-ok" style="font-size:.75rem">Nouveau</span>
                        <span style="font-size:.78rem;color:var(--text-soft)">En stock</span>
                    </div>
                    <h4 style="font-size:1.05rem;margin:0 0 .5rem">Chemise Homme Lin Brodé</h4>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--brand-1);margin-bottom:1rem">22 500 FCFA</div>
                    <a href="{{ route('register') }}" class="btn btn-ghost btn-sm btn-block">Voir la démo en direct</a>
                </div>
                <div class="industry-card-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
                        <span class="badge badge-ok" style="font-size:.75rem">Fait-main</span>
                        <span style="font-size:.78rem;color:var(--text-soft)">En stock</span>
                    </div>
                    <h4 style="font-size:1.05rem;margin:0 0 .5rem">Sandales Cuir Artisanal Lomé</h4>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--brand-1);margin-bottom:1rem">16 000 FCFA</div>
                    <a href="{{ route('register') }}" class="btn btn-ghost btn-sm btn-block">Voir la démo en direct</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     6. TÉMOIGNAGES MARCHANDS (Shopify Merchant Stories)
     ========================================================================== -->
<section class="section container" id="temoignages">
    <div class="section-head reveal">
        <span class="pillar-tag"><x-icon name="users" :size="16" /> Témoignages marchands</span>
        <h2>Ils développent leur entreprise avec Zanika</h2>
        <p>
            <span class="desktop-only">Découvrez comment des entrepreneurs comme vous ont automatisé leurs ventes et multiplié leur chiffre d'affaires.</span>
            <span class="mobile-only">Retours d'expérience de commerçants togolais.</span>
        </p>
    </div>

    <div class="mobile-swipe-hint">
        <span>← Glissez pour voir les retours d'expérience marchands →</span>
    </div>

    <div class="grid grid-3">
        <div class="testimonial-card reveal">
            <blockquote class="testimonial-quote">
                <span class="desktop-only">« Avant Zanika, je perdais un temps fou chaque jour à renvoyer les prix et photos sur WhatsApp et à noter mes commandes sur un carnet. Aujourd'hui, tout est automatisé, mes clientes commandent en 2 minutes et je reçois les alertes T-Money en direct. Mes ventes ont fait un bond de 40% ! »</span>
                <span class="mobile-only">« Mes ventes ont fait un bond de <strong>+40%</strong> ! Mes clientes commandent en 2 minutes sur WhatsApp et je reçois les alertes T-Money en direct. »</span>
            </blockquote>
            <div class="testimonial-author">
                <div class="author-avatar">AK</div>
                <div>
                    <div class="author-name">Abla K.</div>
                    <div class="author-shop">Fondatrice de Lomé Wax Chic (Lomé)</div>
                </div>
            </div>
        </div>

        <div class="testimonial-card reveal">
            <blockquote class="testimonial-quote">
                <span class="desktop-only">« La création de ma boutique n'a pris que 15 minutes avec l'IA. Elle a créé les fiches de mes écouteurs et montres connectées automatiquement. Les clients adorent le paiement par Flooz et le lien de suivi de livraison. C'est le Shopify togolais que nous attendions tous. »</span>
                <span class="mobile-only">« Boutique créée en <strong>15 minutes</strong> avec l'IA. Les clients adorent payer par Flooz et suivre leur colis. C'est le Shopify togolais ! »</span>
            </blockquote>
            <div class="testimonial-author">
                <div class="author-avatar">KD</div>
                <div>
                    <div class="author-name">Koffi D.</div>
                    <div class="author-shop">Gérant de Togo Tech Hub (Agoè)</div>
                </div>
            </div>
        </div>

        <div class="testimonial-card reveal">
            <blockquote class="testimonial-quote">
                <span class="desktop-only">« Le studio publicitaire IA m'a bluffée : il me rédige mes textes WhatsApp en 5 secondes avec les bons emojis et les hashtags. Je n'ai plus besoin de payer une agence de communication pour faire mes promotions du week-end. »</span>
                <span class="mobile-only">« L'IA me rédige mes textes WhatsApp en <strong>5 secondes</strong> avec les bons emojis. Plus besoin de payer une agence chaque week-end. »</span>
            </blockquote>
            <div class="testimonial-author">
                <div class="author-avatar">MA</div>
                <div>
                    <div class="author-name">Mireille A.</div>
                    <div class="author-shop">Créatrice de Karité & Soins Naturels (Nyékonakpoè)</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     SIMULATEUR DE CROISSANCE & GAINS MENSUELS (Animation interactive)
     ========================================================================== -->
<section class="section container" style="padding-bottom:1.5rem">
    <div class="growth-calc-widget reveal">
        <div class="growth-calc-head">
            <span class="pillar-tag"><x-icon name="chart" :size="16" /> Simulateur de rentabilité</span>
            <h3>Estimez ce que Zanika va vous rapporter chaque mois</h3>
            <p>
                <span class="desktop-only">Glissez le curseur selon votre volume de ventes estimé pour observer le temps gagné et les commandes supplémentaires débloquées.</span>
                <span class="mobile-only">Glissez pour estimer vos gains et le temps économisé.</span>
            </p>
        </div>

        <div class="growth-calc-slider-wrap">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
                <span class="growth-calc-slider-title">Volume estimé de commandes par mois :</span>
                <span class="growth-calc-badge"><strong id="calc-orders-display">60</strong> commandes / mois</span>
            </div>
            <input type="range" id="growth-slider" min="10" max="300" step="5" value="60" class="growth-slider" aria-label="Nombre de commandes mensuelles">
            <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--text-faint);margin-top:.4rem">
                <span>10 commandes</span>
                <span>100 commandes</span>
                <span>200 commandes</span>
                <span>300+ commandes</span>
            </div>
        </div>

        <div class="growth-calc-grid">
            <div class="growth-calc-card">
                <div class="growth-calc-icon"><x-icon name="clock" :size="22" /></div>
                <div class="growth-calc-val" id="calc-hours-saved">+30 h</div>
                <div class="growth-calc-label">Temps économisé / mois</div>
                <div class="growth-calc-sub">Fini les réponses manuelles répétitives et la prise de commandes au carnet.</div>
            </div>

            <div class="growth-calc-card highlight">
                <div class="growth-calc-icon"><x-icon name="sparkles" :size="22" /></div>
                <div class="growth-calc-val" id="calc-sales-boost">+210 000 F</div>
                <div class="growth-calc-label">Chiffre d'affaires additionnel</div>
                <div class="growth-calc-sub">Grâce aux paniers WhatsApp instantanés et aux ventes 24h/24 7j/7.</div>
            </div>

            <div class="growth-calc-card">
                <div class="growth-calc-icon"><x-icon name="wallet" :size="22" /></div>
                <div class="growth-calc-val">0 FCFA</div>
                <div class="growth-calc-label">Commission prélevée</div>
                <div class="growth-calc-sub">Vous conservez 100% de votre chiffre d'affaires, sans aucune taxe cachée.</div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     7. GRILLE TARIFAIRE AVEC COMMUTATEUR MENSUEL / ANNUEL
     ========================================================================== -->
<section class="section section-subtle" id="tarifs">
    <div class="container">
        <div class="section-head reveal">
            <span class="pillar-tag"><x-icon name="wallet" :size="16" /> Tarifs transparents</span>
            <h2>Des formules simples pour chaque étape de votre croissance</h2>
            <p>
                <span class="desktop-only">14 jours d'essai gratuit sur toutes les formules. Sans carte bancaire et sans aucun engagement.</span>
                <span class="mobile-only">14 jours d'essai gratuit • 0% de commission sur vos ventes.</span>
            </p>
        </div>

        <!-- Basculeur dynamique Mensuel / Annuel avec remise de 20% -->
        <div class="pricing-billing-switcher reveal">
            <div class="billing-pill-container">
                <button id="btn-billing-monthly" type="button" class="billing-toggle-btn active">Facturation Mensuelle</button>
                <button id="btn-billing-yearly" type="button" class="billing-toggle-btn">Facturation Annuelle</button>
            </div>
            <span class="billing-discount-badge">-20% d'économie</span>
        </div>

        <!-- Sélecteur de formules pour mobile (Accès direct en 1 clic) -->
        <div class="mobile-plan-nav" id="mobile-plan-nav">
            <button type="button" class="mobile-plan-pill" data-plan-idx="0">Starter</button>
            <button type="button" class="mobile-plan-pill active" data-plan-idx="1">⭐ Pro (Recommandé)</button>
            <button type="button" class="mobile-plan-pill" data-plan-idx="2">Business</button>
        </div>
        <div class="mobile-swipe-hint">
            <span>← Glissez pour comparer les 3 formules →</span>
        </div>

        <div class="pricing-grid">
            @foreach ($plans as $plan)
                <div class="plan-shopify reveal {{ $plan->slug === 'pro' ? 'featured card-border-beam' : '' }}">
                    @if ($plan->slug === 'pro')
                        <div class="plan-shopify-badge">Recommandé • Le plus choisi</div>
                    @endif

                    <div class="plan-name">{{ $plan->name }}</div>
                    <div class="plan-desc">
                        @if ($plan->slug === 'starter')
                            Idéal pour lancer son premier commerce en ligne et tester ses premiers produits.
                        @elseif ($plan->slug === 'pro')
                            Pour les boutiques en pleine expansion qui veulent automatiser avec l'IA.
                        @else
                            Pour les grandes marques et commerces établis à fort volume de commandes.
                        @endif
                    </div>

                    <div class="plan-price-wrap">
                        <span class="plan-price" data-plan-monthly="{{ $plan->monthly_price }}">
                            {{ $plan->monthly_price ? number_format($plan->monthly_price, 0, ',', ' ') : 'Bientôt' }}
                        </span>
                        @if ($plan->monthly_price)
                            <span class="plan-period">FCFA / mois</span>
                        @endif
                    </div>

                    <ul class="plan-features">
                        <li>
                            <x-icon name="check" :size="16" />
                            <span>Jusqu'à <strong>{{ number_format($plan->max_products, 0, ',', ' ') }} produits</strong> en catalogue</span>
                        </li>
                        <li>
                            <x-icon name="check" :size="16" />
                            <span><strong>{{ $plan->storage_mb >= 1024 ? ($plan->storage_mb / 1024).' Go' : $plan->storage_mb.' Mo' }}</strong> d'espace de stockage d'images</span>
                        </li>
                        <li>
                            <x-icon name="check" :size="16" />
                            <span><strong>{{ $plan->ai_credits }} crédits d'IA</strong> par mois</span>
                        </li>
                        <li>
                            <x-icon name="check" :size="16" />
                            <span>Commandes WhatsApp en 1 clic</span>
                        </li>
                        <li>
                            <x-icon name="check" :size="16" />
                            <span>Paiements T-Money, Flooz & Cash</span>
                        </li>
                        @foreach ($plan->features ?? [] as $f)
                            <li>
                                <x-icon name="check" :size="16" />
                                <span>{{ $f }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('register') }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-dark' }} btn-block">
                        Démarrer l'essai gratuit
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- ==========================================================================
     8. QUESTIONS FRÉQUENTES (FAQ Accordion Dynamique)
     ========================================================================== -->
<section class="section container" id="faq">
    <div class="section-head reveal">
        <span class="pillar-tag"><x-icon name="info" :size="16" /> Support & Aide</span>
        <h2>Questions fréquemment posées</h2>
        <p>
            <span class="desktop-only">Tout ce que vous devez savoir pour démarrer sereinement sur Zanika.</span>
            <span class="mobile-only">Réponses directes à vos questions fréquentes.</span>
        </p>
    </div>

    <div class="faq-wrap">
        <div class="faq-item active reveal">
            <button type="button" class="faq-question">
                <span>Comment mes clients règlent-ils leurs achats par T-Money ou Flooz ?</span>
                <span class="faq-chevron"><x-icon name="arrow-right" :size="16" /></span>
            </button>
            <div class="faq-answer">
                Vos clients choisissent simplement T-Money ou Flooz lors de la validation de commande. Ils reçoivent les instructions directes avec votre numéro marchand ou le numéro de paiement, et le paiement est immédiatement validé et consigné dans votre tableau de bord.
            </div>
        </div>

        <div class="faq-item reveal">
            <button type="button" class="faq-question">
                <span>Ai-je besoin de connaissances informatiques pour créer ma boutique ?</span>
                <span class="faq-chevron"><x-icon name="arrow-right" :size="16" /></span>
            </button>
            <div class="faq-answer">
                Absolument aucune. L'assistant intelligent vous guide pas-à-pas en français simple : vous décrivez ce que vous vendez, vous chargez les photos de vos articles depuis votre smartphone, et l'IA configure votre boutique automatiquement en 15 minutes chrono.
            </div>
        </div>

        <div class="faq-item reveal">
            <button type="button" class="faq-question">
                <span>Comment se passe la livraison des colis aux clients ?</span>
                <span class="faq-chevron"><x-icon name="arrow-right" :size="16" /></span>
            </button>
            <div class="faq-answer">
                Vous définissez facilement vos zones tarifaires (par exemple : Lomé Centre, Banlieue Agoè/Adidogomé, Intérieur du pays). Lorsque la commande tombe, vous assignez votre livreur habituel ou notre service partenaire, et votre client reçoit un lien de suivi en direct pour réceptionner son colis.
            </div>
        </div>

        <div class="faq-item reveal">
            <button type="button" class="faq-question">
                <span>Puis-je utiliser mon propre nom de domaine personnalisé ?</span>
                <span class="faq-chevron"><x-icon name="arrow-right" :size="16" /></span>
            </button>
            <div class="faq-answer">
                Oui ! Chaque boutique bénéficie d'une adresse gratuite sécurisée (ex: maboutique.zanika.tg) et vous pouvez à tout moment connecter votre propre nom de domaine en .tg, .com ou toute autre extension.
            </div>
        </div>

        <div class="faq-item reveal">
            <button type="button" class="faq-question">
                <span>Prenez-vous une commission sur mon chiffre d'affaires ?</span>
                <span class="faq-chevron"><x-icon name="arrow-right" :size="16" /></span>
            </button>
            <div class="faq-answer">
                Non. Nous appliquons 0% de commission sur vos ventes. Vous payez uniquement votre abonnement mensuel transparent et conservez 100% du fruit de votre travail.
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     9. BANDEAU DE FERMETURE SHOPIFY
     ========================================================================== -->
<section class="shopify-cta-banner">
    <div class="container reveal">
        <h2>
            <span class="desktop-only">Rejoignez la nouvelle génération d'e-commerçants.</span>
            <span class="mobile-only">Lancez votre boutique aujourd'hui.</span>
        </h2>
        <p>
            <span class="desktop-only">Lancez votre boutique en ligne aujourd'hui et commencez à recevoir des commandes sur le Web et WhatsApp en toute simplicité.</span>
            <span class="mobile-only">Recevez vos premières commandes dès ce soir. Prêt en 3 min chrono.</span>
        </p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
            <a href="{{ route('register') }}" class="btn btn-white btn-lg">
                <span>Créer ma boutique gratuitement</span>
                <x-icon name="arrow-right" :size="18" />
            </a>
            <a href="{{ route('login') }}" class="btn btn-ghost btn-lg" style="color:#ffffff;border-color:rgba(255,255,255,0.3)">
                <span>Me connecter</span>
            </a>
        </div>
    </div>
</section>
@endsection

@section('footer')
<!-- ==========================================================================
     10. PIED DE PAGE MULTI-COLONNES STYLE SHOPIFY
     ========================================================================== -->
<footer class="shopify-footer">
    <div class="container">
        <div class="footer-grid-shopify">
            <!-- Colonne Marque -->
            <div>
                <a href="{{ route('home') }}" class="logo" style="margin-bottom:1rem">
                    <span class="logo-mark"><x-icon name="shop" :size="20" /></span>
                    <span>{{ config('platform.name') }}</span>
                </a>
                <p style="font-size:.9rem;line-height:1.6;color:var(--text-soft);max-width:300px">
                    La plateforme de commerce tout-en-un des entreprises et entrepreneurs en Afrique de l'Ouest.
                </p>
                <div style="display:flex;gap:.8rem;color:var(--text-soft);margin-top:1.2rem">
                    <span title="T-Money & Flooz intégrés"><x-icon name="credit-card" :size="20" /></span>
                    <span title="WhatsApp Commerce"><x-icon name="whatsapp" :size="20" /></span>
                    <span title="Protection & Sécurité"><x-icon name="shield" :size="20" /></span>
                </div>
            </div>

            <!-- Colonne Produit -->
            <div>
                <div class="footer-col-title">Produit</div>
                <ul class="footer-col-links">
                    <li><a href="#solutions">Boutique en ligne</a></li>
                    <li><a href="#solutions">WhatsApp Commerce</a></li>
                    <li><a href="#solutions">Paiements T-Money & Flooz</a></li>
                    <li><a href="#solutions">Studio Publicitaire IA</a></li>
                    <li><a href="#solutions">Gestion des livraisons</a></li>
                </ul>
            </div>

            <!-- Colonne Solutions -->
            <div>
                <div class="footer-col-title">Secteurs</div>
                <ul class="footer-col-links">
                    <li><a href="#secteurs">Mode & Wax</a></li>
                    <li><a href="#secteurs">Beauté & Cosmétiques</a></li>
                    <li><a href="#secteurs">High-Tech & Gadgets</a></li>
                    <li><a href="#secteurs">Épicerie & Alimentation</a></li>
                    <li><a href="#secteurs">Artisans & Créateurs</a></li>
                </ul>
            </div>

            <!-- Colonne Ressources -->
            <div>
                <div class="footer-col-title">Ressources</div>
                <ul class="footer-col-links">
                    <li><a href="#tarifs">Tarifs & Abonnements</a></li>
                    <li><a href="#temoignages">Témoignages clients</a></li>
                    <li><a href="#faq">Foire aux questions</a></li>
                    <li><a href="{{ route('login') }}">Espace Marchand</a></li>
                    <li><a href="{{ route('register') }}">Créer un compte</a></li>
                </ul>
            </div>

            <!-- Colonne Légal & Contact -->
            <div>
                <div class="footer-col-title">Assistance</div>
                <ul class="footer-col-links">
                    <li><a href="mailto:support@zanika.tg">Contact Support</a></li>
                    <li><a href="https://wa.me/22890000000" target="_blank">Aide sur WhatsApp</a></li>
                    <li><a href="#">Conditions d'utilisation</a></li>
                    <li><a href="#">Politique de confidentialité</a></li>
                    <li><a href="#">Sécurité des données</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <div>
                © {{ date('Y') }} {{ config('platform.name') }} Technologies. Tous droits réservés.
            </div>
            <div style="display:flex;align-items:center;gap:1.5rem">
                <span style="display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="map-pin" :size="14" />
                    <span>Lomé, Togo (UEMOA)</span>
                </span>
                <span>Devise : FCFA (XOF)</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bouton d'action rapide flottant sur mobile (Sticky CTA) -->
<div id="mobile-sticky-cta" class="mobile-sticky-cta" aria-hidden="true">
    <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:0.88rem;color:#ffffff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Zanika • E-commerce</div>
        <div style="font-size:0.72rem;color:var(--text-soft)">14 jours d'essai gratuit • Prêt en 3 min</div>
    </div>
    <a href="{{ route('register') }}" class="btn btn-primary btn-sm" style="font-weight:700;padding:0.48rem 1rem;white-space:nowrap;box-shadow:0 0 16px rgba(255,45,109,0.4)">
        Démarrer →
    </a>
</div>

<!-- Script d'interaction Mobile-First (Tabs, Carrousels & Sticky CTA) -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Commutateur d'onglets Piliers sur mobile
        var pillarTabs = document.querySelectorAll('.mobile-pillar-tab');
        var pillarRows = document.querySelectorAll('[data-pillar-row]');
        pillarTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var idx = this.getAttribute('data-pillar-idx');
                pillarTabs.forEach(function (t) { t.classList.remove('active'); });
                this.classList.add('active');
                pillarRows.forEach(function (row) {
                    if (row.getAttribute('data-pillar-row') === idx) {
                        row.classList.add('mobile-active');
                    } else {
                        row.classList.remove('mobile-active');
                    }
                });
            });
        });

        // 2. Commutateur de formules Tarifs sur mobile
        var planPills = document.querySelectorAll('.mobile-plan-pill');
        var planCards = document.querySelectorAll('.pricing-grid .plan-shopify');
        var pricingGrid = document.querySelector('.pricing-grid');
        planPills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-plan-idx'), 10);
                planPills.forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                if (planCards[idx]) {
                    planCards[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        });

        // Détecter le défilement horizontal des tarifs pour synchroniser les pilules
        if (pricingGrid && 'IntersectionObserver' in window) {
            var planObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                        var card = entry.target;
                        var idx = Array.prototype.indexOf.call(planCards, card);
                        if (idx !== -1 && planPills[idx]) {
                            planPills.forEach(function (p) { p.classList.remove('active'); });
                            planPills[idx].classList.add('active');
                        }
                    }
                });
            }, { root: pricingGrid, threshold: 0.5 });
            planCards.forEach(function (c) { planObserver.observe(c); });
        }

        // 3. Barre flottante Sticky CTA sur mobile
        var stickyCta = document.getElementById('mobile-sticky-cta');
        if (stickyCta) {
            window.addEventListener('scroll', function () {
                if (window.scrollY > 420) {
                    stickyCta.classList.add('visible');
                } else {
                    stickyCta.classList.remove('visible');
                }
            }, { passive: true });
        }
    });
</script>
<script src="{{ asset('js/ascii-hero.js') }}?v={{ file_exists(public_path('js/ascii-hero.js')) ? filemtime(public_path('js/ascii-hero.js')) : '1.1' }}" defer></script>
@endsection
