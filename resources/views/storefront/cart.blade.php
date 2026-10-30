@extends('storefront.layout')

@section('title', 'Mon panier - ' . $shop->name)

@section('content')
<style>
    .cart-wrapper {
        max-width: 760px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }
    .cart-header-box {
        text-align: center;
        margin-bottom: 2rem;
    }
    .cart-back-link {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .88rem;
        font-weight: 600;
        color: var(--store-primary);
        text-decoration: none;
        margin-bottom: .85rem;
        transition: transform .2s ease;
    }
    .cart-back-link:hover {
        transform: translateX(-3px);
    }
    .cart-main-title {
        font-size: clamp(1.8rem, 4.5vw, 2.3rem);
        font-weight: 900;
        letter-spacing: -0.025em;
        margin: 0 0 .4rem;
        color: var(--text);
    }
    .cart-subtitle {
        color: var(--text-soft);
        font-size: .95rem;
        margin: 0;
    }

    /* Cart Items Card */
    .cart-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.6rem;
    }
    .cart-box-header {
        padding: .9rem 1.4rem;
        background: color-mix(in srgb, var(--surface) 80%, var(--bg-soft));
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .88rem;
        font-weight: 700;
        color: var(--text-soft);
    }
    .cart-box-status {
        color: var(--ok);
        font-size: .82rem;
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    /* Item row */
    .cart-product-row {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1.25rem 1.4rem;
        border-bottom: 1px solid var(--border);
        transition: background .2s ease;
    }
    .cart-product-row:last-child {
        border-bottom: none;
    }
    .cart-product-row:hover {
        background: color-mix(in srgb, var(--surface) 95%, var(--bg-soft));
    }
    .cart-product-img {
        width: 76px;
        height: 76px;
        border-radius: 14px;
        overflow: hidden;
        background: var(--bg-soft);
        border: 1px solid var(--border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .cart-product-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .cart-product-content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }
    .cart-product-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
    }
    .cart-product-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--text);
        text-decoration: none;
        margin: 0;
        line-height: 1.3;
        text-transform: capitalize;
    }
    .cart-product-title:hover {
        color: var(--store-primary);
    }
    .cart-btn-trash {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: #ef4444;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .2s ease;
        flex-shrink: 0;
    }
    .cart-btn-trash:hover {
        background: #ef4444;
        color: #fff;
    }
    .cart-product-unit {
        font-size: .85rem;
        color: var(--text-soft);
    }
    .cart-product-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: .3rem;
    }
    .cart-stepper-wrap {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: .2rem .4rem;
    }
    .cart-stepper-btn {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        border: none;
        background: var(--surface);
        color: var(--text);
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        transition: all .15s ease;
    }
    .cart-stepper-btn:hover {
        background: var(--store-primary);
        color: #fff;
    }
    .cart-stepper-value {
        min-width: 26px;
        text-align: center;
        font-weight: 800;
        font-size: .95rem;
        color: var(--text);
    }
    .cart-product-total {
        font-size: 1.15rem;
        font-weight: 900;
        font-family: var(--font-display);
        color: var(--store-primary);
        white-space: nowrap;
    }

    /* Cart Footer Bar */
    .cart-box-footer {
        padding: .9rem 1.4rem;
        background: color-mix(in srgb, var(--surface) 90%, var(--bg-soft));
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .8rem;
    }
    .btn-clear-link {
        background: none;
        border: none;
        color: var(--danger);
        font-size: .82rem;
        font-weight: 700;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
        opacity: .85;
        transition: opacity .2s;
    }
    .btn-clear-link:hover {
        opacity: 1;
    }
    .cart-secure-text {
        font-size: .8rem;
        color: var(--text-soft);
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    /* Summary Card */
    .cart-summary-box {
        background: var(--surface);
        border: 1px solid color-mix(in srgb, var(--store-primary) 35%, var(--border));
        border-radius: 20px;
        padding: 1.8rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
    }
    .cart-summary-title {
        font-size: 1.25rem;
        font-weight: 900;
        margin: 0 0 1.25rem;
        color: var(--text);
        letter-spacing: -0.01em;
    }
    .cart-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: .85rem;
        font-size: .95rem;
        color: var(--text-soft);
    }
    .cart-summary-row strong {
        color: var(--text);
        font-weight: 700;
    }
    .cart-summary-divider {
        height: 1px;
        background: var(--border);
        border: none;
        margin: 1.1rem 0;
    }
    .cart-summary-total-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin: 1.2rem 0 1.6rem;
    }
    .cart-summary-total-label {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text);
    }
    .cart-summary-total-value {
        font-size: 1.85rem;
        font-weight: 900;
        font-family: var(--font-display);
        color: var(--store-primary);
        letter-spacing: -0.02em;
    }

    /* Action buttons */
    .btn-cart-checkout {
        width: 100%;
        padding: 1rem 1.4rem;
        border-radius: 14px;
        background: var(--store-primary);
        color: #fff;
        font-weight: 800;
        font-size: 1.05rem;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        text-decoration: none;
        box-shadow: 0 6px 20px var(--store-primary-glow);
        transition: transform .2s ease, box-shadow .2s ease;
        cursor: pointer;
    }
    .btn-cart-checkout:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 28px var(--store-primary-glow);
        color: #fff;
    }
    .cart-or-divider {
        display: flex;
        align-items: center;
        gap: .8rem;
        margin: 1rem 0;
        color: var(--text-faint);
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .cart-or-divider::before,
    .cart-or-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }
    .btn-cart-wa-direct {
        width: 100%;
        padding: .85rem 1.4rem;
        border-radius: 14px;
        background: color-mix(in srgb, #25D366 12%, var(--surface));
        border: 1.5px solid #25D366;
        color: #128C7E;
        font-weight: 800;
        font-size: .95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        text-decoration: none;
        transition: all .2s ease;
    }
    .btn-cart-wa-direct:hover {
        background: #25D366;
        color: #fff;
        box-shadow: 0 6px 18px rgba(37, 211, 102, 0.35);
    }
    .cart-trust-note {
        margin-top: 1.25rem;
        text-align: center;
        font-size: .8rem;
        color: var(--text-faint);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        line-height: 1.4;
    }

    @media (max-width: 640px) {
        .cart-wrapper {
            padding: 1.2rem .75rem 3.5rem;
        }
        .cart-main-title {
            font-size: 1.7rem;
        }
        .cart-product-row {
            padding: 1rem .9rem;
            gap: .9rem;
        }
        .cart-product-img {
            width: 70px;
            height: 70px;
            border-radius: 12px;
        }
        .cart-product-title {
            font-size: .98rem;
        }
        .cart-product-total {
            font-size: 1.05rem;
        }
        .cart-summary-box {
            padding: 1.3rem 1.1rem;
            border-radius: 18px;
        }
        .cart-summary-total-value {
            font-size: 1.55rem;
        }
        .btn-cart-checkout {
            padding: .9rem 1.2rem;
            font-size: .98rem;
        }
        .btn-cart-wa-direct {
            padding: .8rem 1.1rem;
            font-size: .9rem;
        }
    }
</style>

<div class="cart-wrapper">
    <!-- En-tête bien centré et aéré -->
    <div class="cart-header-box">
        <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="cart-back-link">
            <x-icon name="arrow-left" size="14" /> Continuer mes achats
        </a>
        <h1 class="cart-main-title">Mon Panier d'achat</h1>
        <p class="cart-subtitle">
            @if (!empty($cart))
                {{ $cartCount }} article{{ $cartCount > 1 ? 's' : '' }} prêt{{ $cartCount > 1 ? 's' : '' }} à être validé{{ $cartCount > 1 ? 's' : '' }}
            @else
                Votre sélection de commande
            @endif
        </p>
    </div>

    @if (empty($cart))
        <div class="cart-box" style="text-align:center;padding:3.5rem 1.5rem">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="shopping-cart" size="56" />
            </div>
            <h2 style="font-size:1.35rem;margin-bottom:.5rem;font-weight:800">Votre panier est actuellement vide</h2>
            <p style="color:var(--text-soft);max-width:440px;margin:0 auto 1.5rem;font-size:.95rem">
                Vous n'avez pas encore ajouté d'articles. Découvrez notre sélection dès maintenant !
            </p>
            <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="btn btn-store" style="padding:.75rem 1.5rem;border-radius:12px;font-weight:700">
                Explorer nos produits
            </a>
        </div>
    @else
        <!-- Boîte des Articles -->
        <div class="cart-box">
            <div class="cart-box-header">
                <span>Articles dans votre commande ({{ $cartCount }})</span>
                <span class="cart-box-status">
                    <x-icon name="check-circle" size="13" /> En stock
                </span>
            </div>

            <div style="display:flex;flex-direction:column">
                @foreach ($cart as $item)
                    @php
                        $imgSrc = !empty($item['image_url']) ? $item['image_url'] : '/api/placeholder-image?id=' . $item['product_id'] . '&color=' . urlencode($shop->brand_color ?: '#10b981');
                        $fallbackSrc = '/api/placeholder-image?id=' . $item['product_id'] . '&color=' . urlencode($shop->brand_color ?: '#10b981');
                    @endphp
                    <article class="cart-product-row">
                        <!-- Image Vignette Propre -->
                        <div class="cart-product-img">
                            <img src="{{ $imgSrc }}" alt="{{ $item['title'] }}" loading="eager" onerror="this.onerror=null; this.src='{{ $fallbackSrc }}';">
                        </div>

                        <!-- Détails & Stepper Réunis Harmoniquement -->
                        <div class="cart-product-content">
                            <div class="cart-product-top">
                                <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $item['slug']) }}" class="cart-product-title">
                                    {{ $item['title'] }}
                                </a>
                                <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/supprimer/' . $item['product_id']) }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="cart-btn-trash" title="Retirer l'article" aria-label="Retirer {{ $item['title'] }}">
                                        <x-icon name="trash" size="15" />
                                    </button>
                                </form>
                            </div>

                            <div class="cart-product-unit">
                                Prix unitaire : {{ number_format($item['price'], 0, ',', ' ') }} FCFA
                            </div>

                            <div class="cart-product-bottom">
                                <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/maj') }}" class="cart-stepper-wrap">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] - 1 }}" class="cart-stepper-btn" title="Diminuer" aria-label="Diminuer">-</button>
                                    <span class="cart-stepper-value">{{ $item['quantity'] }}</span>
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] + 1 }}" class="cart-stepper-btn" title="Augmenter" aria-label="Augmenter">+</button>
                                </form>

                                <div class="cart-product-total">
                                    {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Pied de la boîte Articles -->
            <div class="cart-box-footer">
                <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/vider') }}" data-confirm="Voulez-vous vider l'ensemble de votre panier ?">
                    @csrf
                    <button type="submit" class="btn-clear-link">
                        Vider le panier
                    </button>
                </form>

                <div class="cart-secure-text">
                    <x-icon name="check-circle" size="13" style="color:var(--ok)" /> Paiement T-Money, Flooz ou à la livraison
                </div>
            </div>
        </div>

        <!-- Boîte Récapitulatif Total & Validation -->
        <div class="cart-summary-box">
            <h2 class="cart-summary-title">
                Récapitulatif de la commande
            </h2>

            <div class="cart-summary-row">
                <span>Sous-total des articles :</span>
                <strong>{{ number_format($subtotal, 0, ',', ' ') }} FCFA</strong>
            </div>

            <div class="cart-summary-row">
                <span>Frais de livraison :</span>
                <span style="color:var(--ok);font-weight:700">Calculés à l'étape suivante</span>
            </div>

            <hr class="cart-summary-divider">

            <div class="cart-summary-total-row">
                <span class="cart-summary-total-label">Total à régler :</span>
                <span class="cart-summary-total-value">
                    {{ number_format($subtotal, 0, ',', ' ') }} FCFA
                </span>
            </div>

            <!-- Actions de validation ordonnées -->
            @php
                $waSummary = "Bonjour {$shop->name}, je souhaite valider mon panier :\n";
                foreach ($cart as $it) {
                    $waSummary .= "- {$it['quantity']}x {$it['title']} (" . number_format($it['price'] * $it['quantity'], 0, ',', ' ') . " FCFA)\n";
                }
                $waSummary .= "TOTAL : " . number_format($subtotal, 0, ',', ' ') . " FCFA\n";
                $waUrl = $shop->whatsapp_formatted ? "https://wa.me/{$shop->whatsapp_formatted}?text=" . urlencode($waSummary) : null;
            @endphp

            <div style="display:flex;flex-direction:column">
                <!-- Action Principale : Commande en ligne -->
                <a href="{{ route('storefront.checkout.show', $shop->subdomain) }}" class="btn-cart-checkout">
                    <x-icon name="shopping-bag" size="18" />
                    <span>Passer la commande en ligne</span>
                    <x-icon name="arrow-right" size="16" />
                </a>

                @if ($waUrl)
                    <!-- Séparateur visuel élégant -->
                    <div class="cart-or-divider">
                        <span>ou</span>
                    </div>

                    <!-- Action Alternative : Commande directe WhatsApp -->
                    <a href="{{ $waUrl }}" target="_blank" class="btn-cart-wa-direct">
                        <x-icon name="whatsapp" size="18" />
                        <span>Commander directement via WhatsApp</span>
                    </a>
                @endif

                <div class="cart-trust-note">
                    <x-icon name="lock" size="13" />
                    <span>Commande 100% sécurisée • Vos informations restent confidentielles</span>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
