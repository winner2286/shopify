@extends('layouts.app')

@section('title', 'Commande ' . $order->order_number . ' : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1040px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.3rem">
                <h1 style="font-size:1.8rem;margin:0;font-family:monospace">{{ $order->order_number }}</h1>
                <span class="badge {{ $order->status === 'delivered' ? 'badge-ok' : ($order->status === 'cancelled' ? 'badge-draft' : 'badge-warn') }}" style="font-size:.9rem">
                    {{ $order->status_label }}
                </span>
            </div>
            <p style="margin:0;color:var(--text-soft)">Passée le {{ $order->created_at->format('d/m/Y à H:i') }}</p>
        </div>

        <div class="header-actions" style="display:flex;align-items:center;flex-wrap:wrap;gap:.5rem">
            <!-- Option 3 : Dispatch Livreur -->
            <button type="button" onclick="openDispatchModal()" class="btn btn-sm" style="background:linear-gradient(135deg, #f59e0b, #d97706);color:#fff;border:none;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 12px rgba(245,158,11,0.35);font-weight:600">
                <span style="font-size:1.1rem;line-height:1">🛵</span> Transmettre au Livreur
            </button>

            <!-- Option 2 : Reçu / Facture Pro -->
            <a href="{{ route('orders.receipt', $order) }}" class="btn btn-sm" style="background:linear-gradient(135deg, var(--brand-1), var(--brand-2));color:#fff;border:none;display:inline-flex;align-items:center;gap:.4rem;box-shadow:0 4px 12px rgba(109,61,242,0.3);font-weight:600">
                <x-icon name="file-text" size="15" /> Reçu / Facture Pro
            </a>

            @if ($customerWaUrl)
                <a href="{{ $customerWaUrl }}" target="_blank" class="btn btn-sm" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:.4rem;font-weight:600">
                    <x-icon name="whatsapp" size="15" /> Écrire au client
                </a>
            @endif

            @if ($merchantWaUrl)
                <a href="{{ $merchantWaUrl }}" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--text-soft);display:inline-flex;align-items:center;gap:.35rem" title="Voir l'alerte interne de la boutique">
                    <x-icon name="whatsapp" size="15" /> Alerte interne
                </a>
            @endif

            <button onclick="window.print()" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="printer" size="15" /> Imprimer
            </button>
            <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="arrow-left" size="14" /> Retour
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            {{ session('error') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem;align-items:start">
        <!-- Colonne 1 : Articles et Détails de Paiement -->
        <div>
            <!-- Articles -->
            <div class="card" style="margin-bottom:1.5rem;padding:0;overflow:hidden">
                <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);font-weight:600">
                    Articles commandés ({{ $order->items->count() }})
                </div>

                <div style="display:flex;flex-direction:column">
                    @foreach ($order->items as $it)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-bottom:1px solid var(--border)">
                            <div>
                                <div style="font-weight:600">{{ $it->product_title }}</div>
                                <div style="font-size:.85rem;color:var(--text-soft)">
                                    {{ $it->quantity }} x {{ number_format($it->unit_price, 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                            <div style="font-weight:700;font-family:var(--font-display)">
                                {{ number_format($it->total_price, 0, ',', ' ') }} FCFA
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="padding:1.2rem 1.5rem;background:var(--bg-soft);display:flex;flex-direction:column;gap:.4rem;font-size:.95rem">
                    <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                        <span>Sous-total articles :</span>
                        <span>{{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                        <span>Frais de livraison :</span>
                        <span>{{ $order->shipping_cost === 0 ? 'Gratuit' : number_format($order->shipping_cost, 0, ',', ' ') . ' FCFA' }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:1.25rem;font-weight:700;border-top:1px solid var(--border);padding-top:.6rem;margin-top:.4rem">
                        <span>Total commande :</span>
                        <span style="color:var(--text)">{{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>
            </div>

            <!-- Historique d'audit des statuts (CDC E-02) -->
            <div class="card">
                <h3 style="margin-top:0;font-size:1.15rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
                    Historique & Traçabilité (CDC E-02)
                </h3>

                <div style="display:flex;flex-direction:column;gap:1rem">
                    @foreach ($order->statusHistories as $h)
                        <div style="display:flex;gap:.8rem;align-items:start">
                            <span style="width:24px;height:24px;border-radius:50%;background:var(--brand-1);color:#fff;font-size:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                ●
                            </span>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:.92rem">{{ $h->comment }}</div>
                                <div style="font-size:.8rem;color:var(--text-soft)">
                                    {{ $h->created_at->format('d/m/Y à H:i:s') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Colonne 2 : Client, Paiement et Actions -->
        <div>
            <!-- Client & Livraison -->
            <div class="card" style="margin-bottom:1.5rem">
                <h3 style="margin-top:0;font-size:1.15rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
                    Informations Client & Expédition
                </h3>

                <div style="margin-bottom:1rem">
                    <div style="font-size:.85rem;color:var(--text-soft)">Nom du client :</div>
                    <div style="font-weight:700;font-size:1.05rem">{{ $order->customer_name }}</div>
                </div>

                <div style="margin-bottom:1rem">
                    <div style="font-size:.85rem;color:var(--text-soft)">Téléphone :</div>
                    <div style="font-weight:600">{{ $order->customer_phone }}</div>
                </div>

                @if ($order->customer_email)
                    <div style="margin-bottom:1rem">
                        <div style="font-size:.85rem;color:var(--text-soft)">Email :</div>
                        <div>{{ $order->customer_email }}</div>
                    </div>
                @endif

                <div style="margin-bottom:1rem">
                    <div style="font-size:.85rem;color:var(--text-soft)">Zone de livraison :</div>
                    <div style="font-weight:600">{{ $order->delivery_zone_name }}</div>
                </div>

                <div style="margin-bottom:1rem">
                    <div style="font-size:.85rem;color:var(--text-soft)">Adresse précise :</div>
                    <div style="line-height:1.4">{{ $order->delivery_address }}</div>
                    @if (preg_match('/https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google\.com|maps\.app\.goo\.gl)[^\s\)]+/', $order->delivery_address, $matches))
                        <div style="margin-top:.45rem">
                            <a href="{{ $matches[0] }}" target="_blank" class="btn btn-sm" style="background:#2563eb;color:#fff;display:inline-flex;align-items:center;gap:.35rem;text-decoration:none;font-weight:600;padding:.35rem .75rem;border-radius:6px" title="Ouvrir la position GPS dans Google Maps">
                                <x-icon name="map-pin" size="14" /> Ouvrir dans Google Maps (GPS Livreur) ↗
                            </a>
                        </div>
                    @endif
                </div>

                @if ($order->delivery_notes)
                    <div style="background:var(--bg-soft);padding:.8rem;border-radius:var(--radius-sm)">
                        <div style="font-size:.8rem;color:var(--text-soft)">Note pour le livreur :</div>
                        <div style="font-size:.9rem;font-style:italic">« {{ $order->delivery_notes }} »</div>
                    </div>
                @endif
            </div>

            <!-- Alerte WhatsApp Boutique -->
            <div class="card" style="margin-bottom:1.5rem;border-left:4px solid #25D366">
                <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem">
                    <div style="width:28px;height:28px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <x-icon name="whatsapp" size="16" />
                    </div>
                    <h3 style="margin:0;font-size:1.05rem">Notification WhatsApp Boutique</h3>
                </div>
                <p style="font-size:.88rem;color:var(--text-soft);margin-bottom:.8rem;line-height:1.4">
                    Numéro configuré : <strong>{{ $shop->whatsapp ?: ($shop->phone ?: 'Non configuré') }}</strong>
                </p>
                @if ($merchantWaUrl)
                    <a href="{{ $merchantWaUrl }}" target="_blank" class="btn btn-ghost btn-sm btn-block" style="color:#25D366;border-color:rgba(37,211,102,0.3);display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                        <x-icon name="whatsapp" size="15" /> Ouvrir le récapitulatif WhatsApp
                    </a>
                @else
                    <a href="{{ route('shop.settings') }}" class="btn btn-ghost btn-sm btn-block" style="font-size:.85rem">
                        Configurer le WhatsApp de la boutique
                    </a>
                @endif
            </div>

            <!-- Paiement -->
            <div class="card" style="margin-bottom:1.5rem">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h3 style="margin:0;font-size:1.15rem">Paiement</h3>
                    <span class="badge {{ $order->payment_status === 'paid' ? 'badge-ok' : 'badge-draft' }}">
                        {{ $order->payment_status_label }}
                    </span>
                </div>

                <div style="font-size:.95rem;margin-bottom:1rem">
                    Mode : <strong>{{ $order->payment_method_label }}</strong>
                </div>

                @if ($order->payment_status !== 'paid')
                    <form method="POST" action="{{ route('orders.mark_paid', $order) }}" data-confirm="Confirmer l'encaissement du paiement pour cette commande ?">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm btn-block" style="color:var(--ok);display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                            <x-icon name="check" size="16" /> Marquer comme Payé / Encaissé
                        </button>
                    </form>
                @endif
            </div>

            <!-- Option 3 : Expédition & Livreur Moto (Zémidjan) -->
            <div class="card" style="margin-bottom:1.5rem;border-left:4px solid #f59e0b;background:linear-gradient(180deg, color-mix(in srgb, var(--surface) 96%, #f59e0b 4%), var(--surface))">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem">
                    <div style="display:flex;align-items:center;gap:.6rem">
                        <span style="font-size:1.4rem">🛵</span>
                        <h3 style="margin:0;font-size:1.1rem">Mission Livreur Moto</h3>
                    </div>
                    @if ($order->status === 'shipped')
                        <span class="badge badge-warn" style="font-size:.78rem">En cours de livraison</span>
                    @elseif ($order->status === 'delivered')
                        <span class="badge badge-ok" style="font-size:.78rem">Livrée</span>
                    @else
                        <span class="badge" style="background:rgba(245,158,11,0.15);color:#d97706;font-size:.78rem">À expédier</span>
                    @endif
                </div>

                <div style="font-size:.88rem;color:var(--text-soft);margin-bottom:1rem;line-height:1.45">
                    @if ($order->payment_status === 'paid')
                        <span style="color:var(--ok);font-weight:600">✅ Commande payée en ligne :</span> le livreur ne doit rien encaisser au client.
                    @else
                        <span style="color:#d97706;font-weight:700">💵 À encaisser du client :</span> <strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong> à reverser à la boutique.
                    @endif
                </div>

                <div style="display:flex;flex-direction:column;gap:.5rem">
                    <button type="button" onclick="openDispatchModal()" class="btn btn-block" style="background:linear-gradient(135deg, #f59e0b, #d97706);color:#fff;font-weight:600;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 4px 14px rgba(245,158,11,0.35);border:none;cursor:pointer">
                        <span style="font-size:1.15rem">🛵</span> Transmettre la mission au livreur
                    </button>
                    <a href="{{ route('orders.receipt', $order) }}" class="btn btn-ghost btn-sm btn-block" style="display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                        <x-icon name="file-text" size="14" /> Voir le reçu client officiel
                    </a>
                </div>
            </div>

            <!-- Traitement de la Commande (Changement de statut) -->
            @if ($order->status !== 'cancelled')
                <div class="card" style="margin-bottom:1.5rem">
                    <h3 style="margin-top:0;font-size:1.15rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
                        Actions de Traitement
                    </h3>

                    <div style="display:flex;flex-direction:column;gap:.6rem">
                        @if ($order->status === 'pending')
                            <form method="POST" action="{{ route('orders.update_status', $order) }}">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn btn-primary btn-block" style="display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                                    <x-icon name="check" size="16" /> Confirmer la commande
                                </button>
                            </form>
                        @elseif ($order->status === 'confirmed')
                            <form method="POST" action="{{ route('orders.update_status', $order) }}">
                                @csrf
                                <input type="hidden" name="status" value="shipped">
                                <button type="submit" class="btn btn-primary btn-block" style="display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                                    <x-icon name="truck" size="16" /> Marquer comme Expédiée / En livraison
                                </button>
                            </form>
                        @elseif ($order->status === 'shipped')
                            <form method="POST" action="{{ route('orders.update_status', $order) }}">
                                @csrf
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="btn btn-primary btn-block" style="background:var(--ok);display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                                    <x-icon name="check-circle" size="16" /> Marquer comme Livrée
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Annulation de la commande (CDC E-05) -->
            @if ($order->canBeCancelled())
                <div class="card" style="border-color:color-mix(in srgb, var(--danger) 30%, var(--border))">
                    <h4 style="margin-top:0;color:var(--danger)">Annuler la commande (CDC E-05)</h4>
                    <p style="font-size:.85rem;color:var(--text-soft);margin-bottom:1rem">
                        L'annulation est possible avant expédition. Les articles seront <strong>automatiquement restitués au stock</strong>.
                    </p>

                    <form method="POST" action="{{ route('orders.cancel', $order) }}" data-confirm="Êtes-vous sûr de vouloir annuler cette commande ? Les stocks seront réintégrés.">
                        @csrf
                        <div style="margin-bottom:.8rem">
                            <label for="cancellation_reason" class="label" style="font-size:.85rem">Motif d'annulation obligatoire *</label>
                            <input id="cancellation_reason" type="text" name="cancellation_reason" required placeholder="Ex. Demande du client, indisponibilité temporaire" class="input" style="font-size:.9rem">
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm btn-block" style="color:var(--danger)">
                            Annuler et réintégrer le stock
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- MODAL DISPATCH LIVREUR (OPTION 3) -->
<div id="dispatchDriverModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);z-index:9999;overflow-y:auto;padding:1.5rem 1rem;align-items:center;justify-content:center">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;max-width:560px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,0.5);overflow:hidden;animation:modalPop .25s ease-out">
        <!-- En-tête Modal -->
        <div style="padding:1.2rem 1.5rem;background:linear-gradient(135deg, rgba(245,158,11,0.15), rgba(217,119,6,0.05));border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;align-items:center;gap:.6rem">
                <span style="font-size:1.6rem">🛵</span>
                <div>
                    <h3 style="margin:0;font-size:1.15rem;font-weight:700">Mission Livreur (Zémidjan / Moto)</h3>
                    <p style="margin:0;font-size:.8rem;color:var(--text-soft)">Commande #{{ $order->order_number }}</p>
                </div>
            </div>
            <button type="button" onclick="closeDispatchModal()" style="background:none;border:none;color:var(--text-soft);font-size:1.4rem;cursor:pointer;padding:.2rem .4rem;line-height:1">✕</button>
        </div>

        <form id="dispatchDriverForm" method="POST" action="{{ route('orders.dispatch_driver', $order) }}" target="_blank" style="padding:1.5rem">
            @csrf

            <!-- Récapitulatif Rapide -->
            <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:1.2rem;display:flex;flex-direction:column;gap:.6rem;font-size:.88rem">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span style="color:var(--text-soft)">📍 Client :</span>
                    <strong>{{ $order->customer_name }} ({{ $order->customer_phone }})</strong>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span style="color:var(--text-soft)">🏁 Zone & Adresse :</span>
                    <span style="text-align:right"><strong>{{ $order->delivery_zone_name }}</strong> — {{ Str::limit($order->delivery_address, 35) }}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px dashed var(--border);padding-top:.5rem">
                    <span style="color:var(--text-soft)">💰 Règlement :</span>
                    @if ($order->payment_status === 'paid')
                        <span style="color:var(--ok);font-weight:700">DÉJÀ PAYÉ EN LIGNE (0 FCFA à encaisser)</span>
                    @else
                        <span style="color:#d97706;font-weight:800;font-size:.95rem">À ENCAISSER : {{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
                    @endif
                </div>
            </div>

            <!-- Champs Optionnels Livreur -->
            <div style="margin-bottom:1rem">
                <label for="driver_phone" class="label" style="font-size:.85rem;font-weight:600">Numéro WhatsApp du livreur (Optionnel)</label>
                <input type="tel" id="driver_phone" name="driver_phone" placeholder="Ex. 90123456 (laissez vide pour choisir dans WhatsApp)" class="input" style="font-size:.92rem">
                <small style="color:var(--text-soft);font-size:.78rem">Si vide, votre WhatsApp s'ouvrira pour choisir le livreur parmi vos contacts.</small>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1rem">
                <div>
                    <label for="courier_fee" class="label" style="font-size:.85rem;font-weight:600">Frais de course convenus</label>
                    <div style="display:flex;align-items:center;gap:.3rem">
                        <input type="number" id="courier_fee" name="courier_fee" value="{{ $order->shipping_cost }}" class="input" style="font-size:.92rem">
                        <span style="font-size:.85rem;color:var(--text-soft)">FCFA</span>
                    </div>
                </div>
                <div>
                    <label class="label" style="font-size:.85rem;font-weight:600">Mise à jour statut</label>
                    <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem;cursor:pointer;font-size:.85rem">
                        <input type="checkbox" name="mark_shipped" value="1" checked style="width:16px;height:16px;accent-color:#f59e0b">
                        <span>Passer en « Expédiée »</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:1.2rem">
                <label for="driver_notes" class="label" style="font-size:.85rem;font-weight:600">Consigne spécifique pour le livreur (Optionnel)</label>
                <input type="text" id="driver_notes" name="driver_notes" placeholder="Ex. Appeler 10 min avant d'arriver, colis fragile" class="input" style="font-size:.9rem">
            </div>

            <!-- Aperçu pliable du texte de la course -->
            <details style="margin-bottom:1.2rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:.6rem .8rem;font-size:.82rem">
                <summary style="cursor:pointer;font-weight:600;color:var(--text-soft)">👁️ Voir le texte de la mission WhatsApp</summary>
                <pre id="missionRawText" style="white-space:pre-wrap;font-family:monospace;font-size:.78rem;margin-top:.6rem;background:var(--surface);padding:.8rem;border-radius:6px;border:1px solid var(--border);max-height:180px;overflow-y:auto">{{ $deliveryMissionText }}</pre>
            </details>

            <!-- Boutons d'Action -->
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary" style="flex:1.4;background:linear-gradient(135deg, #f59e0b, #d97706);border:none;color:#fff;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.75rem 1rem">
                    <span style="font-size:1.1rem">📲</span> Ouvrir sur WhatsApp
                </button>
                <button type="button" onclick="copyMissionText()" class="btn btn-ghost" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:.35rem">
                    <x-icon name="copy" size="15" /> Copier texte
                </button>
                <button type="button" onclick="closeDispatchModal()" class="btn btn-ghost" style="padding:.75rem 1rem">
                    Fermer
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.94); }
    to { opacity: 1; transform: scale(1); }
}
</style>

<script>
function openDispatchModal() {
    const modal = document.getElementById('dispatchDriverModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeDispatchModal() {
    const modal = document.getElementById('dispatchDriverModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function copyMissionText() {
    const text = document.getElementById('missionRawText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert("Fiche de mission copiée dans le presse-papier ! Vous pouvez la coller dans n'importe quelle conversation.");
    }).catch(() => {
        alert("Impossible de copier automatiquement.");
    });
}

// Fermeture de la modal en cliquant sur l'arrière-plan
document.getElementById('dispatchDriverModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDispatchModal();
    }
});
</script>
@endsection
