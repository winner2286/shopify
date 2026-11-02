@extends('layouts.app')

@section('title', 'Générateur d\'Affiches Promo & Statuts WhatsApp : '.config('platform.name'))

@section('content')
<style>
.ads-header-btn-row {
    display: flex;
    gap: .5rem;
    align-items: center;
    flex-wrap: nowrap;
}
@media (max-width: 640px) {
    .ads-header-wrap {
        flex-direction: column;
        align-items: stretch !important;
        gap: .85rem !important;
    }
    .ads-header-btn-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: .5rem !important;
        width: 100% !important;
    }
    .ads-header-btn-row .btn {
        width: 100%;
        padding: .6rem .35rem !important;
        font-size: .8rem !important;
        text-align: center;
        justify-content: center;
        white-space: nowrap;
    }
}
.ads-tabs-row {
    display: flex;
    gap: .5rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2rem;
    padding-bottom: .5rem;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.ads-tabs-row::-webkit-scrollbar {
    display: none;
}
.ads-tabs-row .btn {
    flex-shrink: 0;
}
</style>

<div class="container" style="max-width:1160px;padding:2rem 1rem">

    <!-- En-tête & Onglets de navigation Publicité -->
    <div class="ads-header-wrap" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span style="font-size:1.8rem">🎨</span>
                <h1 style="font-size:1.8rem;margin:0">Studio Affiches Promo & Statuts WhatsApp</h1>
                <span class="badge badge-ok" style="font-size:.78rem">1 Clic</span>
            </div>
            <p style="margin:0;color:var(--text-soft)">
                Générez des visuels promotionnels professionnels pour vos statuts WhatsApp, stories et catalogues : pour un <strong>produit spécifique</strong> ou pour <strong>votre boutique en général</strong> !
            </p>
        </div>

        <div class="ads-header-btn-row">
            <a href="{{ route('ads.create') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="sparkles" :size="15" />
                <span>+ Vidéo IA</span>
            </a>
            <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm">
                <span>Mes Vidéos</span>
            </a>
        </div>
    </div>

    <!-- Sélecteur d'onglets Publicité -->
    <div class="ads-tabs-row">
        <a href="{{ route('ads.flyers') }}" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>🎨 Affiches & Statuts WhatsApp</span>
        </a>
        <a href="{{ route('ads.index') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>🎬 Bibliothèque Vidéos IA</span>
        </a>
        <a href="{{ route('ads.create') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>✨ Nouvelle Vidéo IA</span>
        </a>
    </div>

    @php
        $productsJson = $products->map(function ($p) {
            $img = '';
            if ($p->primaryImage) {
                $img = $p->primaryImage->image_data ?: $p->primaryImage->url;
            }
            return [
                'id' => $p->id,
                'title' => $p->title,
                'price' => $p->price,
                'compare_at_price' => $p->compare_at_price,
                'image_url' => $img,
                'category' => $p->categories->first()?->name ?? 'Article tendance',
            ];
        })->values();

        $shopLogo = $shop->logo_path
            ? asset('storage/' . $shop->logo_path)
            : '';
        $shopUrl = $shop->public_url;
        $defaultShopDescription = $shop->description ?: ($shop->business_category ? "Votre boutique spécialisée en {$shop->business_category} à Lomé." : "Votre référence shopping en ligne au Togo.");
    @endphp

    <!-- Grille Studio : Formulaire à Gauche / Preview Live à Droite -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:2rem;align-items:start">

        <!-- COLONNE CONTRÔLES -->
        <div>
            <!-- 1. TYPE D'AFFICHE (PRODUIT OU BOUTIQUE ENTIÈRE) -->
            <div class="card" style="margin-bottom:1.5rem;border-left:4px solid var(--brand-1)">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem">
                    <span>🎯</span> Que souhaitez-vous promouvoir ?
                </h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <!-- Option Affiche Produit -->
                    <label id="modeOptionProduct" style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1rem;border:2px solid var(--brand-1);background:var(--bg-soft);border-radius:12px;cursor:pointer;transition:all .2s">
                        <input type="radio" name="flyer_mode" value="product" checked onchange="setMode('product')" style="display:none">
                        <span style="font-size:1.8rem">🛍️</span>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Un Produit Spécifique</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">Article, prix & réduction flash</span>
                    </label>

                    <!-- Option Affiche Boutique Complète -->
                    <label id="modeOptionShop" style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1rem;border:2px solid var(--border);background:var(--surface);border-radius:12px;cursor:pointer;transition:all .2s">
                        <input type="radio" name="flyer_mode" value="shop" onchange="setMode('shop')" style="display:none">
                        <span style="font-size:1.8rem">🏪</span>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">La Boutique Complète</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">Logo, slogan, site web & WhatsApp</span>
                    </label>
                </div>
            </div>

            <!-- 2. Format de diffusion -->
            <div class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem">
                    <span>📐</span> Format de l'affiche
                </h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <label id="formatOptionStory" style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1rem;border:2px solid var(--brand-1);background:var(--bg-soft);border-radius:12px;cursor:pointer;transition:all .2s">
                        <input type="radio" name="flyer_format" value="story" checked onchange="setFormat('story')" style="display:none">
                        <span style="font-size:1.8rem">📱</span>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Story & Statut</div>
                        <span style="font-size:.75rem;color:var(--text-soft)">WhatsApp / Insta (9:16)</span>
                    </label>

                    <label id="formatOptionSquare" style="display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:1rem;border:2px solid var(--border);background:var(--surface);border-radius:12px;cursor:pointer;transition:all .2s">
                        <input type="radio" name="flyer_format" value="square" onchange="setFormat('square')" style="display:none">
                        <span style="font-size:1.8rem">⏹️</span>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Post Carré</div>
                        <span style="font-size:.75rem;color:var(--text-soft)">Catalogue / Feed (1:1)</span>
                    </label>
                </div>
            </div>

            <!-- 3. CONFIGURATION MODE PRODUIT -->
            <div id="cardProductConfig" class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem">
                    <span>🛍️</span> Paramètres du produit
                </h3>

                @if ($products->isEmpty())
                    <p style="font-size:.9rem;color:var(--text-soft)">Aucun produit actif disponible. Vous pouvez basculer sur l'affiche Boutique ci-dessus !</p>
                @else
                    <div style="margin-bottom:1rem">
                        <label for="selectProduct" class="label" style="font-size:.85rem">Sélectionnez un produit de votre catalogue</label>
                        <select id="selectProduct" class="select" onchange="onProductSelect(this.value)" style="font-size:.92rem">
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" {{ $selectedProductId === $p->id ? 'selected' : '' }}>
                                    {{ $p->title }} — {{ number_format($p->price, 0, ',', ' ') }} FCFA
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1rem">
                    <div>
                        <label for="inputPrice" class="label" style="font-size:.85rem">Prix de vente (FCFA)</label>
                        <input type="number" id="inputPrice" class="input" placeholder="Ex. 15000" oninput="updateFlyer()">
                    </div>
                    <div>
                        <label for="inputOldPrice" class="label" style="font-size:.85rem">Ancien prix barré (FCFA)</label>
                        <input type="number" id="inputOldPrice" class="input" placeholder="Ex. 20000 (Optionnel)" oninput="updateFlyer()">
                    </div>
                </div>

                <div style="margin-bottom:.5rem">
                    <label for="inputProductTitle" class="label" style="font-size:.85rem">Titre affiché sur l'affiche</label>
                    <input type="text" id="inputProductTitle" class="input" placeholder="Nom du produit" oninput="updateFlyer()">
                </div>
            </div>

            <!-- 4. CONFIGURATION MODE BOUTIQUE (QUAND ON CHOISIT TOUTE LA BOUTIQUE) -->
            <div id="cardShopConfig" class="card" style="margin-bottom:1.5rem;display:none">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem">
                    <span>🏪</span> Présentation de la Boutique
                </h3>

                <div style="margin-bottom:1rem">
                    <label for="inputShopHeadline" class="label" style="font-size:.85rem">Titre d'accroche principal</label>
                    <input type="text" id="inputShopHeadline" class="input" value="BIENVENUE CHEZ {{ strtoupper($shop->name) }}" placeholder="Ex. BIENVENUE DANS NOTRE BOUTIQUE" oninput="updateFlyer()">
                </div>

                <div style="margin-bottom:1rem">
                    <label for="inputShopTagline" class="label" style="font-size:.85rem">Slogan ou description de votre boutique</label>
                    <textarea id="inputShopTagline" class="input" rows="2" style="resize:vertical" oninput="updateFlyer()">{{ $defaultShopDescription }}</textarea>
                </div>

                <!-- 3 Points Forts / Avantages Boutique -->
                <div style="margin-bottom:1rem">
                    <label class="label" style="font-size:.85rem">3 Points forts mis en avant</label>
                    <div style="display:flex;flex-direction:column;gap:.5rem">
                        <input type="text" id="inputShopFeature1" class="input" value="🚚 Livraison rapide partout au Togo" oninput="updateFlyer()">
                        <input type="text" id="inputShopFeature2" class="input" value="💵 Paiement sécurisé & à la livraison" oninput="updateFlyer()">
                        <input type="text" id="inputShopFeature3" class="input" value="⭐ Produits de qualité 100% garantis" oninput="updateFlyer()">
                    </div>
                </div>

                <!-- Image personnalisée ou Logo -->
                <div style="margin-bottom:.5rem">
                    <label class="label" style="font-size:.85rem">Visuel central de l'affiche</label>
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <label class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
                            <x-icon name="upload" size="14" />
                            <span>Importer une photo (vitrine, showroom)</span>
                            <input type="file" id="customShopImageInput" accept="image/*" onchange="handleCustomShopImage(event)" style="display:none">
                        </label>
                        <button type="button" onclick="resetToShopLogo()" class="btn btn-ghost btn-sm" style="font-size:.8rem;color:var(--text-soft)">
                            Réinitialiser au logo
                        </button>
                    </div>
                    <small style="color:var(--text-soft);font-size:.78rem;display:block;margin-top:.4rem">Par défaut, le logo de votre boutique est élégamment mis en valeur au centre.</small>
                </div>
            </div>

            <!-- 5. Thème & Style Graphique -->
            <div class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem">
                    <span>✨</span> Thème Visuel & Ambiance
                </h3>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:.6rem;margin-bottom:1.2rem">
                    <!-- Néon Dark -->
                    <button type="button" class="theme-btn active" onclick="setTheme('neon')" data-theme="neon" style="padding:.7rem .5rem;border-radius:10px;border:2px solid #06b6d4;background:#090d16;color:#fff;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #06b6d4, #ec4899);margin:0 auto .3rem"></div>
                        Néon Cyber
                    </button>

                    <!-- Luxe Or -->
                    <button type="button" class="theme-btn" onclick="setTheme('gold')" data-theme="gold" style="padding:.7rem .5rem;border-radius:10px;border:2px solid var(--border);background:#121212;color:#e2e8f0;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #f59e0b, #d97706);margin:0 auto .3rem"></div>
                        Luxe Or & Noir
                    </button>

                    <!-- Sunset Énergie -->
                    <button type="button" class="theme-btn" onclick="setTheme('sunset')" data-theme="sunset" style="padding:.7rem .5rem;border-radius:10px;border:2px solid var(--border);background:#1c1015;color:#e2e8f0;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #ef4444, #f97316);margin:0 auto .3rem"></div>
                        Sunset Flash
                    </button>

                    <!-- Émeraude -->
                    <button type="button" class="theme-btn" onclick="setTheme('emerald')" data-theme="emerald" style="padding:.7rem .5rem;border-radius:10px;border:2px solid var(--border);background:#061811;color:#e2e8f0;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #10b981, #059669);margin:0 auto .3rem"></div>
                        Émeraude
                    </button>

                    <!-- Royal Indigo -->
                    <button type="button" class="theme-btn" onclick="setTheme('indigo')" data-theme="indigo" style="padding:.7rem .5rem;border-radius:10px;border:2px solid var(--border);background:#0e1026;color:#e2e8f0;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #6366f1, #8b5cf6);margin:0 auto .3rem"></div>
                        Royal Indigo
                    </button>

                    <!-- Blanc Pro -->
                    <button type="button" class="theme-btn" onclick="setTheme('white')" data-theme="white" style="padding:.7rem .5rem;border-radius:10px;border:2px solid var(--border);background:#ffffff;color:#0f172a;font-size:.8rem;font-weight:700;cursor:pointer;text-align:center">
                        <div style="width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg, #e2e8f0, #94a3b8);border:1px solid #cbd5e1;margin:0 auto .3rem"></div>
                        Minimalist Blanc
                    </button>
                </div>

                <!-- 6. Badge Promo / Sticker -->
                <div style="margin-bottom:1rem">
                    <label class="label" style="font-size:.85rem">Badge accroche promotionnel</label>
                    <div id="badgePresetsProduct" style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.6rem">
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🔥 PROMO FLASH')">🔥 PROMO FLASH</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('⚡ NOUVEL ARRIVAGE')">⚡ NOUVEL ARRIVAGE</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('💥 OFFRE SPÉCIALE')">💥 OFFRE SPÉCIALE</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('⭐ MEILLEURE VENTE')">⭐ MEILLEURE VENTE</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🎁 LIQUIDATION')">🎁 LIQUIDATION</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🚚 LIVRAISON GRATUITE')">🚚 LIVRAISON GRATUITE</button>
                    </div>
                    <div id="badgePresetsShop" style="display:none;flex-wrap:wrap;gap:.4rem;margin-bottom:.6rem">
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🏪 BOUTIQUE EN LIGNE')">🏪 BOUTIQUE EN LIGNE</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('✨ NOUVELLE COLLECTION')">✨ NOUVELLE COLLECTION</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🚀 COMMANDEZ EN LIGNE')">🚀 COMMANDEZ EN LIGNE</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('📍 DISPONIBLE À LOMÉ')">📍 DISPONIBLE À LOMÉ</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('🚚 EXPÉDITION PARTOUT')">🚚 EXPÉDITION PARTOUT</button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('⭐ SERVICE DE CONFIANCE')">⭐ SERVICE DE CONFIANCE</button>
                    </div>
                    <input type="text" id="inputBadge" class="input" placeholder="Ou tapez votre propre badge..." oninput="updateFlyer()">
                </div>

                <!-- 7. Accroche / Sous-titre -->
                <div style="margin-bottom:1rem">
                    <label for="inputHook" class="label" style="font-size:.85rem">Phrase d'urgence ou sous-titre</label>
                    <input type="text" id="inputHook" class="input" value="Stock très limité — Faites vite ! ⏳" placeholder="Ex. Livraison rapide partout à Lomé" oninput="updateFlyer()">
                </div>

                <!-- 8. Appel à l'action WhatsApp -->
                <div>
                    <label for="inputCta" class="label" style="font-size:.85rem">Bouton de contact / WhatsApp</label>
                    <input type="text" id="inputCta" class="input" value="Commandez vite au {{ $shop->whatsapp ?: $shop->phone }}" placeholder="Ex. WhatsApp : 90123456" oninput="updateFlyer()">
                </div>
            </div>
        </div>

        <!-- COLONNE APERÇU LIVE & TÉLÉCHARGEMENT -->
        <div style="position:sticky;top:5rem">
            <div class="card" style="padding:1.25rem;text-align:center">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h3 style="margin:0;font-size:1.05rem;font-weight:700">Aperçu Réel Haute Définition</h3>
                    <span id="canvasDimLabel" class="badge" style="background:var(--bg-soft);color:var(--text-soft);font-size:.78rem">1080 x 1920 px (9:16)</span>
                </div>

                <!-- Conteneur Mockup Réaliste -->
                <div id="flyerPreviewContainer" style="background:#000;border-radius:18px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;max-width:360px;aspect-ratio:9/16;position:relative;border:1px solid rgba(255,255,255,0.1)">
                    <canvas id="flyerCanvas" style="width:100%;height:100%;object-fit:contain;display:block"></canvas>
                </div>

                <!-- Actions de Téléchargement & Partage -->
                <div style="display:flex;flex-direction:column;gap:.65rem">
                    <button type="button" onclick="downloadFlyerPNG()" class="btn btn-primary btn-block" style="background:linear-gradient(135deg, var(--brand-1), #ec4899);border:none;font-size:1rem;font-weight:700;padding:.85rem 1rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 8px 24px rgba(109,61,242,0.35);cursor:pointer">
                        <x-icon name="download" size="18" />
                        <span>Télécharger l'Affiche en PNG (HD)</span>
                    </button>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
                        <button type="button" onclick="copyWhatsAppPromoText()" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;justify-content:center;gap:.35rem">
                            <x-icon name="copy" size="14" />
                            <span>Copier texte WhatsApp</span>
                        </button>
                        <button type="button" onclick="shareOnWhatsApp()" class="btn btn-ghost btn-sm" style="color:#25D366;border-color:rgba(37,211,102,0.3);display:inline-flex;align-items:center;justify-content:center;gap:.35rem">
                            <x-icon name="whatsapp" size="14" />
                            <span>Ouvrir WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Données injectées
    const productsData = @json($productsJson);
    const shopName = @json($shop->name);
    const shopWhatsapp = @json($shop->whatsapp ?: $shop->phone ?: '');
    const shopLogoUrl = @json($shopLogo);
    const shopUrl = @json($shopUrl);

    let currentMode = 'product'; // 'product' ou 'shop'
    let currentFormat = 'story'; // 'story' (9:16) ou 'square' (1:1)
    let currentTheme = 'neon';
    let currentProduct = null;
    let loadedProductImg = null;
    let loadedLogoImg = null;
    let customShopImg = null;

    // Palettes de thèmes
    const themes = {
        neon: {
            bgDark: '#080c14',
            bgGlow: '#06b6d4',
            bgGlow2: '#ec4899',
            cardBg: 'rgba(15, 23, 42, 0.75)',
            cardBorder: 'rgba(6, 182, 212, 0.4)',
            badgeBg: '#ec4899',
            badgeText: '#ffffff',
            textColor: '#ffffff',
            textSub: '#94a3b8',
            priceColor: '#38bdf8',
            oldPriceColor: '#64748b',
            ctaBg: '#25D366',
            ctaText: '#ffffff',
        },
        gold: {
            bgDark: '#0d0d0f',
            bgGlow: '#f59e0b',
            bgGlow2: '#d97706',
            cardBg: 'rgba(20, 20, 24, 0.85)',
            cardBorder: 'rgba(245, 158, 11, 0.45)',
            badgeBg: '#d97706',
            badgeText: '#ffffff',
            textColor: '#fef3c7',
            textSub: '#d1d5db',
            priceColor: '#fbbf24',
            oldPriceColor: '#78716c',
            ctaBg: '#d97706',
            ctaText: '#ffffff',
        },
        sunset: {
            bgDark: '#14080a',
            bgGlow: '#ef4444',
            bgGlow2: '#f97316',
            cardBg: 'rgba(30, 10, 15, 0.8)',
            cardBorder: 'rgba(239, 68, 68, 0.4)',
            badgeBg: '#ef4444',
            badgeText: '#ffffff',
            textColor: '#ffffff',
            textSub: '#fca5a5',
            priceColor: '#fbbf24',
            oldPriceColor: '#7f1d1d',
            ctaBg: '#25D366',
            ctaText: '#ffffff',
        },
        emerald: {
            bgDark: '#04130d',
            bgGlow: '#10b981',
            bgGlow2: '#059669',
            cardBg: 'rgba(6, 28, 20, 0.8)',
            cardBorder: 'rgba(16, 185, 129, 0.4)',
            badgeBg: '#10b981',
            badgeText: '#ffffff',
            textColor: '#ffffff',
            textSub: '#a7f3d0',
            priceColor: '#34d399',
            oldPriceColor: '#064e3b',
            ctaBg: '#25D366',
            ctaText: '#ffffff',
        },
        indigo: {
            bgDark: '#0b0c1e',
            bgGlow: '#6366f1',
            bgGlow2: '#a855f7',
            cardBg: 'rgba(18, 20, 48, 0.8)',
            cardBorder: 'rgba(99, 102, 241, 0.4)',
            badgeBg: '#a855f7',
            badgeText: '#ffffff',
            textColor: '#ffffff',
            textSub: '#c7d2fe',
            priceColor: '#818cf8',
            oldPriceColor: '#4338ca',
            ctaBg: '#25D366',
            ctaText: '#ffffff',
        },
        white: {
            bgDark: '#f8fafc',
            bgGlow: '#e2e8f0',
            bgGlow2: '#cbd5e1',
            cardBg: '#ffffff',
            cardBorder: '#e2e8f0',
            badgeBg: '#ef4444',
            badgeText: '#ffffff',
            textColor: '#0f172a',
            textSub: '#64748b',
            priceColor: '#dc2626',
            oldPriceColor: '#94a3b8',
            ctaBg: '#25D366',
            ctaText: '#ffffff',
        }
    };

    // Initialisation
    document.addEventListener('DOMContentLoaded', () => {
        if (productsData.length > 0) {
            const initialId = @json($selectedProductId) || productsData[0].id;
            onProductSelect(initialId);
        } else {
            // Si aucun produit, basculer par défaut sur l'affiche Boutique
            setMode('shop');
        }

        // Pré-chargement du logo officiel
        if (shopLogoUrl) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => { loadedLogoImg = img; updateFlyer(); };
            img.src = shopLogoUrl;
        }
    });

    // Basculement Mode Produit / Mode Boutique
    function setMode(mode) {
        currentMode = mode;
        const optProd = document.getElementById('modeOptionProduct');
        const optShop = document.getElementById('modeOptionShop');
        const cardProd = document.getElementById('cardProductConfig');
        const cardShop = document.getElementById('cardShopConfig');
        const badgePresetsProd = document.getElementById('badgePresetsProduct');
        const badgePresetsShop = document.getElementById('badgePresetsShop');

        if (mode === 'product') {
            optProd.style.borderColor = 'var(--brand-1)';
            optProd.style.background = 'var(--bg-soft)';
            optShop.style.borderColor = 'var(--border)';
            optShop.style.background = 'var(--surface)';

            cardProd.style.display = 'block';
            cardShop.style.display = 'none';
            badgePresetsProd.style.display = 'flex';
            badgePresetsShop.style.display = 'none';

            document.getElementById('inputBadge').value = '🔥 PROMO FLASH';
            document.getElementById('inputHook').value = 'Stock très limité — Faites vite ! ⏳';
            document.getElementById('inputCta').value = `Commandez vite au ${shopWhatsapp}`;
        } else {
            optShop.style.borderColor = 'var(--brand-1)';
            optShop.style.background = 'var(--bg-soft)';
            optProd.style.borderColor = 'var(--border)';
            optProd.style.background = 'var(--surface)';

            cardShop.style.display = 'block';
            cardProd.style.display = 'none';
            badgePresetsShop.style.display = 'flex';
            badgePresetsProd.style.display = 'none';

            document.getElementById('inputBadge').value = '🏪 BOUTIQUE EN LIGNE';
            document.getElementById('inputHook').value = 'Découvrez tous nos articles et nouveautés en 1 clic !';
            document.getElementById('inputCta').value = `Visiter la boutique • WhatsApp : ${shopWhatsapp}`;
        }
        updateFlyer();
    }

    function setFormat(fmt) {
        currentFormat = fmt;
        const container = document.getElementById('flyerPreviewContainer');
        const dimLabel = document.getElementById('canvasDimLabel');

        const optStory = document.getElementById('formatOptionStory');
        const optSquare = document.getElementById('formatOptionSquare');

        if (fmt === 'story') {
            container.style.aspectRatio = '9/16';
            dimLabel.innerText = '1080 x 1920 px (Story / Statut)';
            optStory.style.borderColor = 'var(--brand-1)';
            optStory.style.background = 'var(--bg-soft)';
            optSquare.style.borderColor = 'var(--border)';
            optSquare.style.background = 'var(--surface)';
        } else {
            container.style.aspectRatio = '1/1';
            dimLabel.innerText = '1080 x 1080 px (Post Carré)';
            optSquare.style.borderColor = 'var(--brand-1)';
            optSquare.style.background = 'var(--bg-soft)';
            optStory.style.borderColor = 'var(--border)';
            optStory.style.background = 'var(--surface)';
        }
        updateFlyer();
    }

    function setTheme(thm) {
        currentTheme = thm;
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.theme === thm) {
                btn.classList.add('active');
                btn.style.borderColor = 'var(--brand-1)';
            } else {
                btn.style.borderColor = 'var(--border)';
            }
        });
        updateFlyer();
    }

    function setBadge(text) {
        document.getElementById('inputBadge').value = text;
        updateFlyer();
    }

    function onProductSelect(productId) {
        const p = productsData.find(item => item.id == productId);
        if (!p) return;

        currentProduct = p;
        document.getElementById('inputProductTitle').value = p.title;
        document.getElementById('inputPrice').value = p.price;
        document.getElementById('inputOldPrice').value = p.compare_at_price || '';
        document.getElementById('inputBadge').value = document.getElementById('inputBadge').value || '🔥 PROMO FLASH';

        if (p.image_url) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                loadedProductImg = img;
                updateFlyer();
            };
            img.onerror = () => {
                loadedProductImg = null;
                updateFlyer();
            };
            img.src = p.image_url;
        } else {
            loadedProductImg = null;
            updateFlyer();
        }
    }

    // Gestion de l'import d'une image personnalisée pour l'affiche boutique
    function handleCustomShopImage(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(evt) {
            const img = new Image();
            img.onload = function() {
                customShopImg = img;
                updateFlyer();
            };
            img.src = evt.target.result;
        };
        reader.readAsDataURL(file);
    }

    function resetToShopLogo() {
        customShopImg = null;
        document.getElementById('customShopImageInput').value = '';
        updateFlyer();
    }

    // Moteur de rendu graphique Canvas
    function updateFlyer() {
        const canvas = document.getElementById('flyerCanvas');
        if (!canvas) return;

        const isStory = currentFormat === 'story';
        const W = 1080;
        const H = isStory ? 1920 : 1080;

        canvas.width = W;
        canvas.height = H;

        const ctx = canvas.getContext('2d');
        const theme = themes[currentTheme] || themes.neon;

        // 1. Fond et Halos lumineux
        ctx.fillStyle = theme.bgDark;
        ctx.fillRect(0, 0, W, H);

        const grad1 = ctx.createRadialGradient(W * 0.2, H * 0.2, 50, W * 0.2, H * 0.2, 650);
        grad1.addColorStop(0, hexToRgba(theme.bgGlow, 0.45));
        grad1.addColorStop(1, 'transparent');
        ctx.fillStyle = grad1;
        ctx.fillRect(0, 0, W, H);

        const grad2 = ctx.createRadialGradient(W * 0.8, H * 0.8, 80, W * 0.8, H * 0.8, 750);
        grad2.addColorStop(0, hexToRgba(theme.bgGlow2, 0.4));
        grad2.addColorStop(1, 'transparent');
        ctx.fillStyle = grad2;
        ctx.fillRect(0, 0, W, H);

        // Distinction selon le MODE :
        if (currentMode === 'product') {
            renderProductFlyer(ctx, W, H, isStory, theme);
        } else {
            renderShopFlyer(ctx, W, H, isStory, theme);
        }
    }

    // RENDU MODE 1 : AFFICHE PRODUIT SPÉCIFIQUE
    function renderProductFlyer(ctx, W, H, isStory, theme) {
        const title = document.getElementById('inputProductTitle').value || 'Article d\'exception';
        const price = parseInt(document.getElementById('inputPrice').value) || 0;
        const oldPrice = parseInt(document.getElementById('inputOldPrice').value) || 0;
        const badgeText = document.getElementById('inputBadge').value || '🔥 PROMO FLASH';
        const hookText = document.getElementById('inputHook').value || 'Stock très limité — Faites vite ! ⏳';
        const ctaText = document.getElementById('inputCta').value || 'Commandez vite sur WhatsApp';

        // En-tête : Boutique & Logo
        const topY = isStory ? 90 : 60;
        ctx.save();
        ctx.fillStyle = theme.textColor;
        ctx.font = 'bold 38px "Outfit", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(shopName.toUpperCase(), W / 2, topY + 45);

        ctx.font = '500 24px "Plus Jakarta Sans", sans-serif';
        ctx.fillStyle = theme.textSub;
        ctx.fillText('BOUTIQUE OFFICIELLE CERTIFIÉE', W / 2, topY + 80);
        ctx.restore();

        // Cadre Photo Produit
        let imgX, imgY, imgSize;
        if (isStory) {
            imgSize = 780;
            imgX = (W - imgSize) / 2;
            imgY = 240;
        } else {
            imgSize = 520;
            imgX = (W - imgSize) / 2;
            imgY = 170;
        }

        ctx.save();
        ctx.shadowColor = hexToRgba(theme.bgGlow, 0.35);
        ctx.shadowBlur = 40;
        ctx.shadowOffsetY = 15;
        drawRoundedRect(ctx, imgX, imgY, imgSize, imgSize, 36);
        ctx.fillStyle = theme.cardBg;
        ctx.fill();
        ctx.restore();

        ctx.save();
        drawRoundedRect(ctx, imgX, imgY, imgSize, imgSize, 36);
        ctx.lineWidth = 3;
        ctx.strokeStyle = theme.cardBorder;
        ctx.stroke();
        ctx.clip();

        if (loadedProductImg) {
            drawImageCover(ctx, loadedProductImg, imgX, imgY, imgSize, imgSize);
        } else {
            ctx.fillStyle = '#1e293b';
            ctx.fillRect(imgX, imgY, imgSize, imgSize);
            ctx.fillStyle = '#94a3b8';
            ctx.font = 'bold 64px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('🛍️', imgX + imgSize / 2, imgY + imgSize / 2);
        }
        ctx.restore();

        // Badge Promo Flottant sur l'image
        if (badgeText) {
            ctx.save();
            ctx.font = '800 28px "Plus Jakarta Sans", sans-serif';
            const badgeW = ctx.measureText(badgeText).width + 50;
            const badgeH = 58;
            const badgeX = imgX + 30;
            const badgeY = imgY + 30;

            ctx.shadowColor = 'rgba(0,0,0,0.4)';
            ctx.shadowBlur = 15;
            drawRoundedRect(ctx, badgeX, badgeY, badgeW, badgeH, 29);
            ctx.fillStyle = theme.badgeBg;
            ctx.fill();

            ctx.fillStyle = theme.badgeText;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, badgeX + badgeW / 2, badgeY + badgeH / 2);
            ctx.restore();
        }

        // Pourcentage de réduction
        if (oldPrice > price && price > 0) {
            const discountPercent = Math.round(((oldPrice - price) / oldPrice) * 100);
            const discText = `-${discountPercent}%`;

            ctx.save();
            ctx.font = '900 32px "Outfit", sans-serif';
            const discW = 120;
            const discH = 58;
            const discX = imgX + imgSize - discW - 30;
            const discY = imgY + 30;

            drawRoundedRect(ctx, discX, discY, discW, discH, 29);
            ctx.fillStyle = '#ef4444';
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(discText, discX + discW / 2, discY + discH / 2);
            ctx.restore();
        }

        // Bloc Titre & Prix
        let infoY = isStory ? imgY + imgSize + 60 : imgY + imgSize + 35;

        ctx.save();
        ctx.fillStyle = theme.textColor;
        ctx.font = '800 48px "Outfit", sans-serif';
        ctx.textAlign = 'center';
        wrapText(ctx, title, W / 2, infoY, W - 140, 56);
        ctx.restore();

        const priceY = isStory ? infoY + 140 : infoY + 105;
        const priceFormatted = formatCFA(price);

        ctx.save();
        ctx.textAlign = 'center';

        if (oldPrice > price) {
            const oldFormatted = formatCFA(oldPrice);
            ctx.font = '600 36px "Plus Jakarta Sans", sans-serif';
            ctx.fillStyle = theme.oldPriceColor;
            const oldWidth = ctx.measureText(oldFormatted).width;

            ctx.font = '900 68px "Outfit", sans-serif';
            ctx.fillStyle = theme.priceColor;
            const newWidth = ctx.measureText(priceFormatted).width;

            const totalW = oldWidth + newWidth + 40;
            const startX = (W - totalW) / 2;

            ctx.font = '600 36px "Plus Jakarta Sans", sans-serif';
            ctx.fillStyle = theme.oldPriceColor;
            ctx.textAlign = 'left';
            const oldX = startX;
            ctx.fillText(oldFormatted, oldX, priceY);

            ctx.lineWidth = 4;
            ctx.strokeStyle = '#ef4444';
            ctx.beginPath();
            ctx.moveTo(oldX - 6, priceY - 12);
            ctx.lineTo(oldX + oldWidth + 6, priceY - 12);
            ctx.stroke();

            ctx.font = '900 68px "Outfit", sans-serif';
            ctx.fillStyle = theme.priceColor;
            ctx.fillText(priceFormatted, oldX + oldWidth + 40, priceY + 8);
        } else {
            ctx.font = '900 74px "Outfit", sans-serif';
            ctx.fillStyle = theme.priceColor;
            ctx.fillText(priceFormatted, W / 2, priceY);
        }
        ctx.restore();

        // Phrase d'accroche
        const hookY = isStory ? priceY + 85 : priceY + 65;
        if (hookText) {
            ctx.save();
            ctx.fillStyle = theme.textSub;
            ctx.font = '600 30px "Plus Jakarta Sans", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(hookText, W / 2, hookY);
            ctx.restore();
        }

        // Appel à l'action WhatsApp
        const ctaH = isStory ? 130 : 100;
        const ctaW = W - 140;
        const ctaX = 70;
        const ctaY = isStory ? H - 210 : H - 140;

        ctx.save();
        ctx.shadowColor = 'rgba(37, 211, 102, 0.45)';
        ctx.shadowBlur = 30;
        ctx.shadowOffsetY = 10;
        drawRoundedRect(ctx, ctaX, ctaY, ctaW, ctaH, ctaH / 2);
        ctx.fillStyle = theme.ctaBg;
        ctx.fill();

        ctx.fillStyle = theme.ctaText;
        ctx.font = '800 34px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('💬 ' + ctaText, W / 2, ctaY + ctaH / 2);
        ctx.restore();

        // Signature bas
        const footY = isStory ? H - 45 : H - 20;
        ctx.save();
        ctx.fillStyle = hexToRgba(theme.textSub, 0.6);
        ctx.font = '600 20px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Boutique en ligne propulsée par Zanika • Paiement & Livraison express', W / 2, footY);
        ctx.restore();
    }

    // RENDU MODE 2 : AFFICHE BOUTIQUE & MARQUE COMPLÈTE
    function renderShopFlyer(ctx, W, H, isStory, theme) {
        const headline = document.getElementById('inputShopHeadline').value || `BIENVENUE CHEZ ${shopName.toUpperCase()}`;
        const tagline = document.getElementById('inputShopTagline').value || 'Votre référence shopping en ligne au Togo.';
        const feat1 = document.getElementById('inputShopFeature1').value || '🚚 Livraison rapide partout au Togo';
        const feat2 = document.getElementById('inputShopFeature2').value || '💵 Paiement sécurisé & à la livraison';
        const feat3 = document.getElementById('inputShopFeature3').value || '⭐ Produits de qualité garantis';
        const badgeText = document.getElementById('inputBadge').value || '🏪 BOUTIQUE EN LIGNE';
        const ctaText = document.getElementById('inputCta').value || `Visiter la boutique • WhatsApp : ${shopWhatsapp}`;

        // 1. Badge haut
        const badgeY = isStory ? 100 : 70;
        if (badgeText) {
            ctx.save();
            ctx.font = '800 26px "Plus Jakarta Sans", sans-serif';
            const badgeW = ctx.measureText(badgeText).width + 50;
            const badgeH = 54;
            const badgeX = (W - badgeW) / 2;

            ctx.shadowColor = hexToRgba(theme.badgeBg, 0.4);
            ctx.shadowBlur = 20;
            drawRoundedRect(ctx, badgeX, badgeY, badgeW, badgeH, 27);
            ctx.fillStyle = theme.badgeBg;
            ctx.fill();

            ctx.fillStyle = theme.badgeText;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, W / 2, badgeY + badgeH / 2);
            ctx.restore();
        }

        // 2. Nom de la Boutique Géant
        const titleY = isStory ? badgeY + 140 : badgeY + 110;
        ctx.save();
        ctx.fillStyle = theme.textColor;
        ctx.font = '900 64px "Outfit", sans-serif';
        ctx.textAlign = 'center';
        wrapText(ctx, shopName.toUpperCase(), W / 2, titleY, W - 160, 72);
        ctx.restore();

        // 3. Grand Médaillon Central (Logo officiel ou Photo importée de la boutique)
        let emblemY = isStory ? titleY + 120 : titleY + 90;
        let emblemSize = isStory ? 440 : 320;
        let emblemX = (W - emblemSize) / 2;

        ctx.save();
        ctx.shadowColor = hexToRgba(theme.bgGlow, 0.45);
        ctx.shadowBlur = 40;
        ctx.shadowOffsetY = 15;
        drawRoundedRect(ctx, emblemX, emblemY, emblemSize, emblemSize, 40);
        ctx.fillStyle = theme.cardBg;
        ctx.fill();
        ctx.restore();

        ctx.save();
        drawRoundedRect(ctx, emblemX, emblemY, emblemSize, emblemSize, 40);
        ctx.lineWidth = 4;
        ctx.strokeStyle = theme.cardBorder;
        ctx.stroke();
        ctx.clip();

        // Priorité à l'image importée personnalisée, sinon logo officiel, sinon monogramme
        if (customShopImg) {
            drawImageCover(ctx, customShopImg, emblemX, emblemY, emblemSize, emblemSize);
        } else if (loadedLogoImg) {
            drawImageCover(ctx, loadedLogoImg, emblemX, emblemY, emblemSize, emblemSize);
        } else {
            // Monogramme élégant avec initiale
            ctx.fillStyle = hexToRgba(theme.bgGlow, 0.15);
            ctx.fillRect(emblemX, emblemY, emblemSize, emblemSize);
            ctx.fillStyle = theme.priceColor;
            ctx.font = '900 160px "Outfit", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(shopName.substring(0, 1).toUpperCase(), emblemX + emblemSize / 2, emblemY + emblemSize / 2);
        }
        ctx.restore();

        // 4. Slogan & Présentation
        const sloganY = isStory ? emblemY + emblemSize + 80 : emblemY + emblemSize + 55;
        ctx.save();
        ctx.fillStyle = theme.textColor;
        ctx.font = '600 34px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        wrapText(ctx, tagline, W / 2, sloganY, W - 180, 48);
        ctx.restore();

        // 5. 3 Puces de Réassurance / Avantages Boutique
        const featsStartY = isStory ? sloganY + 140 : sloganY + 85;
        const featsList = [feat1, feat2, feat3].filter(Boolean);
        const featCardW = W - 160;
        const featCardH = isStory ? 64 : 52;
        const featGap = isStory ? 18 : 12;

        featsList.forEach((feat, idx) => {
            const fY = featsStartY + idx * (featCardH + featGap);
            ctx.save();
            drawRoundedRect(ctx, 80, fY, featCardW, featCardH, 16);
            ctx.fillStyle = hexToRgba(theme.cardBg, 0.9);
            ctx.fill();
            ctx.strokeStyle = theme.cardBorder;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            ctx.fillStyle = theme.textColor;
            ctx.font = isStory ? '700 28px "Plus Jakarta Sans", sans-serif' : '700 24px "Plus Jakarta Sans", sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(feat, W / 2, fY + featCardH / 2);
            ctx.restore();
        });

        // 6. Ruban Lien du Site Web
        if (isStory) {
            const linkY = featsStartY + (featsList.length * (featCardH + featGap)) + 25;
            ctx.save();
            ctx.fillStyle = theme.priceColor;
            ctx.font = '700 28px "Outfit", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(`🌐 Visitez notre boutique : ${shopUrl.replace(/^https?:\/\//, '')}`, W / 2, linkY);
            ctx.restore();
        }

        // 7. Bouton Appel à l'action WhatsApp en bas
        const ctaH = isStory ? 130 : 96;
        const ctaW = W - 140;
        const ctaX = 70;
        const ctaY = isStory ? H - 210 : H - 135;

        ctx.save();
        ctx.shadowColor = 'rgba(37, 211, 102, 0.45)';
        ctx.shadowBlur = 30;
        ctx.shadowOffsetY = 10;
        drawRoundedRect(ctx, ctaX, ctaY, ctaW, ctaH, ctaH / 2);
        ctx.fillStyle = theme.ctaBg;
        ctx.fill();

        ctx.fillStyle = theme.ctaText;
        ctx.font = isStory ? '800 32px "Plus Jakarta Sans", sans-serif' : '800 28px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('💬 ' + ctaText, W / 2, ctaY + ctaH / 2);
        ctx.restore();

        // 8. Signature bas
        const footY = isStory ? H - 45 : H - 20;
        ctx.save();
        ctx.fillStyle = hexToRgba(theme.textSub, 0.6);
        ctx.font = '600 20px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Boutique officielle propulsée par Zanika • Paiement sécurisé & Livraison express', W / 2, footY);
        ctx.restore();
    }

    // Fonctions utilitaires Canvas
    function formatCFA(val) {
        return (val || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " FCFA";
    }

    function hexToRgba(hex, alpha) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex.split('').map(c => c + c).join('');
        }
        const r = parseInt(hex.substring(0, 2), 16) || 0;
        const g = parseInt(hex.substring(2, 4), 16) || 0;
        const b = parseInt(hex.substring(4, 6), 16) || 0;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function drawRoundedRect(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
    }

    function drawImageCover(ctx, img, x, y, w, h) {
        const imgRatio = img.width / img.height;
        const targetRatio = w / h;
        let sWidth, sHeight, sx, sy;

        if (imgRatio > targetRatio) {
            sHeight = img.height;
            sWidth = img.height * targetRatio;
            sx = (img.width - sWidth) / 2;
            sy = 0;
        } else {
            sWidth = img.width;
            sHeight = img.width / targetRatio;
            sx = 0;
            sy = (img.height - sHeight) / 2;
        }
        ctx.drawImage(img, sx, sy, sWidth, sHeight, x, y, w, h);
    }

    function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        let currentY = y;
        let linesCount = 0;

        for (let n = 0; n < words.length; n++) {
            const testLine = line + words[n] + ' ';
            const metrics = ctx.measureText(testLine);
            const testWidth = metrics.width;
            if (testWidth > maxWidth && n > 0) {
                ctx.fillText(line, x, currentY);
                line = words[n] + ' ';
                currentY += lineHeight;
                linesCount++;
                if (linesCount >= 2) {
                    line = line.trim() + '...';
                    break;
                }
            } else {
                line = testLine;
            }
        }
        ctx.fillText(line, x, currentY);
    }

    // Téléchargement immédiat de l'affiche en PNG
    function downloadFlyerPNG() {
        const canvas = document.getElementById('flyerCanvas');
        if (!canvas) return;

        let namePrefix = 'Affiche';
        if (currentMode === 'shop') {
            namePrefix = `Boutique_${shopName.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
        } else {
            const title = (document.getElementById('inputProductTitle').value || 'Produit').replace(/[^a-zA-Z0-9_-]/g, '_');
            namePrefix = `Produit_${title}`;
        }

        const filename = `${namePrefix}_${currentFormat}.png`;
        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
    }

    // Copie du texte promotionnel WhatsApp
    function copyWhatsAppPromoText() {
        let text = '';

        if (currentMode === 'shop') {
            const tagline = document.getElementById('inputShopTagline').value || 'Votre référence shopping en ligne au Togo.';
            const badge = document.getElementById('inputBadge').value || '🏪 NOUVELLE BOUTIQUE';

            text = `✨ *${badge} : ${shopName.toUpperCase()} !* ✨\n\n`
                + `👋 ${tagline}\n\n`
                + `🛍️ Visitez notre catalogue complet et commandez directement en ligne :\n`
                + `👉 ${shopUrl}\n\n`
                + `🚚 Livraison rapide partout à Lomé et au Togo\n`
                + `💵 Paiement sécurisé à la livraison\n\n`
                + `📲 Contact & Commandes WhatsApp : *${shopWhatsapp}*`;
        } else {
            const title = document.getElementById('inputProductTitle').value || 'Notre sélection';
            const price = formatCFA(document.getElementById('inputPrice').value);
            const oldPriceVal = document.getElementById('inputOldPrice').value;
            const oldPrice = oldPriceVal ? formatCFA(oldPriceVal) : null;
            const badge = document.getElementById('inputBadge').value || 'PROMO';

            text = `${badge} chez *${shopName}* ! 🎉\n\n`
                + `Découvrez *${title}* !\n`;

            if (oldPrice) {
                text += `💰 Prix promo : *${price}* (au lieu de ~${oldPrice}~)\n\n`;
            } else {
                text += `💰 Prix : *${price}*\n\n`;
            }

            text += `📲 Pour commander ou réserver tout de suite, écrivez-nous au : *${shopWhatsapp}*\n`
                + `🚀 Quantités limitées, livraison rapide !`;
        }

        navigator.clipboard.writeText(text).then(() => {
            alert("Texte d'annonce copié dans le presse-papier ! Vous pouvez le coller avec l'affiche dans votre statut WhatsApp.");
        }).catch(() => {
            alert("Impossible de copier automatiquement.");
        });
    }

    // Ouverture de WhatsApp
    function shareOnWhatsApp() {
        let msg = '';
        if (currentMode === 'shop') {
            msg = encodeURIComponent(`🏪 Découvrez la boutique en ligne *${shopName}* ! Visitez le catalogue ici : ${shopUrl}`);
        } else {
            const title = document.getElementById('inputProductTitle').value || 'Notre sélection';
            const price = formatCFA(document.getElementById('inputPrice').value);
            msg = encodeURIComponent(`🔥 PROMO chez *${shopName}* : *${title}* à seulement *${price}* !`);
        }
        window.open(`https://api.whatsapp.com/send?text=${msg}`, '_blank');
    }
</script>
@endsection
