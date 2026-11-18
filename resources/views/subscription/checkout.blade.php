@extends('layouts.app')

@section('title', 'Souscription au forfait ' . $plan->name . ' - ' . config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding-top:2.5rem;padding-bottom:4rem">
    <div style="margin-bottom:1.5rem">
        <a href="{{ route('dashboard') }}" style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft);text-decoration:none;font-size:.9rem;font-weight:600">
            <x-icon name="arrow-left" :size="16" /> Retour au tableau de bord
        </a>
    </div>

    <!-- En-tête de la souscription -->
    <div class="card" style="padding:2rem;margin-bottom:1.8rem;background:radial-gradient(circle at 50% 0%, rgba(255,45,109,0.08), var(--surface) 75%);border:1px solid var(--border);border-radius:16px;text-align:center">
        <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,45,109,0.12);color:#FF2D6D;padding:.4rem 1rem;border-radius:20px;font-size:.85rem;font-weight:700;margin-bottom:1rem">
            <x-icon name="sparkles" :size="16" /> Formule Commerçant Zanika
        </div>
        <h1 style="font-size:2rem;margin:0 0 .5rem">Forfait {{ $plan->name }}</h1>
        <p style="color:var(--text-soft);font-size:1.05rem;max-width:540px;margin:0 auto 1.5rem">
            Choisissez les fonctionnalités et quotas du forfait <strong>{{ $plan->name }}</strong> pour votre compte. La boutique sélectionnée est <strong>{{ $shop->name }}</strong>.
        </p>

        <div style="display:inline-flex;align-items:baseline;gap:.4rem;background:var(--bg-soft);padding:.8rem 1.6rem;border-radius:14px;border:1px solid var(--border)">
            <span style="font-size:2.4rem;font-weight:800;color:var(--text);letter-spacing:-1px">
                {{ number_format($monthlyPrice, 0, ',', ' ') }}
            </span>
            <span style="color:var(--text-soft);font-size:1rem;font-weight:600">FCFA / mois</span>
        </div>
    </div>

    <!-- Détail des avantages inclus -->
    <div class="card" style="padding:1.8rem;margin-bottom:1.8rem;border-radius:16px">
        <h3 style="margin-top:0;font-size:1.15rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem">
            <x-icon name="check-circle" :size="18" style="color:var(--ok)" /> Ce qui est inclus dans {{ $plan->name }}
        </h3>

        @include('subscription.plan-allowances', ['plan' => $plan])
        @include('subscription.plan-benefits', ['plan' => $plan])
        @include('subscription.plan-conditions')
    </div>

    <!-- Passerelle de paiement GeniusPay -->
    <div class="card" style="padding:1.8rem;border-radius:16px;border-left:4px solid #FF2D6D">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;margin-bottom:1rem">
            <h3 style="margin:0;display:flex;align-items:center;gap:.5rem">
                <x-icon name="credit-card" :size="20" style="color:#FF2D6D" />
                Paiement Sécurisé via GeniusPay
            </h3>
            <span class="badge" style="background:rgba(255,45,109,0.1);color:#FF2D6D;font-weight:700;padding:.25rem .6rem">
                {{ config('feexpay.mode', 'SANDBOX') === 'LIVE' ? 'Production' : 'Sandbox (Mode Test)' }}
            </span>
        </div>

        <p style="color:var(--text-soft);font-size:.95rem;line-height:1.55;margin-bottom:1.4rem">
            GeniusPay affiche les moyens de paiement et les éventuels frais disponibles pour votre transaction.
        </p>

        <!-- Conteneur SDK GeniusPay -->
        @php $canLaunchOnlinePayment = filled(config('feexpay.shop_id')) && filled(config('feexpay.token')) && filled(config('feexpay.sdk_token')); @endphp
        @if ($canLaunchOnlinePayment)
            <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;margin:1.8rem 0">
                <div id="feexpay-btn-sub" style="width:100%;max-width:380px;min-height:50px;display:flex;align-items:center;justify-content:center">
                    <button type="button" id="feexpay-trigger-sub-btn" class="btn btn-primary" style="background:#FF2D6D;border-color:#FF2D6D;color:#fff;width:100%;font-weight:700;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem 1.4rem;border-radius:10px;box-shadow:0 6px 20px rgba(255,45,109,0.3)">
                        <x-icon name="credit-card" :size="18" /> Payer {{ number_format($monthlyPrice, 0, ',', ' ') }} FCFA via GeniusPay
                    </button>
                </div>
                <p id="feexpay-sub-payment-notice" role="status" hidden style="width:100%;max-width:440px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.85rem 1rem;font-size:.84rem;color:#92400e;line-height:1.45;text-align:center">
                    GeniusPay n’a pas pu ouvrir le paiement. Vous pouvez réessayer plus tard depuis votre tableau de bord.
                </p>
            </div>
        @else
            <p class="plan-conditions">Le paiement en ligne de cet abonnement est actuellement indisponible.</p>
        @endif

        <!-- Simulation pour démonstration et tests locaux Sandbox uniquement -->
        @if (app()->environment('local', 'testing') && config('feexpay.mode', 'SANDBOX') !== 'LIVE')
        <form method="POST" action="{{ route('subscription.simulate', [$subscription->id, $plan->id]) }}" style="background:var(--bg-soft);padding:1.1rem;border-radius:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;border:1px dashed var(--border);margin-top:1.5rem">
            @csrf
            <div>
                <div style="font-weight:700;font-size:.92rem">Mode Test / Démonstration Sandbox</div>
                <div style="font-size:.82rem;color:var(--text-soft)">Simuler la validation du paiement GeniusPay pour activer immédiatement le forfait</div>
            </div>
            <button type="submit" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem;color:#FF2D6D;border-color:rgba(255,45,109,0.4);font-weight:700">
                <x-icon name="zap" :size="15" /> Simuler l'activation (Test)
            </button>
        </form>
        @endif
    </div>
