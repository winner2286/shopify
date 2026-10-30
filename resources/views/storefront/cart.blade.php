@extends('storefront.layout')

@section('title', 'Mon panier - ' . $shop->name)

@section('content')
<style>
    .cart-container {
        max-width: 960px;
        padding-top: 2.2rem;
        padding-bottom: 6.5rem; /* Espace de sécurité pour le bouton flottant WhatsApp */
    }
    .cart-item-row {
        display: grid;
        grid-template-columns: 76px 1fr auto auto auto;
        gap: 1.25rem;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        align-items: center;
    }
    .cart-item-img {
        width: 76px;
        height: 76px;
        border-radius: 14px;
        overflow: hidden;
        background: var(--bg-soft);
        flex-shrink: 0;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .cart-item-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .cart-item-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text);
        text-decoration: none;
        line-height: 1.35;
    }
    .cart-item-title:hover {
        color: var(--store-primary);
    }
    .cart-item-unit-price {
        font-size: .88rem;
        color: var(--text-soft);
        margin-top: .25rem;
    }
    .cart-qty-ctrl {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: var(--bg-soft);
        padding: .25rem .45rem;
        border-radius: 10px;
        border: 1px solid var(--border);
    }
    .cart-qty-btn {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: none;
        background: var(--surface);
        color: var(--text);
        font-weight: 800;
        font-size: 1.05rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: all .2s;
    }
    .cart-qty-btn:hover {
        background: var(--store-primary);
        color: #fff;
    }
    .cart-qty-val {
        min-width: 28px;
        text-align: center;
        font-weight: 700;
        font-size: .95rem;
    }
    .cart-item-total {
        font-weight: 800;
        font-size: 1.15rem;
        font-family: var(--font-display);
        color: var(--text);
        white-space: nowrap;
    }

    @media (max-width: 640px) {
        .cart-container {
            padding-top: 1.5rem;
            padding-bottom: 7rem;
        }
        .cart-item-row {
            display: flex;
            flex-direction: column;
            gap: .9rem;
            padding: 1.1rem 1rem;
        }
        .cart-item-top {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            width: 100%;
        }
        .cart-item-img {
            width: 68px;
            height: 68px;
            border-radius: 12px;
        }
        .cart-item-details {
            flex: 1;
            min-width: 0;
        }
        .cart-item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding-top: .75rem;
            border-top: 1px dashed var(--border);
        }
        .cart-item-total {
            font-size: 1.1rem;
            color: var(--store-primary);
        }
    }
</style>

