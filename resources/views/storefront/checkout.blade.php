@extends('storefront.layout')

@section('title', 'Finaliser ma commande - ' . $shop->name)

@section('content')
<div class="container" style="max-width:960px;padding-top:2.5rem;padding-bottom:4rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
        <div>
            <h1 style="font-size:2rem;margin:0">Validation de votre commande</h1>
            <p style="margin:0;color:var(--text-soft)">Aucun compte requis. Renseignez simplement vos informations de livraison (D-05).</p>
        </div>
        <a href="{{ url('/b/' . $shop->subdomain . '/panier') }}" style="font-size:.95rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
            <x-icon name="arrow-left" size="14" /> Modifier le panier
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

    <form method="POST" action="{{ route('storefront.checkout.process', $shop->subdomain) }}">
        @csrf

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem;align-items:start">
            <!-- Colonne 1 : Coordonnées, Livraison & Paiement -->
            <div>
                <!-- Étape 1 : Coordonnées -->
                <div class="card" style="margin-bottom:1.5rem">
                    <h3 style="margin-top:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem">
                        <x-icon name="user" size="20" style="color:var(--store-primary)" />
                        Vos Coordonnées
                    </h3>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                        <div style="grid-column:span 2">
                            <label for="customer_name" class="label">Nom et Prénom *</label>
                            <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name') }}" required placeholder="Ex. Koffi Mensah" class="input">
                        </div>

                        <div>
                            <label for="customer_phone" class="label">Numéro de Téléphone (WhatsApp) *</label>
                            <input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required placeholder="Ex. 90 12 34 56" class="input">
                            <p class="hint">Indispensable pour que le livreur puisse vous joindre.</p>
                        </div>

                        <div>
                            <label for="customer_email" class="label">Email (facultatif)</label>
                            <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" placeholder="koffi@gmail.com" class="input">
                        </div>
                    </div>
                </div>

                <!-- Étape 2 : Zone et Adresse de livraison -->
                <div class="card" style="margin-bottom:1.5rem">
                    <h3 style="margin-top:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem">
                        <x-icon name="truck" size="20" style="color:var(--store-primary)" />
                        Mode & Zone de Livraison
                    </h3>

                    <div style="margin-bottom:1.2rem">
                        <label class="label">Choisissez votre zone :</label>
                        <div style="display:flex;flex-direction:column;gap:.6rem">
                            @foreach ($zones as $zone)
                                @php
                                    $fee = $zone->calculateFee($subtotal);
                                    $checked = (int) old('shipping_zone_id', $zones->first()->id) === $zone->id;
                                @endphp
                                <label style="display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.2rem;background:var(--bg-soft);border:1px solid {{ $checked ? 'var(--store-primary)' : 'var(--border)' }};border-radius:var(--radius-sm);cursor:pointer;transition:border-color .2s" class="zone-option">
                                    <div style="display:flex;align-items:center;gap:.75rem">
                                        <input type="radio" name="shipping_zone_id" value="{{ $zone->id }}" {{ $checked ? 'checked' : '' }} onchange="updateShipping({{ $fee }})" style="accent-color:var(--store-primary);width:18px;height:18px">
                                        <div>
                                            <div style="font-weight:600;font-size:.95rem">{{ $zone->name }}</div>
                                            <div style="font-size:.85rem;color:var(--text-soft)">{{ $zone->delivery_estimate }}</div>
                                        </div>
                                    </div>

                                    <div style="font-weight:700;font-size:1rem;color:{{ $fee === 0 ? 'var(--ok)' : 'var(--text)' }}">
                                        {{ $fee === 0 ? 'Gratuit' : number_format($fee, 0, ',', ' ') . ' FCFA' }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div style="margin-bottom:1rem">
                        <label for="delivery_address" class="label">Quartier & Adresse précise de livraison *</label>
                        <input id="delivery_address" type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Ex. Tokoin Doumasséssé, près de la pharmacie" class="input">
                    </div>

                    <div>
                        <label for="delivery_notes" class="label">Indications pour le livreur (facultatif)</label>
                        <textarea id="delivery_notes" name="delivery_notes" rows="2" placeholder="Ex. Portail bleu, appeler dès votre arrivée" class="input">{{ old('delivery_notes') }}</textarea>
                    </div>
                </div>

                <!-- Étape 3 : Mode de paiement -->
                <div class="card">
                    <h3 style="margin-top:0;font-size:1.2rem;display:flex;align-items:center;gap:.5rem">
                        <x-icon name="credit-card" size="20" style="color:var(--store-primary)" />
                        Mode de Paiement
                    </h3>

                    <div style="display:flex;flex-direction:column;gap:.6rem">
                        <label style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1.2rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="radio" name="payment_method" value="cash_on_delivery" {{ old('payment_method', 'cash_on_delivery') === 'cash_on_delivery' ? 'checked' : '' }} style="accent-color:var(--store-primary);width:18px;height:18px">
                            <div style="flex:1">
                                <div style="font-weight:600;display:flex;align-items:center;gap:.4rem"><x-icon name="wallet" size="16" /> Espèces à la livraison</div>
                                <div style="font-size:.85rem;color:var(--text-soft)">Réglez directement en espèces au livreur lors de la réception de votre colis.</div>
                            </div>
                        </label>

                        <label style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1.2rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="radio" name="payment_method" value="tmoney" {{ old('payment_method') === 'tmoney' ? 'checked' : '' }} style="accent-color:var(--store-primary);width:18px;height:18px">
                            <div style="flex:1">
                                <div style="font-weight:600;display:flex;align-items:center;gap:.4rem"><x-icon name="phone" size="16" /> T-Money (Togocom)</div>
                                <div style="font-size:.85rem;color:var(--text-soft)">Paiement Mobile Money direct et sécurisé.</div>
                            </div>
                        </label>

                        <label style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1.2rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                            <input type="radio" name="payment_method" value="flooz" {{ old('payment_method') === 'flooz' ? 'checked' : '' }} style="accent-color:var(--store-primary);width:18px;height:18px">
                            <div style="flex:1">
                                <div style="font-weight:600;display:flex;align-items:center;gap:.4rem"><x-icon name="phone" size="16" /> Flooz (Moov Africa)</div>
                                <div style="font-size:.85rem;color:var(--text-soft)">Paiement Mobile Money par compte Flooz.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Colonne 2 : Récapitulatif Panier & Validation -->
            <div class="card" style="position:sticky;top:90px">
                <h3 style="margin-top:0;font-size:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
                    Articles commandés ({{ $cartCount }})
                </h3>

                <div style="display:flex;flex-direction:column;gap:.8rem;max-height:280px;overflow-y:auto;margin-bottom:1.2rem;padding-right:.3rem">
                    @foreach ($cart as $it)
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;font-size:.92rem">
                            <div style="display:flex;align-items:center;gap:.6rem">
                                <span style="font-weight:700;background:var(--bg-soft);padding:.2rem .5rem;border-radius:6px;font-size:.85rem">
                                    {{ $it['quantity'] }}x
                                </span>
                                <span style="font-weight:500;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    {{ $it['title'] }}
                                </span>
                            </div>
                            <div style="font-weight:600;font-family:var(--font-display)">
                                {{ number_format($it['price'] * $it['quantity'], 0, ',', ' ') }} FCFA
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="border-top:1px solid var(--border);padding-top:1rem;display:flex;flex-direction:column;gap:.6rem;font-size:.95rem">
                    <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                        <span>Sous-total :</span>
                        <span style="font-weight:600;color:var(--text)">{{ number_format($subtotal, 0, ',', ' ') }} FCFA</span>
                    </div>

                    @php $firstFee = $zones->first() ? $zones->first()->calculateFee($subtotal) : 0; @endphp
                    <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                        <span>Frais de livraison :</span>
                        <span id="shipping-display" style="font-weight:600;color:var(--text)">
                            {{ $firstFee === 0 ? 'Gratuit' : number_format($firstFee, 0, ',', ' ') . ' FCFA' }}
                        </span>
                    </div>

                    <div style="border-top:2px dashed var(--border);padding-top:.9rem;margin-top:.4rem;display:flex;justify-content:space-between;align-items:baseline">
                        <span style="font-size:1.15rem;font-weight:700">Total à payer :</span>
                        <span id="total-display" style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);color:var(--text)">
                            {{ number_format($subtotal + $firstFee, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>

                <div style="margin-top:1.8rem">
                    <button type="submit" class="btn-store btn-block" style="font-size:1.1rem;padding:.95rem 1.5rem;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                        <x-icon name="check" size="18" /> Valider ma commande
                    </button>
                    <p style="font-size:.8rem;color:var(--text-faint);text-align:center;margin:.6rem 0 0;display:flex;align-items:center;justify-content:center;gap:.3rem">
                        <x-icon name="lock" size="12" /> En commandant, vous acceptez les conditions de vente de la boutique.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const subtotal = {{ $subtotal }};
    function updateShipping(fee) {
        const total = subtotal + fee;
        document.getElementById('shipping-display').innerText = fee === 0 ? 'Gratuit' : fee.toLocaleString('fr-FR') + ' FCFA';
        document.getElementById('total-display').innerText = total.toLocaleString('fr-FR') + ' FCFA';
    }
</script>
@endsection