</div>

@if ($canLaunchOnlinePayment)
<script src="https://api-v2.feexpay.me/feexpay-javascript-sdk/index.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const triggerBtn = document.getElementById('feexpay-trigger-sub-btn');
        const paymentNotice = document.getElementById('feexpay-sub-payment-notice');
        const feexShopId = @json(config('feexpay.shop_id'));
        const feexToken = @json(config('feexpay.sdk_token'));

        if (typeof FeexPayButton !== 'undefined' && feexShopId && feexToken) {
            try {
                FeexPayButton.init('feexpay-btn-sub', {
                    id: feexShopId,
                    amount: {{ (int) $monthlyPrice }},
                    token: feexToken,
                    callback_url: '{{ route('geniuspay.callback') }}?custom_id=SUB:{{ $subscription->id }}:{{ $plan->id }}',
                    mode: '{{ config('feexpay.mode', 'SANDBOX') }}',
                    custom_id: 'SUB:{{ $subscription->id }}:{{ $plan->id }}',
                    description: @json('Abonnement '.$plan->name.' Zanika ('.$monthlyPrice.' FCFA)'),
                    currency: 'XOF',
                    buttonText: 'Payer {{ number_format($monthlyPrice, 0, ',', ' ') }} FCFA via GeniusPay',
                    callback: function(response) {
                        if (response && (response.status === 'SUCCESSFUL' || response.status === 'SUCCESS')) {
                            window.location.href = '{{ route('geniuspay.callback') }}?custom_id=SUB:{{ $subscription->id }}:{{ $plan->id }}&reference=' + (response.reference || '');
                        }
                    }
                });
            } catch (e) {
                console.error('Erreur init GeniusPay subscription:', e);
            }
        }

        setTimeout(() => {
            const errEl = document.querySelector('#feexpay-btn-sub .feexpay-error-message');
            if (errEl) {
                errEl.style.display = 'none';
                if (paymentNotice) paymentNotice.hidden = false;
            }
        }, 1200);

        if (triggerBtn) {
            triggerBtn.addEventListener('click', function() {
                if (typeof FeexPayButton !== 'undefined' && typeof FeexPayButton.showPaymentModal === 'function') {
                    FeexPayButton.showPaymentModal();
                } else {
                    if (paymentNotice) paymentNotice.hidden = false;
                }
            });
        }
    });
</script>
@endif
@endsection