<div class="container cart-container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.8rem;flex-wrap:wrap;gap:.75rem">
        <h1 style="font-size:1.85rem;margin:0;font-weight:800;letter-spacing:-0.02em">Mon Panier d'achat</h1>
        <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="font-size:.9rem;font-weight:600;display:inline-flex;align-items:center;gap:.35rem;color:var(--store-primary);text-decoration:none">
            <x-icon name="arrow-left" size="14" /> Continuer mes achats
        </a>
    </div>

    @if (empty($cart))
        <div class="card" style="text-align:center;padding:4rem 1.5rem;border-radius:18px">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="shopping-cart" size="56" />
            </div>
            <h2 style="font-size:1.4rem;margin-bottom:.5rem;font-weight:700">Votre panier est actuellement vide</h2>
            <p style="color:var(--text-soft);max-width:440px;margin:0 auto 1.5rem">
                Vous n'avez pas encore ajouté d'articles. Découvrez notre sélection dès maintenant !
            </p>
            <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="btn btn-store">
                Explorer nos produits
            </a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:1fr;gap:1.8rem">
            <!-- Articles -->
            <div class="card" style="padding:0;overflow:hidden;border-radius:18px">
                <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);font-weight:700;font-size:.9rem;color:var(--text-soft);display:flex;align-items:center;justify-content:space-between">
                    <span>Articles dans votre panier ({{ $cartCount }})</span>
                    <span style="font-size:.8rem;color:var(--ok);font-weight:600">✓ Prêt à valider</span>
                </div>

                <div style="display:flex;flex-direction:column">
                    @foreach ($cart as $item)
                        @php
                            $imgSrc = !empty($item['image_url']) ? $item['image_url'] : '/api/placeholder-image?id=' . $item['product_id'] . '&color=' . urlencode($shop->brand_color ?: '#10b981');
                            $fallbackSrc = '/api/placeholder-image?id=' . $item['product_id'] . '&color=' . urlencode($shop->brand_color ?: '#10b981');
                        @endphp
                        <article class="cart-item-row">
                            <!-- Version Mobile : Haut avec image, détails et corbeille -->
                            <div class="cart-item-top">
                                <div class="cart-item-img">
                                    <img src="{{ $imgSrc }}" alt="{{ $item['title'] }}" loading="eager" onerror="this.onerror=null; this.src='{{ $fallbackSrc }}';">
                                </div>

                                <div class="cart-item-details">
                                    <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $item['slug']) }}" class="cart-item-title">
                                        {{ $item['title'] }}
                                    </a>
                                    <div class="cart-item-unit-price">
                                        Prix unitaire : {{ number_format($item['price'], 0, ',', ' ') }} FCFA
                                    </div>
                                </div>

                                <!-- Bouton Supprimer (affiché à droite sur mobile) -->
                                <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/supprimer/' . $item['product_id']) }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost" style="color:var(--danger);padding:.4rem .55rem;display:inline-flex;align-items:center;border-radius:8px" title="Retirer l'article" aria-label="Retirer {{ $item['title'] }}">
                                        <x-icon name="trash" size="16" />
                                    </button>
                                </form>
                            </div>

                            <!-- Version Mobile : Bas avec stepper quantité et sous-total -->
                            <div class="cart-item-bottom">
                                <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/maj') }}" class="cart-qty-ctrl">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] - 1 }}" class="cart-qty-btn" title="Diminuer" aria-label="Diminuer">-</button>
                                    <span class="cart-qty-val">{{ $item['quantity'] }}</span>
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] + 1 }}" class="cart-qty-btn" title="Augmenter" aria-label="Augmenter">+</button>
                                </form>

                                <div class="cart-item-total">
                                    {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div style="padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;background:var(--bg-soft);flex-wrap:wrap;gap:.75rem">
                    <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/vider') }}" data-confirm="Voulez-vous vider l'ensemble de votre panier ?">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);font-weight:600">
                            Vider le panier
                        </button>
                    </form>

                    <div style="font-size:.85rem;color:var(--text-soft);display:flex;align-items:center;gap:.35rem">
                        <x-icon name="check-circle" size="14" style="color:var(--ok)" /> Paiement sécurisé T-Money, Flooz ou à la livraison
                    </div>
                </div>
            </div>

            <!-- Récapitulatif Total & Commande -->
            <div class="card" style="background:var(--surface);border:1px solid color-mix(in srgb, var(--store-primary) 30%, var(--border));border-radius:18px;padding:1.6rem">
                <h2 style="margin-top:0;font-size:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.8rem;font-weight:800">
                    Récapitulatif de la commande
                </h2>

                <div style="display:flex;justify-content:space-between;margin-bottom:.8rem;font-size:1rem">
                    <span style="color:var(--text-soft)">Sous-total des articles :</span>
                    <span style="font-weight:700">{{ number_format($subtotal, 0, ',', ' ') }} FCFA</span>
                </div>

                <div style="display:flex;justify-content:space-between;margin-bottom:1.2rem;font-size:1rem">
                    <span style="color:var(--text-soft)">Frais de livraison :</span>
                    <span style="color:var(--ok);font-weight:700">Calculés à la livraison</span>
                </div>

                <div style="border-top:2px dashed var(--border);padding-top:1.2rem;margin-bottom:1.8rem;display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:.5rem">
                    <span style="font-size:1.15rem;font-weight:800">Total à régler :</span>
                    <span style="font-size:1.75rem;font-weight:900;font-family:var(--font-display);color:var(--store-primary)">
                        {{ number_format($subtotal, 0, ',', ' ') }} FCFA
                    </span>
                </div>

                <!-- Actions de validation -->
                @php
                    $waSummary = "Bonjour {$shop->name}, je souhaite valider mon panier :\n";
                    foreach ($cart as $it) {
                        $waSummary .= "- {$it['quantity']}x {$it['title']} (" . number_format($it['price'] * $it['quantity'], 0, ',', ' ') . " FCFA)\n";
                    }
                    $waSummary .= "TOTAL : " . number_format($subtotal, 0, ',', ' ') . " FCFA\n";
                    $waUrl = $shop->whatsapp_formatted ? "https://wa.me/{$shop->whatsapp_formatted}?text=" . urlencode($waSummary) : null;
                @endphp

                <div style="display:flex;flex-direction:column;gap:.85rem">
                    <a href="{{ route('storefront.checkout.show', $shop->subdomain) }}" class="btn btn-store" style="font-size:1.05rem;padding:1rem 1.5rem;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:12px">
                        <x-icon name="shopping-bag" size="18" /> Passer la commande en ligne
                    </a>

                    @if ($waUrl)
                        <a href="{{ $waUrl }}" target="_blank" class="btn btn-whatsapp" style="font-size:.95rem;padding:.85rem 1.2rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:12px">
                            <x-icon name="whatsapp" size="18" /> Commander directement sur WhatsApp
                        </a>
                    @endif

                    <div style="font-size:.82rem;text-align:center;color:var(--text-faint);margin-top:.4rem;display:inline-flex;align-items:center;justify-content:center;gap:.35rem;width:100%">
                        <x-icon name="lock" size="13" /> Vos informations sont protégées et transmises directement au commerçant.
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
