@extends('storefront.layout')

@section('title', 'Confirmation de commande ' . $order->order_number . ' - ' . $shop->name)

@section('content')
<div class="container" style="max-width:820px;padding-top:2.5rem;padding-bottom:4rem">
    <div class="card" style="text-align:center;padding:2.5rem 1.5rem;margin-bottom:2rem;background:radial-gradient(circle at 50% 0%, var(--store-primary-soft), var(--surface) 70%)">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--ok);color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;box-shadow:0 8px 24px rgba(16,185,129,0.3)">
            <x-icon name="check" size="36" />
        </div>
        <h1 style="font-size:2rem;margin:0 0 .5rem">Merci pour votre commande !</h1>
        <p style="color:var(--text-soft);font-size:1.1rem;margin:0 0 1.2rem">
            Votre commande a bien été enregistrée et transmise à <strong>{{ $shop->name }}</strong>.
        </p>

        <div style="display:inline-flex;align-items:center;gap:.6rem;background:var(--bg-soft);padding:.6rem 1.4rem;border-radius:30px;border:1px solid var(--border)">
            <span style="color:var(--text-soft);font-size:.9rem">Numéro de commande :</span>
            <strong style="font-family:monospace;font-size:1.15rem;color:var(--text)">{{ $order->order_number }}</strong>
        </div>
    </div>

    <!-- Instructions selon le mode de paiement -->
    @if ($order->payment_method === 'cash_on_delivery')
        <div class="card" style="margin-bottom:2rem;border-left:4px solid var(--ok)">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <x-icon name="wallet" size="20" style="color:var(--store-primary)" />
                Paiement à la livraison
            </h3>
            <p style="margin:0;color:var(--text);line-height:1.6">
                Vous avez choisi de régler en espèces à la livraison. Veuillez préparer le montant exact de <strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong> qui sera remis au livreur lors de la réception de votre colis.
            </p>
        </div>
    @elseif (in_array($order->payment_method, ['tmoney', 'flooz'], true))
        <div class="card" style="margin-bottom:2rem;border-left:4px solid var(--store-primary)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;margin-bottom:1rem">
                <h3 style="margin:0;display:flex;align-items:center;gap:.5rem">
                    <x-icon name="phone" size="20" style="color:var(--store-primary)" />
                    Paiement {{ $order->payment_method_label }}
                </h3>
                @if ($order->payment_status === 'paid')
                    <span class="badge badge-ok" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="check" size="14" /> Paiement Validé</span>
                @else
                    <span class="badge badge-warn" id="countdown-badge" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="clock" size="14" /> Expire dans : <span id="timer">14:59</span></span>
                @endif
            </div>

            @if ($order->payment_status === 'paid')
                <div style="color:var(--ok);font-weight:600;font-size:1.05rem;display:flex;align-items:center;gap:.4rem">
                    <x-icon name="check-circle" size="18" /> Votre paiement a été confirmé avec succès par l'opérateur !
                </div>
            @else
                <p style="color:var(--text-soft);margin-bottom:1.2rem;line-height:1.6">
                    Effectuez le transfert du montant de <strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong> vers le numéro marchand de la boutique ({{ $shop->phone ?: '+228 90 00 00 00' }}) en indiquant votre numéro de commande <strong>{{ $order->order_number }}</strong> en référence.
                </p>

                <!-- Bouton de simulation pour tests et démonstration immédiate (CDC F-01) -->
                <form method="POST" action="{{ route('storefront.order.simulate_mobile_money', ['subdomain' => $shop->subdomain, 'orderNumber' => $order->order_number]) }}" style="background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
                    @csrf
                    <div>
                        <div style="font-weight:600;font-size:.92rem">Simulation de paiement (Test direct)</div>
                        <div style="font-size:.82rem;color:var(--text-soft)">Simuler la réception du webhook / signal serveur de l'opérateur</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                        <x-icon name="zap" size="16" /> Simuler la validation du paiement
                    </button>
                </form>
            @endif
        </div>
    @elseif ($order->payment_method === 'feexpay')
        <div class="card" style="margin-bottom:2rem;border-left:4px solid #FF2D6D">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;margin-bottom:1rem">
                <h3 style="margin:0;display:flex;align-items:center;gap:.5rem">
                    <x-icon name="credit-card" size="20" style="color:#FF2D6D" />
                    Paiement en ligne FeexPay (Mobile Money & Carte)
                </h3>
                @if ($order->payment_status === 'paid')
                    <span class="badge badge-ok" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="check" size="14" /> Paiement Validé</span>
                @else
                    <span class="badge badge-warn" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="clock" size="14" /> En attente de règlement</span>
                @endif
            </div>

            @if ($order->payment_status === 'paid')
                <div style="color:var(--ok);font-weight:600;font-size:1.05rem;display:flex;align-items:center;gap:.4rem">
                    <x-icon name="check-circle" size="18" /> Votre paiement de {{ number_format($order->total, 0, ',', ' ') }} FCFA a été validé avec succès par FeexPay !
                </div>
            @else
                <p style="color:var(--text-soft);margin-bottom:1.2rem;line-height:1.6">
                    Cliquez sur le bouton ci-dessous pour payer instantanément par <strong>T-Money, Flooz, MTN, Moov, Orange, Wave ou Carte bancaire</strong> via la passerelle sécurisée FeexPay.
                </p>

                <!-- Bouton FeexPay officiel et conteneur sécurisé -->
                <div style="display:flex;flex-direction:column;align-items:center;gap:.8rem;margin:1.5rem 0">
                    <div id="feexpay-btn-order" style="width:100%;max-width:380px;min-height:50px;display:flex;justify-content:center;align-items:center">
                        <button type="button" id="feexpay-trigger-btn" class="btn btn-primary" style="background:#FF2D6D;border-color:#FF2D6D;color:#fff;width:100%;font-weight:700;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem 1.4rem;border-radius:10px;box-shadow:0 6px 20px rgba(255,45,109,0.3)">
                            <x-icon name="credit-card" size="18" /> Payer {{ number_format($order->total, 0, ',', ' ') }} FCFA via FeexPay
                        </button>
                    </div>
                    <div id="feexpay-kyc-notice" style="display:none;width:100%;max-width:440px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.85rem 1rem;font-size:.84rem;color:#92400e;line-height:1.45;text-align:center">
                        ⏳ <strong>Validation KYC en cours chez FeexPay :</strong> Pour activer les encaissements réels, validez vos informations dans votre tableau de bord FeexPay. Vous pouvez déjà tester le parcours complet avec le bouton de test ci-dessous.
                    </div>
                </div>

                <!-- Simulation de test direct si en mode test/sandbox -->
                <form method="POST" action="{{ route('storefront.order.simulate_mobile_money', ['subdomain' => $shop->subdomain, 'orderNumber' => $order->order_number]) }}" style="background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-top:1.5rem;border:1px dashed var(--border)">
                    @csrf
                    <div>
                        <div style="font-weight:600;font-size:.92rem">Mode Test / Démonstration (Sandbox)</div>
                        <div style="font-size:.82rem;color:var(--text-soft)">Simuler la confirmation automatique de paiement sans débiter de compte</div>
                    </div>
                    <button type="submit" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem;color:#FF2D6D;border-color:rgba(255,45,109,0.3);font-weight:700">
                        <x-icon name="zap" size="16" /> Valider le test FeexPay
                    </button>
                </form>

                <script src="https://api-v2.feexpay.me/feexpay-javascript-sdk/index.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const triggerBtn = document.getElementById('feexpay-trigger-btn');
                        const kycNotice = document.getElementById('feexpay-kyc-notice');

                        if (typeof FeexPayButton !== 'undefined') {
                            try {
                                FeexPayButton.init('feexpay-btn-order', {
                                    id: '{{ config('feexpay.shop_id', '698dd650a7ffb88696f5id86') }}',
                                    amount: {{ (int) $order->total }},
                                    token: '{{ config('feexpay.token', 'test_Hg7Kjl3ZAM63UuIUpuudD9nKuu3ZAM67Kjl3Uuhn') }}',
                                    callback_url: '{{ route('feexpay.callback') }}?custom_id=ORDER:{{ $order->id }}',
                                    mode: '{{ config('feexpay.mode', 'SANDBOX') }}',
                                    custom_id: 'ORDER:{{ $order->id }}',
                                    description: 'Commande #{{ $order->order_number }} sur {{ addslashes($shop->name) }}',
                                    currency: 'XOF',
                                    buttonText: 'Payer {{ number_format($order->total, 0, ',', ' ') }} FCFA via FeexPay 💳',
                                    callback: function(response) {
                                        if (response && (response.status === 'SUCCESSFUL' || response.status === 'SUCCESS')) {
                                            window.location.href = '{{ route('feexpay.callback') }}?custom_id=ORDER:{{ $order->id }}&reference=' + (response.reference || '');
                                        }
                                    }
                                });
                            } catch (e) {
                                console.error('Erreur initialisation FeexPay:', e);
                            }
                        }

                        // Détecter si le compte est en attente de validation KYC
                        setTimeout(() => {
                            const errEl = document.querySelector('.feexpay-error-message');
                            if (errEl) {
                                errEl.style.display = 'none'; // Masquer le message technique brut
                                if (kycNotice) kycNotice.style.display = 'block';
                            }
                        }, 1200);

                        if (triggerBtn) {
                            triggerBtn.addEventListener('click', function() {
                                if (typeof FeexPayButton !== 'undefined' && typeof FeexPayButton.showPaymentModal === 'function') {
                                    FeexPayButton.showPaymentModal();
                                } else {
                                    if (kycNotice) kycNotice.style.display = 'block';
                                }
                            });
                        }
                    });
                </script>
            @endif
        </div>
    @endif

    <!-- Détails de la commande -->
    <div class="card" style="margin-bottom:2rem">
        <h3 style="margin-top:0;font-size:1.2rem;border-bottom:1px solid var(--border);padding-bottom:.8rem">
            Détails de votre commande
        </h3>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
            <div>
                <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.2rem">Destinataire :</div>
                <div style="font-weight:600">{{ $order->customer_name }}</div>
                <div style="color:var(--text-soft);font-size:.9rem">{{ $order->customer_phone }}</div>
            </div>

            <div>
                <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.2rem">Lieu de livraison :</div>
                <div style="font-weight:600">{{ $order->delivery_zone_name }}</div>
                <div style="color:var(--text-soft);font-size:.9rem">{{ $order->delivery_address }}</div>
                @if (preg_match('/https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google\.com|maps\.app\.goo\.gl)[^\s\)]+/', $order->delivery_address, $matches))
                    <div style="margin-top:.4rem">
                        <a href="{{ $matches[0] }}" target="_blank" style="font-size:.82rem;color:var(--brand-1);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:3px">
                            <x-icon name="map-pin" size="13" /> Voir ma position sur Google Maps ↗
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div style="border-top:1px solid var(--border);padding-top:1rem">
            <div style="font-weight:600;font-size:.95rem;margin-bottom:.8rem">Articles :</div>
            <div style="display:flex;flex-direction:column;gap:.6rem;margin-bottom:1.2rem">
                @foreach ($order->items as $it)
                    <div style="display:flex;justify-content:space-between;font-size:.95rem">
                        <span>{{ $it->quantity }}x {{ $it->product_title }}</span>
                        <span style="font-weight:600">{{ number_format($it->total_price, 0, ',', ' ') }} FCFA</span>
                    </div>
                @endforeach
            </div>

            <div style="background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);display:flex;flex-direction:column;gap:.4rem;font-size:.95rem">
                <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                    <span>Sous-total :</span>
                    <span>{{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</span>
                </div>
                <div style="display:flex;justify-content:space-between;color:var(--text-soft)">
                    <span>Livraison :</span>
                    <span>{{ $order->shipping_cost === 0 ? 'Gratuit' : number_format($order->shipping_cost, 0, ',', ' ') . ' FCFA' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:1.2rem;font-weight:700;border-top:1px solid var(--border);padding-top:.5rem;margin-top:.3rem">
                    <span>Total :</span>
                    <span style="color:var(--text)">{{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div style="display:flex;flex-direction:column;gap:.8rem">
        @if ($waUrl)
            <div style="background:rgba(37,211,102,0.1);border:1px solid rgba(37,211,102,0.35);border-radius:var(--radius-md, 12px);padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem">
                <div style="width:40px;height:40px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(37,211,102,0.35)">
                    <x-icon name="whatsapp" size="22" />
                </div>
                <div style="flex:1;font-size:.92rem;line-height:1.45">
                    <strong style="color:var(--text);font-size:1rem;display:block;margin-bottom:.2rem">Finalisez votre commande sur WhatsApp</strong>
                    <span style="color:var(--text-soft)">Votre commande est bien enregistrée ! Cliquez ci-dessous pour envoyer le récapitulatif directement à la boutique sur WhatsApp et convenir de la livraison.</span>
                </div>
            </div>
            <a href="{{ $waUrl }}" target="_blank" class="btn-whatsapp" style="font-size:1.05rem;padding:1rem 1.5rem;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;border-radius:10px;text-decoration:none;font-weight:600;box-shadow:0 6px 20px rgba(37,211,102,0.35)">
                <x-icon name="whatsapp" size="22" /> Envoyer ma commande sur WhatsApp
            </a>
        @endif

        <div style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap">
            <a href="{{ route('storefront.tracking', $shop->subdomain) }}?numero={{ $order->order_number }}&telephone={{ $order->customer_phone }}" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="package" size="16" /> Suivre l'avancement de ma commande
            </a>
            <a href="{{ url('/b/' . $shop->subdomain) }}" class="btn btn-ghost">
                Retour à l'accueil
            </a>
        </div>
    </div>
</div>

<script>
    // Compte à rebours 15 min (CDC F-01)
    let timeLeft = 15 * 60;
    const timerEl = document.getElementById('timer');
    if (timerEl) {
        const interval = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(interval);
                timerEl.innerText = "Expiré";
                return;
            }
            const m = Math.floor(timeLeft / 60);
            const s = timeLeft % 60;
            timerEl.innerText = `${m}:${s < 10 ? '0' : ''}${s}`;
        }, 1000);
    }
</script>
@endsection
