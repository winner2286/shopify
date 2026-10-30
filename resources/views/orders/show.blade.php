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

        <div class="header-actions">
            @if ($merchantWaUrl)
                <a href="{{ $merchantWaUrl }}" target="_blank" class="btn btn-ghost btn-sm" style="background:rgba(37,211,102,0.12);color:#25D366;border-color:rgba(37,211,102,0.3);display:inline-flex;align-items:center;gap:.4rem" title="Transférer le récapitulatif par WhatsApp au livreur ou à votre équipe">
                    <x-icon name="whatsapp" size="16" /> Bordereau WhatsApp
                </a>
            @endif
            @if ($customerWaUrl)
                <a href="{{ $customerWaUrl }}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="whatsapp" size="16" /> Écrire au client
                </a>
            @endif
            <button onclick="window.print()" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="printer" size="16" /> Imprimer
            </button>
            <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="arrow-left" size="14" /> Retour aux commandes
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
@endsection
