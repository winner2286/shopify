@extends('storefront.layout')

@section('title', $product->title . ' - ' . $shop->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description), 160))

@section('content')
@php
    $images = $product->images;
    $firstImg = $images->first();
    $hasDiscount = $product->compare_at_price && $product->compare_at_price > $product->price;
    $discountPct = $hasDiscount ? round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100) : 0;
    $isOutOfStock = $product->track_stock && $product->stock <= 0;
    $isLowStock = $product->track_stock && $product->stock > 0 && $product->stock <= $product->low_stock_threshold;
@endphp

<div class="container" style="padding-top:2rem;padding-bottom:5rem">
    <!-- Fil d'Ariane -->
    <nav style="font-size:.85rem;color:var(--text-faint);margin-bottom:1.5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <a href="{{ url('/b/' . $shop->subdomain) }}" style="color:var(--text-faint)">Accueil</a>
        <span>/</span>
        <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="color:var(--text-faint)">Produits</a>
        @if ($product->categories->isNotEmpty())
            <span>/</span>
            <a href="{{ url('/b/' . $shop->subdomain . '/produits?categorie=' . $product->categories->first()->slug) }}" style="color:var(--text-faint)">
                {{ $product->categories->first()->name }}
            </a>
        @endif
        <span>/</span>
        <span style="color:var(--text);font-weight:500">{{ \Illuminate\Support\Str::limit($product->title, 30) }}</span>
    </nav>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:3rem;align-items:start">
        <!-- Galerie Photo -->
        <div class="reveal-on-scroll in-view">
            <div style="position:relative;border-radius:var(--radius);overflow:hidden;background:var(--bg-soft);border:1px solid color-mix(in srgb, var(--store-primary) 20%, var(--border));aspect-ratio:1;box-shadow:0 12px 35px rgba(0,0,0,0.18)">
                @if ($firstImg)
                    <img id="main-product-img" src="{{ $firstImg->url }}" alt="{{ $product->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s ease" onerror="this.onerror=null; this.src='/api/placeholder-image?id={{ $product->id }}&color={{ urlencode($shop->brand_color ?: '#10b981') }}'">
                @else
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-faint)">
                        <x-icon name="shopping-bag" size="48" />
                    </div>
                @endif

                @if ($hasDiscount)
                    <span style="position:absolute;top:1rem;left:1rem;background:linear-gradient(135deg, #ef4444, #dc2626);color:#fff;font-weight:800;padding:.35rem .8rem;border-radius:10px;font-size:.85rem;box-shadow:0 4px 14px rgba(220,38,38,0.4);animation:discountPulse 2.5s infinite">
                        -{{ $discountPct }}% ÉCONOMIE
                    </span>
                @endif
            </div>

            <!-- Vignettes -->
            @if ($images->count() > 1)
                <div style="display:flex;gap:.75rem;margin-top:1rem;overflow-x:auto;padding-bottom:.5rem">
                    @foreach ($images as $img)
                        <button type="button" onclick="document.getElementById('main-product-img').src = '{{ $img->url }}'" style="border:2px solid var(--border);border-radius:10px;padding:0;overflow:hidden;width:68px;height:68px;cursor:pointer;flex-shrink:0;background:var(--bg-soft);transition:border-color .2s" onmouseover="this.style.borderColor='var(--store-primary)'" onmouseout="this.style.borderColor='var(--border)'">
                            <img src="{{ $img->thumbnail_url }}" alt="" style="width:100%;height:100%;object-fit:cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Informations Produit & Actions -->
        <div class="reveal-on-scroll in-view" style="--delay: 1">
            <!-- Badge Visiteurs en direct -->
            <div style="display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .85rem;border-radius:99px;background:rgba(239, 68, 68, 0.12);border:1px solid rgba(239, 68, 68, 0.28);color:#ef4444;font-size:.8rem;font-weight:700;margin-bottom:1rem">
                <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 10px #ef4444;animation:discountPulse 1.8s infinite"></span>
                <span><span id="product-viewers-count">4 personnes</span> consultent cet article actuellement</span>
            </div>

            @if ($product->categories->isNotEmpty())
                <div style="display:flex;gap:.5rem;margin-bottom:.6rem">
                    @foreach ($product->categories as $c)
                        <span style="font-size:.8rem;font-weight:600;padding:.2rem .6rem;background:var(--surface);border-radius:6px;border:1px solid var(--border);color:var(--text-soft)">
                            {{ $c->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            <h1 style="font-size:clamp(1.6rem, 4vw, 2.3rem);margin:0 0 .8rem;line-height:1.25">
                {{ $product->title }}
            </h1>

            @if ($product->sku)
                <div style="font-size:.85rem;color:var(--text-faint);margin-bottom:1rem">
                    Réf : <span style="font-family:monospace">{{ $product->sku }}</span>
                </div>
            @endif

            <!-- Prix -->
            <div style="background:var(--surface);border:1px solid color-mix(in srgb, var(--store-primary) 22%, var(--border));border-radius:var(--radius-sm);padding:1.3rem;margin-bottom:1.5rem;box-shadow:0 6px 20px rgba(0,0,0,0.08)">
                <div style="display:flex;align-items:baseline;gap:.8rem">
                    <span style="font-size:2.2rem;font-weight:800;font-family:var(--font-display);color:var(--text);letter-spacing:-.02em">
                        {{ number_format($product->price, 0, ',', ' ') }} FCFA
                    </span>
                    @if ($hasDiscount)
                        <span style="font-size:1.15rem;color:var(--text-faint);text-decoration:line-through">
                            {{ number_format($product->compare_at_price, 0, ',', ' ') }} FCFA
                        </span>
                    @endif
                </div>

                <!-- Disponibilité du stock -->
                <div style="margin-top:.75rem;font-size:.9rem">
                    @if ($isOutOfStock)
                        <span style="color:var(--danger);font-weight:700;display:inline-flex;align-items:center;gap:.4rem"><x-icon name="alert-triangle" size="16" /> Actuellement en rupture de stock</span>
                    @elseif ($isLowStock)
                        <span style="color:var(--warn);font-weight:700;display:inline-flex;align-items:center;gap:.4rem"><x-icon name="flame" size="16" /> Plus que {{ $product->stock }} article(s) disponible(s) ! Commandez vite</span>
                    @else
                        <span style="color:#10b981;font-weight:700;display:inline-flex;align-items:center;gap:.4rem">
                            <span class="card-stock-dot"></span> En stock (disponible immédiatement pour livraison)
                        </span>
                    @endif
                </div>
            </div>

            <!-- Formulaire d'ajout au panier & Commande directe -->
            @if (! $isOutOfStock)
                <form id="product-buy-form" class="ajax-add-cart" method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/ajouter') }}" style="margin-bottom:1.5rem">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    <div style="margin-bottom:1.2rem">
                        <label for="quantity" class="label" style="font-size:.9rem">Quantité souhaitée :</label>
                        <div style="display:flex;align-items:center;gap:.5rem;max-width:180px">
                            <button type="button" class="btn btn-ghost" style="padding:.5rem 1rem" onclick="var q = document.getElementById('quantity'); if(parseInt(q.value) > 1) q.value = parseInt(q.value) - 1;">-</button>
                            <input id="quantity" type="number" name="quantity" value="1" min="1" max="{{ $product->track_stock ? $product->stock : 99 }}" class="input" style="text-align:center;font-size:1.1rem;font-weight:600;padding:.5rem">
                            <button type="button" class="btn btn-ghost" style="padding:.5rem 1rem" onclick="var q = document.getElementById('quantity'); if(parseInt(q.value) < parseInt(q.max)) q.value = parseInt(q.value) + 1;">+</button>
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:.75rem">
                        <button type="submit" class="btn-store" style="font-size:1.05rem;padding:.95rem 1.5rem;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;box-shadow:0 6px 20px var(--store-primary-glow)">
                            <x-icon name="shopping-cart" size="18" /> Ajouter au panier
                        </button>

                        @if ($waUrl)
                            <a href="{{ $waUrl }}" target="_blank" class="btn-whatsapp" style="font-size:1rem;padding:.95rem 1.5rem;display:inline-flex;align-items:center;justify-content:center;gap:.6rem">
                                <x-icon name="whatsapp" size="20" /> Commander directement sur WhatsApp
                            </a>
                        @endif
                    </div>
                </form>
            @else
                <div style="margin-bottom:1.5rem">
                    @if ($waUrl)
                        <a href="{{ $waUrl }}" target="_blank" class="btn-whatsapp" style="font-size:1rem;padding:.85rem 1.5rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                            <x-icon name="whatsapp" size="18" /> Être prévenu du retour en stock sur WhatsApp
                        </a>
                    @endif
                </div>
            @endif

            <!-- Description -->
            <div style="border-top:1px solid var(--border);padding-top:1.5rem;margin-top:1.5rem">
                <h3 style="font-size:1.15rem;margin-bottom:.8rem">Description du produit</h3>
                <div style="color:var(--text-soft);line-height:1.75;font-size:.95rem;white-space:pre-line">
                    {{ $product->description }}
                </div>
            </div>

            <!-- Engagements & Confiance -->
            <div style="background:var(--bg-soft);border:1px solid color-mix(in srgb, var(--store-primary) 15%, var(--border));border-radius:var(--radius-sm);padding:1.1rem 1.3rem;margin-top:2rem;display:flex;flex-direction:column;gap:.75rem;font-size:.85rem">
                <div style="display:flex;align-items:center;gap:.65rem">
                    <span style="color:var(--store-primary)"><x-icon name="truck" size="18" /></span>
                    <span><strong>Livraison garantie :</strong> Expédition rapide à Lomé et dans toutes les régions du Togo.</span>
                </div>
                <div style="display:flex;align-items:center;gap:.65rem">
                    <span style="color:var(--store-primary)"><x-icon name="wallet" size="18" /></span>
                    <span><strong>Paiement facile :</strong> T-Money, Flooz ou à la livraison en espèces.</span>
                </div>
                <div style="display:flex;align-items:center;gap:.65rem">
                    <span style="color:var(--store-primary)"><x-icon name="shield" size="18" /></span>
                    <span><strong>Vendeur certifié :</strong> Assistance réactive par appel direct ou WhatsApp.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Articles similaires (Recommandations) -->
    @if ($relatedProducts->isNotEmpty())
        <div style="margin-top:5rem" class="reveal-on-scroll">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
                <div>
                    <h2 style="font-size:1.45rem;font-family:var(--font-display);font-weight:800;margin:0 0 .25rem">Vous pourriez aussi aimer</h2>
                    <p style="color:var(--text-soft);font-size:.9rem;margin:0">Découvrez d'autres articles recommandés par {{ $shop->name }}</p>
                </div>
                <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="font-size:.88rem;color:var(--store-primary);font-weight:700;text-decoration:none">
                    Tout voir &rarr;
                </a>
            </div>

            <div class="store-grid">
                @foreach ($relatedProducts as $rel)
                    @php
                        $relImg = $rel->images->first();
                        $relHasDiscount = $rel->compare_at_price && $rel->compare_at_price > $rel->price;
                        $relDiscountPct = $relHasDiscount ? round((($rel->compare_at_price - $rel->price) / $rel->compare_at_price) * 100) : 0;
                        $relCat = $rel->categories->first()?->name ?? '';
                        $relWaUrl = $shop->whatsapp_formatted ? 'https://wa.me/' . $shop->whatsapp_formatted . '?text=' . urlencode('Bonjour ' . $shop->name . ', je souhaite commander "' . $rel->title . '" à ' . number_format($rel->price, 0, ',', ' ') . ' FCFA : ' . url('/b/' . $shop->subdomain . '/produits/' . $rel->slug)) : null;
                    @endphp
                    <article class="product-card reveal-on-scroll in-view"
                        style="--delay: {{ $loop->index % 8 }}"
                        data-product-id="{{ $rel->id }}"
                        data-product-title="{{ $rel->title }}"
                        data-product-price="{{ number_format($rel->price, 0, ',', ' ') }} FCFA"
                        data-product-old-price="{{ $relHasDiscount ? number_format($rel->compare_at_price, 0, ',', ' ') . ' FCFA' : '' }}"
                        data-product-discount="{{ $relDiscountPct }}"
                        data-product-description="{{ e($rel->description) }}"
                        data-product-category="{{ $relCat }}"
                        data-product-url="{{ url('/b/' . $shop->subdomain . '/produits/' . $rel->slug) }}"
                        data-product-wa="{{ $relWaUrl }}"
                        data-product-color="{{ $shop->brand_color ?: '#10b981' }}"
                        data-product-images='@json($rel->images->map(fn($i) => ["url" => $i->url, "thumb" => $i->thumbnail_url]))'
                    >
                        <div class="product-img-wrap">
                            <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $rel->slug) }}">
                                @if ($relImg)
                                    <img src="{{ $relImg->thumbnail_url ?: $relImg->url }}" alt="{{ $rel->title }}" loading="lazy" onerror="this.onerror=null; this.src='/api/placeholder-image?id={{ $rel->id }}&color={{ urlencode($shop->brand_color ?: '#10b981') }}'">
                                @else
                                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg-soft);color:var(--text-faint)">
                                        <x-icon name="shopping-bag" size="32" />
                                    </div>
                                @endif
                            </a>

                            <div class="product-card-badge-top">Prêt à livrer</div>

                            @if ($relHasDiscount)
                                <span class="product-discount-badge">-{{ $relDiscountPct }}%</span>
                            @endif

                            <div class="product-card-quick-actions">
                                <button type="button" class="btn-card-qv" onclick="openQuickView(this)" title="Aperçu rapide" aria-label="Aperçu rapide de {{ $rel->title }}">
                                    <x-icon name="eye" size="16" />
                                </button>
                                @if ($relWaUrl)
                                    <a href="{{ $relWaUrl }}" target="_blank" class="btn-card-wa" title="Commander sur WhatsApp">
                                        <x-icon name="whatsapp" size="16" />
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="product-card-body">
                            <div class="card-stock-pill">
                                <span class="card-stock-dot"></span> En stock
                            </div>
                            <h3 class="product-card-title">
                                <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $rel->slug) }}">
                                    {{ $rel->title }}
                                </a>
                            </h3>
                            <div class="product-price-box">
                                <span class="product-price">{{ number_format($rel->price, 0, ',', ' ') }} FCFA</span>
                                @if ($relHasDiscount)
                                    <span class="product-old-price">{{ number_format($rel->compare_at_price, 0, ',', ' ') }} FCFA</span>
                                @endif
                            </div>
                            <div style="margin-top:.85rem;display:flex;align-items:center;gap:.45rem">
                                <form class="ajax-add-cart" method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/ajouter') }}" style="flex:1">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $rel->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn btn-add-cart" style="width:100%">
                                        <x-icon name="shopping-cart" size="15" /> + Panier
                                    </button>
                                </form>
                                @if ($relWaUrl)
                                    <a href="{{ $relWaUrl }}" target="_blank" class="btn-card-wa-icon" title="Commander direct sur WhatsApp">
                                        <x-icon name="whatsapp" size="17" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @endif
