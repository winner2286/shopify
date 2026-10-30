@extends('storefront.layout')

@section('title', 'Mon panier - ' . $shop->name)

@section('content')
<div class="container" style="max-width:960px;padding-top:2.5rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
        <h1 style="font-size:2rem;margin:0">Mon Panier d'achat</h1>
        <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="font-size:.95rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
            <x-icon name="arrow-left" size="14" /> Continuer mes achats
        </a>
    </div>

    @if (empty($cart))
        <div class="card" style="text-align:center;padding:4rem 1.5rem">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="shopping-cart" size="56" />
            </div>
            <h2 style="font-size:1.4rem;margin-bottom:.5rem">Votre panier est actuellement vide</h2>
            <p style="color:var(--text-soft);max-width:440px;margin:0 auto 1.5rem">
                Vous n'avez pas encore ajouté d'articles. Découvrez notre sélection dès maintenant !
            </p>
            <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="btn-store">
                Explorer nos produits
            </a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:1fr;gap:2rem">
            <!-- Articles -->
            <div class="card" style="padding:0;overflow:hidden">
                <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);font-weight:600;font-size:.9rem;color:var(--text-soft)">
                    Articles dans votre panier ({{ $cartCount }})
                </div>

                <div style="display:flex;flex-direction:column">
                    @foreach ($cart as $item)
                        <div style="display:flex;gap:1.2rem;padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);align-items:center;flex-wrap:wrap">
                            <!-- Image -->
                            <div style="width:72px;height:72px;border-radius:10px;overflow:hidden;background:var(--bg-soft);flex-shrink:0;border:1px solid var(--border)">
                                @if ($item['image_url'])
                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" style="width:100%;height:100%;object-fit:cover">
                                @else
                                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-faint)">
                                        <x-icon name="shopping-bag" size="24" />
                                    </div>
                                @endif
                            </div>

                            <!-- Détails -->
                            <div style="flex:1;min-width:180px">
                                <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $item['slug']) }}" style="font-weight:600;font-size:1.05rem;color:var(--text)">
                                    {{ $item['title'] }}
                                </a>
                                <div style="font-size:.9rem;color:var(--text-soft);margin-top:.2rem">
                                    Prix unitaire : {{ number_format($item['price'], 0, ',', ' ') }} FCFA
                                </div>
                            </div>

                            <!-- Modification quantité -->
                            <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/maj') }}" style="display:flex;align-items:center;gap:.3rem">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                <button type="submit" name="quantity" value="{{ $item['quantity'] - 1 }}" class="btn btn-ghost" style="padding:.3rem .7rem;font-weight:bold" title="Diminuer">-</button>
                                <span style="font-weight:700;font-size:1.05rem;min-width:28px;text-align:center">{{ $item['quantity'] }}</span>
                                <button type="submit" name="quantity" value="{{ $item['quantity'] + 1 }}" class="btn btn-ghost" style="padding:.3rem .7rem;font-weight:bold" title="Augmenter">+</button>
                            </form>

                            <!-- Sous-total article -->
                            <div style="text-align:right;min-width:110px">
                                <div style="font-weight:700;font-size:1.15rem;font-family:var(--font-display)">
                                    {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }} FCFA
                                </div>
                            </div>

                            <!-- Bouton Supprimer -->
                            <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/supprimer/' . $item['product_id']) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="color:var(--danger);padding:.4rem .6rem;display:inline-flex;align-items:center" title="Retirer l'article">
                                    <x-icon name="trash" size="16" />
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div style="padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;background:var(--bg-soft)">
                    <form method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/vider') }}" data-confirm="Voulez-vous vider l'ensemble de votre panier ?">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">
                            Vider le panier
                        </button>
                    </form>

                    <div style="font-size:.9rem;color:var(--text-soft)">
                        Paiement sécurisé par T-Money, Flooz ou à la livraison
                    </div>
                </div>
            </div>

            <!-- Récapitulatif Total & Commande -->
            <div class="card" style="background:var(--surface);border:1px solid var(--border)">
                <h3 style="margin-top:0;font-size:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
                    Récapitulatif de la commande
                </h3>

                <div style="display:flex;justify-content:space-between;margin-bottom:.8rem;font-size:1rem">
                    <span style="color:var(--text-soft)">Sous-total des articles :</span>
                    <span style="font-weight:600">{{ number_format($subtotal, 0, ',', ' ') }} FCFA</span>
                </div>

                <div style="display:flex;justify-content:space-between;margin-bottom:1.2rem;font-size:1rem">
                    <span style="color:var(--text-soft)">Frais de livraison :</span>
                    <span style="color:var(--ok);font-weight:600">Calculés à la livraison</span>
                </div>

                <div style="border-top:2px dashed var(--border);padding-top:1.2rem;margin-bottom:1.8rem;display:flex;justify-content:space-between;align-items:baseline">
                    <span style="font-size:1.2rem;font-weight:700">Total à régler :</span>
                    <span style="font-size:1.8rem;font-weight:700;font-family:var(--font-display);color:var(--text)">
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

                <div style="display:flex;flex-direction:column;gap:.8rem">
                    <a href="{{ route('storefront.checkout.show', $shop->subdomain) }}" class="btn-store" style="font-size:1.1rem;padding:1rem 1.5rem;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                        <x-icon name="shopping-bag" size="18" /> Passer la commande en ligne
                    </a>

                    @if ($waUrl)
                        <a href="{{ $waUrl }}" target="_blank" class="btn-whatsapp" style="font-size:.95rem;padding:.75rem 1.2rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                            <x-icon name="whatsapp" size="18" /> Ou commander directement via WhatsApp
                        </a>
                    @endif

                    <div style="font-size:.85rem;text-align:center;color:var(--text-faint);margin-top:.4rem;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;width:100%">
                        <x-icon name="lock" size="14" /> Vos informations sont confidentielles et transmises directement au commerçant.
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
