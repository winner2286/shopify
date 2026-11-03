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
.flyer-action-btn-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
    width: 100%;
}
.flyer-action-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: .35rem !important;
    white-space: normal !important;
    line-height: 1.25 !important;
    padding: .65rem .45rem !important;
    text-align: center !important;
    font-size: .84rem !important;
    min-height: 44px !important;
}
@media (max-width: 440px) {
    .flyer-action-btn-row {
        grid-template-columns: 1fr !important;
    }
}
.studio-choice-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    padding: 1rem .75rem;
    border: 2px solid var(--border);
    background: var(--surface);
    border-radius: 12px;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
}
.studio-choice-card.active,
.studio-choice-card:has(input:checked) {
    border-color: var(--brand-1) !important;
    background: var(--bg-soft) !important;
}
.studio-choice-card .choice-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--bg-soft);
    color: var(--text-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .25rem;
    transition: all .2s;
}
.studio-choice-card.active .choice-icon-wrap,
.studio-choice-card:has(input:checked) .choice-icon-wrap {
    background: rgba(158, 27, 50, 0.12);
    color: var(--brand-1);
}
.studio-section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(158, 27, 50, 0.1);
    color: var(--brand-1);
    flex-shrink: 0;
}
</style>

<div class="container" style="max-width:1160px;padding:2rem 1rem">

    <!-- En-tête & Onglets de navigation Publicité -->
    <div class="ads-header-wrap" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.3rem">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(158, 27, 50, 0.1);color:var(--brand-1);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                    <x-icon name="palette" :size="20" />
                </div>
                <h1 style="font-size:1.65rem;margin:0">Studio Affiches Promo & Statuts WhatsApp</h1>
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
            <x-icon name="palette" :size="15" />
            <span>Affiches & Statuts WhatsApp</span>
        </a>
        <a href="{{ route('ads.index') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <x-icon name="video" :size="15" />
            <span>Bibliothèque Vidéos IA</span>
        </a>
        <a href="{{ route('ads.create') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <x-icon name="sparkles" :size="15" />
            <span>Nouvelle Vidéo IA</span>
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
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.6rem;line-height:1.3">
                    <span class="studio-section-icon">
                        <x-icon name="target" :size="16" />
                    </span>
                    <span>Que souhaitez-vous promouvoir&nbsp;?</span>
                </h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <!-- Option Affiche Produit -->
                    <label id="modeOptionProduct" class="studio-choice-card active">
                        <input type="radio" name="flyer_mode" value="product" checked onchange="setMode('product')" style="display:none">
                        <div class="choice-icon-wrap">
                            <x-icon name="shopping-bag" :size="24" />
                        </div>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Un Produit Spécifique</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">Article, prix & réduction flash</span>
                    </label>

                    <!-- Option Affiche Boutique Complète -->
                    <label id="modeOptionShop" class="studio-choice-card">
                        <input type="radio" name="flyer_mode" value="shop" onchange="setMode('shop')" style="display:none">
                        <div class="choice-icon-wrap">
                            <x-icon name="store" :size="24" />
                        </div>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">La Boutique Complète</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">Logo, slogan, site web & WhatsApp</span>
                    </label>
                </div>
            </div>

            <!-- 2. Format de diffusion -->
            <div class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.6rem;line-height:1.3">
                    <span class="studio-section-icon">
                        <x-icon name="aspect-ratio" :size="16" />
                    </span>
                    <span>Format de l'affiche</span>
                </h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <label id="formatOptionStory" class="studio-choice-card active">
                        <input type="radio" name="flyer_format" value="story" checked onchange="setFormat('story')" style="display:none">
                        <div class="choice-icon-wrap">
                            <x-icon name="smartphone" :size="24" />
                        </div>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Story & Statut</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">WhatsApp / Insta (9:16)</span>
                    </label>

                    <label id="formatOptionSquare" class="studio-choice-card">
                        <input type="radio" name="flyer_format" value="square" onchange="setFormat('square')" style="display:none">
                        <div class="choice-icon-wrap">
                            <x-icon name="square" :size="24" />
                        </div>
                        <div style="font-weight:700;font-size:.92rem;text-align:center">Post Carré</div>
                        <span style="font-size:.75rem;color:var(--text-soft);text-align:center">Catalogue / Feed (1:1)</span>
                    </label>
                </div>
            </div>

            <!-- 3. CONFIGURATION MODE PRODUIT -->
            <div id="cardProductConfig" class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.6rem;line-height:1.3">
                    <span class="studio-section-icon">
                        <x-icon name="shopping-bag" :size="16" />
                    </span>
                    <span>Paramètres du produit</span>
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
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.6rem;line-height:1.3">
                    <span class="studio-section-icon">
                        <x-icon name="store" :size="16" />
                    </span>
                    <span>Présentation de la Boutique</span>
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
                    <div style="display:flex;flex-direction:column;gap:.55rem">
                        <div style="display:flex;align-items:center;position:relative">
                            <span style="position:absolute;left:.75rem;color:var(--brand-1);pointer-events:none;display:flex;align-items:center">
                                <x-icon name="truck" :size="16" />
                            </span>
                            <input type="text" id="inputShopFeature1" class="input" value="Livraison rapide partout au Togo" placeholder="Ex. Livraison rapide partout au Togo" oninput="updateFlyer()" style="padding-left:2.25rem">
                        </div>

                        <div style="display:flex;align-items:center;position:relative">
                            <span style="position:absolute;left:.75rem;color:var(--brand-1);pointer-events:none;display:flex;align-items:center">
                                <x-icon name="credit-card" :size="16" />
                            </span>
                            <input type="text" id="inputShopFeature2" class="input" value="Paiement sécurisé & à la livraison" placeholder="Ex. Paiement sécurisé & à la livraison" oninput="updateFlyer()" style="padding-left:2.25rem">
                        </div>

                        <div style="display:flex;align-items:center;position:relative">
                            <span style="position:absolute;left:.75rem;color:var(--brand-1);pointer-events:none;display:flex;align-items:center">
                                <x-icon name="check-circle" :size="16" />
                            </span>
                            <input type="text" id="inputShopFeature3" class="input" value="Produits de qualité 100% garantis" placeholder="Ex. Produits de qualité 100% garantis" oninput="updateFlyer()" style="padding-left:2.25rem">
                        </div>
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
                <h3 style="margin-top:0;font-size:1.1rem;display:flex;align-items:center;gap:.6rem;line-height:1.3">
                    <span class="studio-section-icon">
                        <x-icon name="sparkles" :size="16" />
                    </span>
                    <span>Thème Visuel & Ambiance</span>
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
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('PROMO FLASH')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="zap" :size="13" />
                            <span>PROMO FLASH</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('NOUVEL ARRIVAGE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="sparkles" :size="13" />
                            <span>NOUVEL ARRIVAGE</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('OFFRE SPÉCIALE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="tag" :size="13" />
                            <span>OFFRE SPÉCIALE</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('MEILLEURE VENTE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="star" :size="13" />
                            <span>MEILLEURE VENTE</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('LIQUIDATION')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="flame" :size="13" />
                            <span>LIQUIDATION</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('LIVRAISON GRATUITE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="truck" :size="13" />
                            <span>LIVRAISON GRATUITE</span>
                        </button>
                    </div>
                    <div id="badgePresetsShop" style="display:none;flex-wrap:wrap;gap:.4rem;margin-bottom:.6rem">
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('BOUTIQUE EN LIGNE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="store" :size="13" />
                            <span>BOUTIQUE EN LIGNE</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('NOUVELLE COLLECTION')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="sparkles" :size="13" />
                            <span>NOUVELLE COLLECTION</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('COMMANDEZ EN LIGNE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="shopping-bag" :size="13" />
                            <span>COMMANDEZ EN LIGNE</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('DISPONIBLE À LOMÉ')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="map-pin" :size="13" />
                            <span>DISPONIBLE À LOMÉ</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('EXPÉDITION PARTOUT')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="truck" :size="13" />
                            <span>EXPÉDITION PARTOUT</span>
                        </button>
                        <button type="button" class="badge-preset btn btn-ghost btn-sm" onclick="setBadge('SERVICE DE CONFIANCE')" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="shield" :size="13" />
                            <span>SERVICE DE CONFIANCE</span>
                        </button>
                    </div>
                    <input type="text" id="inputBadge" class="input" placeholder="Ou tapez votre propre badge..." oninput="updateFlyer()">
                </div>

                <!-- 7. Accroche / Sous-titre -->
                <div style="margin-bottom:1rem">
                    <label for="inputHook" class="label" style="font-size:.85rem">Phrase d'urgence ou sous-titre</label>
                    <input type="text" id="inputHook" class="input" value="Stock très limité — Faites vite !" placeholder="Ex. Livraison rapide partout à Lomé" oninput="updateFlyer()">
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
                    <button type="button" id="btnDownloadFlyer" onclick="downloadFlyerPNG()" class="btn btn-primary btn-block" style="background:linear-gradient(135deg, var(--brand-1), #ec4899);border:none;font-size:1rem;font-weight:700;padding:.85rem 1rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 8px 24px rgba(109,61,242,0.35);cursor:pointer;white-space:normal;line-height:1.3;text-align:center">
                        <x-icon name="download" size="18" />
                        <span id="btnDownloadFlyerText">Télécharger l'Affiche en PNG (HD)</span>
                    </button>

                    <div class="flyer-action-btn-row">
                        <button type="button" onclick="copyWhatsAppPromoText()" class="btn btn-ghost btn-sm flyer-action-btn">
                            <x-icon name="copy" size="14" />
                            <span>Copier texte WhatsApp</span>
                        </button>
                        <button type="button" onclick="shareOnWhatsApp()" class="btn btn-ghost btn-sm flyer-action-btn" style="color:#25D366;border-color:rgba(37,211,102,0.3)">
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

        // Pré-chargement sécurisé du logo officiel
        if (shopLogoUrl) {
            const img = new Image();
            if (!/^data:/i.test(shopLogoUrl)) {
                img.crossOrigin = 'anonymous';
            }
            img.onload = () => { loadedLogoImg = img; updateFlyer(); };
            img.onerror = () => {
                const img2 = new Image();
                img2.onload = () => { loadedLogoImg = img2; updateFlyer(); };
                img2.src = shopLogoUrl;
            };
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
            optProd.classList.add('active');
            optShop.classList.remove('active');
            optProd.style.borderColor = 'var(--brand-1)';
            optProd.style.background = 'var(--bg-soft)';
            optShop.style.borderColor = 'var(--border)';
            optShop.style.background = 'var(--surface)';

            cardProd.style.display = 'block';
            cardShop.style.display = 'none';
            badgePresetsProd.style.display = 'flex';
            badgePresetsShop.style.display = 'none';

            document.getElementById('inputBadge').value = 'PROMO FLASH';
            document.getElementById('inputHook').value = 'Stock très limité — Faites vite !';
            document.getElementById('inputCta').value = `Commandez vite au ${shopWhatsapp}`;
        } else {
            optShop.classList.add('active');
            optProd.classList.remove('active');
            optShop.style.borderColor = 'var(--brand-1)';
            optShop.style.background = 'var(--bg-soft)';
            optProd.style.borderColor = 'var(--border)';
            optProd.style.background = 'var(--surface)';

            cardShop.style.display = 'block';
            cardProd.style.display = 'none';
            badgePresetsShop.style.display = 'flex';
            badgePresetsProd.style.display = 'none';

            document.getElementById('inputBadge').value = 'BOUTIQUE EN LIGNE';
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
            optStory.classList.add('active');
            optSquare.classList.remove('active');
            container.style.aspectRatio = '9/16';
            dimLabel.innerText = '1080 x 1920 px (Story / Statut)';
            optStory.style.borderColor = 'var(--brand-1)';
            optStory.style.background = 'var(--bg-soft)';
            optSquare.style.borderColor = 'var(--border)';
            optSquare.style.background = 'var(--surface)';
        } else {
            optSquare.classList.add('active');
            optStory.classList.remove('active');
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
        document.getElementById('inputBadge').value = document.getElementById('inputBadge').value || 'PROMO FLASH';

        if (p.image_url) {
            const img = new Image();
            if (!/^data:/i.test(p.image_url)) {
                img.crossOrigin = 'anonymous';
            }
            img.onload = () => {
                loadedProductImg = img;
                updateFlyer();
            };
            img.onerror = () => {
                const img2 = new Image();
                img2.onload = () => {
                    loadedProductImg = img2;
                    updateFlyer();
                };
                img2.onerror = () => {
                    loadedProductImg = null;
                    updateFlyer();
                };
                img2.src = p.image_url;
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
        const badgeText = document.getElementById('inputBadge').value || 'PROMO FLASH';
        const hookText = document.getElementById('inputHook').value || 'Stock très limité — Faites vite !';
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

        // Badge Promo Flottant sur l'image avec auto-ajustement de taille
        if (badgeText) {
            ctx.save();
            let badgeFontSize = isStory ? 28 : 22;
            ctx.font = `800 ${badgeFontSize}px "Plus Jakarta Sans", sans-serif`;
            const maxBadgeW = (oldPrice > price && price > 0) ? (imgSize - 180) : (imgSize - 60);

            while (ctx.measureText(badgeText).width + 46 > maxBadgeW && badgeFontSize > 16) {
                badgeFontSize -= 2;
                ctx.font = `800 ${badgeFontSize}px "Plus Jakarta Sans", sans-serif`;
            }

            const badgeW = Math.min(ctx.measureText(badgeText).width + 44, maxBadgeW);
            const badgeH = isStory ? 58 : 46;
            const badgeX = imgX + 24;
            const badgeY = imgY + 24;

            ctx.shadowColor = 'rgba(0,0,0,0.4)';
            ctx.shadowBlur = 15;
            drawRoundedRect(ctx, badgeX, badgeY, badgeW, badgeH, badgeH / 2);
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
            const discH = isStory ? 58 : 46;
            const discX = imgX + imgSize - discW - 24;
            const discY = imgY + 24;

            drawRoundedRect(ctx, discX, discY, discW, discH, discH / 2);
            ctx.fillStyle = '#ef4444';
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(discText, discX + discW / 2, discY + discH / 2);
            ctx.restore();
        }

        // Bloc Titre & Prix dynamique (évite tout chevauchement et débordement)
        let infoY = isStory ? imgY + imgSize + 55 : imgY + imgSize + 30;

        let titleFontSize = isStory ? (title.length > 35 ? 36 : (title.length > 25 ? 42 : 48)) : (title.length > 30 ? 30 : 36);
        let titleLineHeight = titleFontSize + 8;
        ctx.save();
        ctx.fillStyle = theme.textColor;
        ctx.font = `800 ${titleFontSize}px "Outfit", sans-serif`;
        ctx.textAlign = 'center';
        const titleLinesDrawn = wrapText(ctx, title, W / 2, infoY, W - 140, titleLineHeight, 2);
        ctx.restore();

        // Position du prix calculée DYNAMIQUEMENT selon la hauteur réelle du titre
        const priceY = infoY + (titleLinesDrawn * titleLineHeight) + (isStory ? 45 : 30);
        const priceFormatted = formatCFA(price);

        ctx.save();
        ctx.textAlign = 'center';

        if (oldPrice > price) {
            const oldFormatted = formatCFA(oldPrice);
            let oldFontSize = isStory ? 36 : 28;
            let newFontSize = isStory ? 68 : 52;
            ctx.font = `600 ${oldFontSize}px "Plus Jakarta Sans", sans-serif`;
            let oldWidth = ctx.measureText(oldFormatted).width;
            ctx.font = `900 ${newFontSize}px "Outfit", sans-serif`;
            let newWidth = ctx.measureText(priceFormatted).width;

            let totalW = oldWidth + newWidth + 35;
            const maxPriceW = W - 120;
            if (totalW > maxPriceW) {
                const scale = maxPriceW / totalW;
                oldFontSize = Math.max(20, Math.floor(oldFontSize * scale));
                newFontSize = Math.max(32, Math.floor(newFontSize * scale));
                ctx.font = `600 ${oldFontSize}px "Plus Jakarta Sans", sans-serif`;
                oldWidth = ctx.measureText(oldFormatted).width;
                ctx.font = `900 ${newFontSize}px "Outfit", sans-serif`;
                newWidth = ctx.measureText(priceFormatted).width;
                totalW = oldWidth + newWidth + 25;
            }

            const startX = (W - totalW) / 2;
            ctx.fillStyle = theme.oldPriceColor;
            ctx.font = `600 ${oldFontSize}px "Plus Jakarta Sans", sans-serif`;
            ctx.textAlign = 'left';
            ctx.fillText(oldFormatted, startX, priceY);

            ctx.lineWidth = 3.5;
            ctx.strokeStyle = '#ef4444';
            ctx.beginPath();
            ctx.moveTo(startX - 4, priceY - (oldFontSize / 3));
            ctx.lineTo(startX + oldWidth + 4, priceY - (oldFontSize / 3));
            ctx.stroke();

            ctx.font = `900 ${newFontSize}px "Outfit", sans-serif`;
            ctx.fillStyle = theme.priceColor;
            ctx.fillText(priceFormatted, startX + oldWidth + 25, priceY + 6);
        } else {
            let singlePriceFontSize = isStory ? 74 : 58;
            ctx.font = `900 ${singlePriceFontSize}px "Outfit", sans-serif`;
            while (ctx.measureText(priceFormatted).width > W - 140 && singlePriceFontSize > 36) {
                singlePriceFontSize -= 4;
                ctx.font = `900 ${singlePriceFontSize}px "Outfit", sans-serif`;
            }
            ctx.fillStyle = theme.priceColor;
            ctx.fillText(priceFormatted, W / 2, priceY);
        }
        ctx.restore();

        // Phrase d'accroche avec auto-adaptation de largeur
        const hookY = isStory ? priceY + 80 : priceY + 60;
        if (hookText) {
            ctx.save();
            ctx.fillStyle = theme.textSub;
            let hookFontSize = isStory ? 30 : 24;
            ctx.font = `600 ${hookFontSize}px "Plus Jakarta Sans", sans-serif`;
            ctx.textAlign = 'center';

            const maxHookW = W - 160;
            while (ctx.measureText(hookText).width > maxHookW && hookFontSize > 20) {
                hookFontSize -= 2;
                ctx.font = `600 ${hookFontSize}px "Plus Jakarta Sans", sans-serif`;
            }

            if (ctx.measureText(hookText).width > maxHookW) {
                wrapText(ctx, hookText, W / 2, hookY - 14, maxHookW, hookFontSize + 6, 2);
            } else {
                ctx.fillText(hookText, W / 2, hookY);
            }
            ctx.restore();
        }

        // Appel à l'action WhatsApp avec texte auto-dimensionné
        const ctaH = isStory ? 120 : 96;
        const ctaW = W - 140;
        const ctaX = 70;
        const ctaY = isStory ? H - 200 : H - 135;

        ctx.save();
        ctx.shadowColor = 'rgba(37, 211, 102, 0.45)';
        ctx.shadowBlur = 30;
        ctx.shadowOffsetY = 10;
        drawRoundedRect(ctx, ctaX, ctaY, ctaW, ctaH, ctaH / 2);
        ctx.fillStyle = theme.ctaBg;
        ctx.fill();

        ctx.fillStyle = theme.ctaText;
        let ctaFontSize = isStory ? 34 : 28;
        ctx.font = `800 ${ctaFontSize}px "Plus Jakarta Sans", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const fullCta = '💬 ' + ctaText;
        const maxCtaW = ctaW - 50;
        while (ctx.measureText(fullCta).width > maxCtaW && ctaFontSize > 18) {
            ctaFontSize -= 2;
            ctx.font = `800 ${ctaFontSize}px "Plus Jakarta Sans", sans-serif`;
        }

        let finalCta = fullCta;
        if (ctx.measureText(finalCta).width > maxCtaW) {
            while (ctx.measureText(finalCta + '...').width > maxCtaW && finalCta.length > 6) {
                finalCta = finalCta.substring(0, finalCta.length - 1);
            }
            finalCta += '...';
        }

        ctx.fillText(finalCta, W / 2, ctaY + ctaH / 2);
        ctx.restore();

        // Signature bas
        const footY = isStory ? H - 45 : H - 20;
        ctx.save();
        ctx.fillStyle = hexToRgba(theme.textSub, 0.6);
        ctx.font = isStory ? '600 20px "Plus Jakarta Sans", sans-serif' : '600 17px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Boutique en ligne propulsée par Zanika • Paiement & Livraison express', W / 2, footY);
        ctx.restore();
    }

    // RENDU MODE 2 : AFFICHE BOUTIQUE & MARQUE COMPLÈTE
    function renderShopFlyer(ctx, W, H, isStory, theme) {
        const headline = document.getElementById('inputShopHeadline').value || `BIENVENUE CHEZ ${shopName.toUpperCase()}`;
        const tagline = document.getElementById('inputShopTagline').value || 'Votre référence shopping en ligne au Togo.';
        const feat1 = document.getElementById('inputShopFeature1').value || 'Livraison rapide partout au Togo';
        const feat2 = document.getElementById('inputShopFeature2').value || 'Paiement sécurisé & à la livraison';
        const feat3 = document.getElementById('inputShopFeature3').value || 'Produits de qualité 100% garantis';
        const badgeText = document.getElementById('inputBadge').value || 'BOUTIQUE EN LIGNE';
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
        let taglineFontSize = isStory ? (tagline.length > 50 ? 28 : 34) : (tagline.length > 40 ? 22 : 28);
        ctx.font = `600 ${taglineFontSize}px "Plus Jakarta Sans", sans-serif`;
        ctx.textAlign = 'center';
        const taglineLines = wrapText(ctx, tagline, W / 2, sloganY, W - 180, taglineFontSize + 10, 2);
        ctx.restore();

        // 5. 3 Puces de Réassurance / Avantages Boutique
        const featsStartY = isStory ? sloganY + (taglineLines * (taglineFontSize + 10)) + 60 : sloganY + (taglineLines * (taglineFontSize + 10)) + 35;
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
            let featFontSize = isStory ? 28 : 22;
            ctx.font = `700 ${featFontSize}px "Plus Jakarta Sans", sans-serif`;
            const maxFeatW = featCardW - 40;
            while (ctx.measureText(feat).width > maxFeatW && featFontSize > 16) {
                featFontSize -= 1.5;
                ctx.font = `700 ${featFontSize}px "Plus Jakarta Sans", sans-serif`;
            }

            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(feat, W / 2, fY + featCardH / 2);
            ctx.restore();
        });

        // 6. Ruban Lien du Site Web
        if (isStory) {
            const linkY = featsStartY + (featsList.length * (featCardH + featGap)) + 30;
            ctx.save();
            ctx.fillStyle = theme.priceColor;
            let linkFontSize = 28;
            const cleanUrl = `🌐 Visitez notre boutique : ${shopUrl.replace(/^https?:\/\//, '')}`;
            ctx.font = `700 ${linkFontSize}px "Outfit", sans-serif`;
            while (ctx.measureText(cleanUrl).width > W - 140 && linkFontSize > 18) {
                linkFontSize -= 2;
                ctx.font = `700 ${linkFontSize}px "Outfit", sans-serif`;
            }
            ctx.textAlign = 'center';
            ctx.fillText(cleanUrl, W / 2, linkY);
            ctx.restore();
        }

        // 7. Bouton Appel à l'action WhatsApp en bas
        const ctaH = isStory ? 120 : 96;
        const ctaW = W - 140;
        const ctaX = 70;
        const ctaY = isStory ? H - 200 : H - 135;

        ctx.save();
        ctx.shadowColor = 'rgba(37, 211, 102, 0.45)';
        ctx.shadowBlur = 30;
        ctx.shadowOffsetY = 10;
        drawRoundedRect(ctx, ctaX, ctaY, ctaW, ctaH, ctaH / 2);
        ctx.fillStyle = theme.ctaBg;
        ctx.fill();

        ctx.fillStyle = theme.ctaText;
        let ctaFontSize = isStory ? 32 : 26;
        ctx.font = `800 ${ctaFontSize}px "Plus Jakarta Sans", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const fullCta = '💬 ' + ctaText;
        const maxCtaW = ctaW - 50;
        while (ctx.measureText(fullCta).width > maxCtaW && ctaFontSize > 18) {
            ctaFontSize -= 2;
            ctx.font = `800 ${ctaFontSize}px "Plus Jakarta Sans", sans-serif`;
        }

        let finalCta = fullCta;
        if (ctx.measureText(finalCta).width > maxCtaW) {
            while (ctx.measureText(finalCta + '...').width > maxCtaW && finalCta.length > 6) {
                finalCta = finalCta.substring(0, finalCta.length - 1);
            }
            finalCta += '...';
        }

        ctx.fillText(finalCta, W / 2, ctaY + ctaH / 2);
        ctx.restore();

        // 8. Signature bas
        const footY = isStory ? H - 45 : H - 20;
        ctx.save();
        ctx.fillStyle = hexToRgba(theme.textSub, 0.6);
        ctx.font = isStory ? '600 20px "Plus Jakarta Sans", sans-serif' : '600 17px "Plus Jakarta Sans", sans-serif';
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

    function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
        maxLines = maxLines || 3;
        const words = String(text || '').trim().split(/\s+/);
        let line = '';
        let currentY = y;
        let linesCount = 0;

        for (let n = 0; n < words.length; n++) {
            const testLine = line ? (line + ' ' + words[n]) : words[n];
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && n > 0) {
                ctx.fillText(line, x, currentY);
                linesCount++;
                currentY += lineHeight;
                line = words[n];
                if (linesCount >= maxLines - 1 && n < words.length - 1) {
                    let remaining = words.slice(n).join(' ');
                    while (ctx.measureText(remaining + '...').width > maxWidth && remaining.length > 4) {
                        remaining = remaining.substring(0, remaining.lastIndexOf(' ') > 0 ? remaining.lastIndexOf(' ') : remaining.length - 1);
                    }
                    line = remaining + '...';
                    break;
                }
            } else {
                line = testLine;
            }
        }
        if (line) {
            ctx.fillText(line, x, currentY);
            linesCount++;
        }
        return Math.max(1, linesCount);
    }

    // Téléchargement sécurisé & universel de l'affiche (Anti-blocage Safari / iOS / Android / Desktop)
    async function downloadFlyerPNG() {
        const canvas = document.getElementById('flyerCanvas');
        if (!canvas) return;

        const btn = document.getElementById('btnDownloadFlyer');
        const textSpan = document.getElementById('btnDownloadFlyerText');
        const originalText = textSpan ? textSpan.innerText : 'Télécharger l\'Affiche en PNG (HD)';

        try {
            if (btn) {
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.75';
                if (textSpan) textSpan.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:exportSpin .6s linear infinite;vertical-align:middle;margin-right:6px"></span> Préparation HD...';
            }

            let namePrefix = 'Affiche';
            if (currentMode === 'shop') {
                namePrefix = `Affiche_${(shopName || 'Boutique').replace(/[^a-zA-Z0-9_-]/g, '_')}`;
            } else {
                const title = (document.getElementById('inputProductTitle').value || 'Produit').replace(/[^a-zA-Z0-9_-]/g, '_');
                namePrefix = `Affiche_${title}`;
            }

            const filename = `${namePrefix}_${currentFormat}.png`;
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                          (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            // Obtention du Blob PNG
            canvas.toBlob(async function(blob) {
                if (!blob) {
                    // Fallback DataURL immédiat
                    try {
                        const dataUrl = canvas.toDataURL('image/png', 1.0);
                        showFlyerModal(dataUrl, filename);
                    } catch (e) {
                        console.error('Canvas export error:', e);
                        if (window.BoutiqToast) {
                            window.BoutiqToast("Erreur lors de l'export de l'image. Veuillez recharger la page.", "error");
                        }
                    }
                    restoreBtn();
                    return;
                }

                // Cas 1 : Mobile / iOS avec Web Share API (Partage direct avec image PNG : Photos, WhatsApp, etc.)
                if (navigator.canShare) {
                    try {
                        const file = new File([blob], filename, { type: 'image/png' });
                        if (navigator.canShare({ files: [file] })) {
                            await navigator.share({
                                title: filename,
                                text: `Affiche promotionnelle : ${shopName}`,
                                files: [file]
                            });
                            if (window.BoutiqToast) window.BoutiqToast('Affiche partagée avec succès !', 'success');
                            restoreBtn();
                            return;
                        }
                    } catch (shareErr) {
                        if (shareErr.name === 'AbortError') {
                            // L'utilisateur a simplement fermé la feuille de partage
                            restoreBtn();
                            return;
                        }
                        console.warn('Web Share failed, fallback to direct download:', shareErr);
                    }
                }

                // Cas 2 : Téléchargement direct (Desktop Mac/PC & Android standards)
                if (!isIOS) {
                    try {
                        const blobUrl = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.style.display = 'none';
                        link.href = blobUrl;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        setTimeout(function() {
                            if (link.parentNode) link.parentNode.removeChild(link);
                            URL.revokeObjectURL(blobUrl);
                        }, 2500);
                        if (window.BoutiqToast) window.BoutiqToast('Téléchargement de votre affiche HD lancé !', 'success');
                        restoreBtn();
                        return;
                    } catch (dlErr) {
                        console.warn('Blob URL download failed:', dlErr);
                    }
                }

                // Cas 3 : iOS Safari / PWA Standalone (Modale visuelle haute fidélité)
                // Permet à l'utilisateur d'enregistrer l'affiche d'un appui long sans être bloqué
                try {
                    const dataUrl = canvas.toDataURL('image/png', 1.0);
                    showFlyerModal(dataUrl, filename);
                } catch (e) {
                    const blobUrl = URL.createObjectURL(blob);
                    showFlyerModal(blobUrl, filename);
                }
                restoreBtn();

            }, 'image/png', 1.0);

        } catch (err) {
            console.error('downloadFlyerPNG fatal error:', err);
            if (window.BoutiqToast) {
                window.BoutiqToast("Impossible d'exporter l'affiche : " + (err.message || 'erreur'), 'error');
            }
            restoreBtn();
        }

        function restoreBtn() {
            if (btn) {
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
                if (textSpan) textSpan.innerText = originalText;
            }
        }
    }

    // Modale de prévisualisation et d'enregistrement de l'affiche
    function showFlyerModal(imageUrl, filename) {
        const modal = document.getElementById('flyerDownloadModal');
        const img = document.getElementById('modalFlyerImg');
        const link = document.getElementById('modalFlyerDownloadLink');

        if (img) img.src = imageUrl;
        if (link) {
            link.href = imageUrl;
            link.download = filename;
        }
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeFlyerModal() {
        const modal = document.getElementById('flyerDownloadModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
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

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                if (window.BoutiqToast) {
                    window.BoutiqToast("Texte d'annonce copié ! Prêt à coller dans votre statut WhatsApp.", "success");
                } else {
                    alert("Texte copié dans le presse-papier !");
                }
            }).catch(() => {
                fallbackCopyText(text);
            });
        } else {
            fallbackCopyText(text);
        }

        function fallbackCopyText(t) {
            const ta = document.createElement('textarea');
            ta.value = t;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            if (window.BoutiqToast) {
                window.BoutiqToast("Texte d'annonce copié dans le presse-papier !", "success");
            } else {
                alert("Texte copié !");
            }
        }
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

<!-- Modale de Sauvegarde Affiche (Anti-blocage Safari / iOS) -->
<div id="flyerDownloadModal" class="boutiq-modal-backdrop" onclick="if(event.target===this)closeFlyerModal()" style="display:none;align-items:center;justify-content:center;z-index:100002;padding:1rem;position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px)">
    <div class="card" style="max-width:440px;width:100%;max-height:90vh;display:flex;flex-direction:column;padding:1.25rem;border-radius:18px;background:var(--surface);box-shadow:0 24px 60px rgba(0,0,0,0.6);border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
            <h3 style="margin:0;font-size:1.1rem;font-weight:700;display:flex;align-items:center;gap:.5rem">
                <span class="studio-section-icon" style="width:26px;height:26px">
                    <x-icon name="image" :size="15" />
                </span>
                <span>Votre Affiche HD</span>
            </h3>
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeFlyerModal()" style="padding:.2rem .5rem">✕</button>
        </div>
        
        <div style="background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);padding:.65rem .85rem;border-radius:10px;font-size:.82rem;margin-bottom:1rem;line-height:1.4;display:flex;align-items:flex-start;gap:.45rem">
            <x-icon name="smartphone" :size="16" style="flex-shrink:0;margin-top:2px" />
            <div>
                <strong>Astuce Mobile / iPhone :</strong> Maintenez votre doigt appuyé sur l'affiche ci-dessous puis touchez <strong>« Enregistrer dans Photos »</strong> ou <strong>« Partager »</strong>.
            </div>
        </div>

        <div style="overflow:auto;text-align:center;flex:1;margin-bottom:1rem;background:#000;border-radius:12px;padding:.5rem">
            <img id="modalFlyerImg" src="" alt="Affiche générée" style="max-width:100%;max-height:55vh;object-fit:contain;border-radius:8px;display:inline-block">
        </div>

        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a id="modalFlyerDownloadLink" href="#" download="" class="btn btn-primary" style="flex:1;justify-content:center;text-decoration:none;font-size:.88rem">
                <x-icon name="download" size="16" />
                <span>Télécharger</span>
            </a>
            <button type="button" class="btn btn-ghost" onclick="closeFlyerModal()" style="font-size:.88rem">
                Fermer
            </button>
        </div>
    </div>
</div>
@endsection