</div>

<!-- Barre d'achat Mobile Sticky au Défilement -->
<div id="mobile-sticky-bar" class="mobile-sticky-product-bar">
    <div style="display:flex;align-items:center;gap:.6rem;overflow:hidden">
        @if ($firstImg)
            <img src="{{ $firstImg->thumbnail_url ?: $firstImg->url }}" alt="" style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid var(--border);flex-shrink:0">
        @endif
        <div style="line-height:1.2;overflow:hidden">
            <div style="font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);max-width:130px">
                {{ $product->title }}
            </div>
            <div style="font-size:.88rem;font-weight:800;color:var(--store-primary)">
                {{ number_format($product->price, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:.45rem;flex-shrink:0">
        @if (! $isOutOfStock)
            <button type="button" class="btn-store btn-sm" onclick="var f = document.querySelector('.ajax-add-cart'); if(f) f.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));" style="padding:.5rem .85rem;font-size:.82rem;font-weight:700">
                <x-icon name="shopping-cart" size="14" /> Acheter
            </button>
        @endif
        @if ($waUrl)
            <a href="{{ $waUrl }}" target="_blank" class="btn-card-wa-icon" title="Commander par WhatsApp" style="width:36px;height:36px">
                <x-icon name="whatsapp" size="17" />
            </a>
        @endif
    </div>
</div>
@endsection
