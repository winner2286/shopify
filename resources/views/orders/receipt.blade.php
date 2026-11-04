<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu #REC-{{ $order->order_number }} — {{ $shop->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <!-- Librairie html2canvas pour la génération d'image PNG 1-clic -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>

    <style>
        :root {
            --brand: {{ $shop->brand_color ?: '#6d3df2' }};
            --brand-dark: color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 75%, #000);
            --brand-soft: color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 10%, #fff);
            --ok: #16a34a;
            --ok-soft: #dcfce7;
            --warn: #d97706;
            --warn-soft: #fef3c7;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-title: 'Outfit', var(--font-main);
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background: #0f1422;
            color: #1e293b;
            min-height: 100vh;
            padding: 1.5rem 1rem 3rem;
            -webkit-font-smoothing: antialiased;
        }

        /* Barre d'action supérieure (non imprimée) */
        .receipt-action-bar {
            max-width: 820px;
            margin: 0 auto 1.5rem;
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: .9rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .8rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
        }

        .receipt-bar-title {
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .receipt-bar-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .btn-act {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .9rem;
            border-radius: 10px;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s ease;
            border: none;
        }

        .btn-act-primary {
            background: #25D366;
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.35);
        }
        .btn-act-primary:hover {
            transform: translateY(-1px);
            background: #20bd5a;
        }

        .btn-act-dark {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .btn-act-dark:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-act-brand {
            background: var(--brand);
            color: #fff;
        }
        .btn-act-brand:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        /* Cadre du reçu imprimable */
        .receipt-wrapper {
            max-width: 820px;
            margin: 0 auto;
            position: relative;
        }

        .receipt-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45);
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        /* Liseré haut coloré */
        .receipt-top-ribbon {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--brand), #ec4899, #3b82f6);
        }

        /* En-tête */
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1.6rem;
            margin-bottom: 1.6rem;
        }

        .receipt-shop {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .receipt-logo {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background: var(--brand-soft);
            border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent);
            color: var(--brand);
            font-size: 1.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .receipt-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .shop-name {
            font-family: var(--font-title);
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: .2rem;
        }

        .shop-meta {
            font-size: .84rem;
            color: #64748b;
            line-height: 1.45;
        }

        .receipt-doc-meta {
            text-align: right;
        }

        .receipt-type-badge {
            display: inline-block;
            background: var(--brand-soft);
            color: var(--brand);
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: .35rem .75rem;
            border-radius: 6px;
            margin-bottom: .4rem;
        }

        .receipt-num {
            font-family: var(--font-mono);
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .2rem;
        }

        .receipt-date {
            font-size: .84rem;
            color: #64748b;
        }

        /* Cartes d'infos client et commande */
        .receipt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.8rem;
        }

        .info-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.2rem;
        }

        .panel-heading {
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin-bottom: .6rem;
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .panel-val-strong {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .25rem;
        }

        .panel-val-sub {
            font-size: .85rem;
            color: #475569;
            line-height: 1.45;
        }

        /* Tableau des articles */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.8rem;
        }

        .receipt-table th {
            text-align: left;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            background: #f1f5f9;
            padding: .85rem 1rem;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .receipt-table th:last-child,
        .receipt-table td:last-child {
            text-align: right;
        }

        .receipt-table th:nth-child(2),
        .receipt-table td:nth-child(2) {
            text-align: center;
        }

        .receipt-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: .92rem;
            color: #1e293b;
        }

        .item-name {
            font-weight: 700;
            color: #0f172a;
        }
        .item-ref {
            font-size: .78rem;
            color: #94a3b8;
            font-family: var(--font-mono);
            margin-top: .15rem;
        }

        /* Totaux et Cachet */
        .receipt-bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 2rem;
            align-items: start;
            margin-bottom: 1.8rem;
            padding-top: .5rem;
        }

        .receipt-totals {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.2rem;
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .tot-row {
            display: flex;
            justify-content: space-between;
            font-size: .9rem;
            color: #64748b;
        }

        .tot-row.grand-total {
            border-top: 2px dashed #cbd5e1;
            padding-top: .8rem;
            margin-top: .2rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            font-family: var(--font-title);
        }

        .tot-row.grand-total .val {
            color: var(--brand);
        }

        /* Cachet Électronique / Sceau */
        .receipt-seal-box {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .official-seal {
            border: 2px dashed {{ $order->payment_status === 'paid' ? '#16a34a' : '#d97706' }};
            background: {{ $order->payment_status === 'paid' ? 'rgba(22, 163, 74, 0.05)' : 'rgba(217, 119, 6, 0.05)' }};
            border-radius: 14px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: .9rem;
            position: relative;
        }

        .seal-stamp {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid {{ $order->payment_status === 'paid' ? '#16a34a' : '#d97706' }};
            color: {{ $order->payment_status === 'paid' ? '#16a34a' : '#d97706' }};
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: .62rem;
            line-height: 1.1;
            text-align: center;
            text-transform: uppercase;
            flex-shrink: 0;
            transform: rotate(-6deg);
        }

        .seal-stamp svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
        }

        .seal-text {
            font-size: .82rem;
            line-height: 1.4;
        }

        .seal-title {
            font-weight: 800;
            color: {{ $order->payment_status === 'paid' ? '#16a34a' : '#d97706' }};
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .15rem;
        }

        .seal-desc {
            color: #64748b;
            font-size: .78rem;
        }

        /* QR Code et vérification en ligne */
        .receipt-qr-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: .9rem 1.1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .receipt-qr-img {
            width: 72px;
            height: 72px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 4px;
            background: #fff;
            flex-shrink: 0;
        }

        .receipt-qr-text {
            font-size: .78rem;
            color: #64748b;
            line-height: 1.4;
        }

        .receipt-qr-text strong {
            color: #0f172a;
        }

        /* Pied de page */
        .receipt-footer {
            border-top: 1px solid #f1f5f9;
            padding-top: 1.4rem;
            text-align: center;
            font-size: .8rem;
            color: #94a3b8;
            line-height: 1.6;
        }

        .receipt-footer-brand {
            font-weight: 700;
            color: #64748b;
        }

        /* Toast notification */
        .receipt-toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(30px);
            background: #0f172a;
            color: #fff;
            padding: .75rem 1.4rem;
            border-radius: 30px;
            font-size: .9rem;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            opacity: 0;
            transition: all .25s ease;
            pointer-events: none;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .receipt-toast.active {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* Responsive Mobile */
        @media (max-width: 680px) {
            body { padding: .75rem .5rem 2rem; }
            .receipt-card { padding: 1.5rem 1rem; border-radius: 14px; }
            .receipt-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .receipt-doc-meta { text-align: left; }
            .receipt-info-grid { grid-template-columns: 1fr; }
            .receipt-bottom-grid { grid-template-columns: 1fr; gap: 1.25rem; }
            .receipt-action-bar { flex-direction: column; align-items: stretch; }
            .receipt-bar-actions { justify-content: stretch; }
            .receipt-bar-actions .btn-act { flex: 1; justify-content: center; }
        }

        /* Mode d'impression */
        @media print {
            body {
                background: #fff;
                padding: 0;
                color: #000;
            }
            .receipt-action-bar,
            .receipt-toast {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none;
                border: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
            .receipt-top-ribbon {
                display: none;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- Barre d'actions supérieure (Écran uniquement) -->
    <div class="receipt-action-bar">
        <div class="receipt-bar-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--brand)">
                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
            </svg>
            <span>Reçu officiel #REC-{{ $order->order_number }}</span>
        </div>

        <div class="receipt-bar-actions">
            <button onclick="window.print()" class="btn-act btn-act-dark" title="Imprimer ou enregistrer au format PDF">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect width="12" height="8" x="6" y="14"/>
                </svg>
                Imprimer / PDF
            </button>

            <button onclick="downloadAsImage()" id="btnDownloadImage" class="btn-act btn-act-dark" title="Télécharger l'image PNG haute définition pour l'envoyer sur WhatsApp ou l'archiver">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" x2="12" y1="15" y2="3"/>
                </svg>
                <span id="btnDownloadText">Télécharger Image (PNG)</span>
            </button>

            @if (!empty($customerInvoiceWaUrl))
                <a href="{{ $customerInvoiceWaUrl }}" target="_blank" class="btn-act btn-act-primary" title="Transmettre ce reçu directement au client sur WhatsApp">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.888 9.884"/>
                    </svg>
                    Partager WhatsApp
                </a>
            @endif

            <button onclick="copyReceiptLink()" class="btn-act btn-act-dark" title="Copier le lien public du reçu">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                </svg>
                Copier le lien
            </button>

            @if ($isMerchant)
                <a href="{{ route('orders.show', $order) }}" class="btn-act btn-act-brand" style="margin-left:auto">
                    ← Retour commande
                </a>
            @endif
        </div>
    </div>

    <!-- DOCUMENT DU REÇU / FACTURE OFFICIELLE -->
    <div class="receipt-wrapper">
        <div id="receipt-card" class="receipt-card">
            <div class="receipt-top-ribbon"></div>

            <!-- En-tête -->
            <div class="receipt-header">
                <div class="receipt-shop">
                    <div class="receipt-logo">
                        @if ($shop->logo_path)
                            <img src="{{ asset('storage/' . $shop->logo_path) }}" alt="{{ $shop->name }}">
                        @elseif (!empty($shop->onboarding_data['chosen_logo']['svg']))
                            {!! $shop->onboarding_data['chosen_logo']['svg'] !!}
                        @else
                            {{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div class="shop-name">{{ $shop->name }}</div>
                        <div class="shop-meta">
                            <div>{{ $shop->address ?: 'Boutique certifiée' }}{{ $shop->city ? ' — ' . $shop->city : '' }}</div>
                            <div>Tél : {{ $shop->phone ?: ($shop->whatsapp ?: 'N/A') }}</div>
                            @if ($shop->email_contact)
                                <div>Email : {{ $shop->email_contact }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="receipt-doc-meta">
                    <div class="receipt-type-badge">
                        {{ $order->payment_status === 'paid' ? 'REÇU DE PAIEMENT ACQUITTÉ' : 'FACTURE COMMERCIALE' }}
                    </div>
                    <div class="receipt-num">#REC-{{ $order->order_number }}</div>
                    <div class="receipt-date">
                        Date : {{ $order->created_at ? $order->created_at->format('d/m/Y à H:i') : date('d/m/Y à H:i') }}
                    </div>
                </div>
            </div>

            <!-- Grille d'informations -->
            <div class="receipt-info-grid">
                <!-- Destinataire -->
                <div class="info-panel">
                    <div class="panel-heading">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Facturé et Livré à :
                    </div>
                    <div class="panel-val-strong">{{ $order->customer_name }}</div>
                    <div class="panel-val-sub">
                        <div><strong>Téléphone :</strong> {{ $order->customer_phone }}</div>
                        <div><strong>Zone :</strong> {{ $order->delivery_zone_name }}</div>
                        <div><strong>Adresse :</strong> {{ $order->delivery_address }}</div>
                    </div>
                </div>

                <!-- Modalités de règlement -->
                <div class="info-panel">
                    <div class="panel-heading">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>
                        </svg>
                        Modalités de Paiement :
                    </div>
                    <div class="panel-val-strong">{{ $order->payment_method_label }}</div>
                    <div class="panel-val-sub">
                        <div>
                            <strong>Statut :</strong>
                            <span style="font-weight:700;color:{{ $order->payment_status === 'paid' ? '#16a34a' : '#d97706' }}">
                                ● {{ $order->payment_status_label }}
                            </span>
                        </div>
                        @if ($order->paid_at)
                            <div><strong>Encaissé le :</strong> {{ $order->paid_at->format('d/m/Y à H:i') }}</div>
                        @endif
                        <div><strong>Commande N° :</strong> {{ $order->order_number }}</div>
                    </div>
                </div>
            </div>

            <!-- Tableau des articles -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th style="width:55%">Désignation</th>
                        <th style="width:15%">Quantité</th>
                        <th style="width:15%">Prix unitaire</th>
                        <th style="width:15%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                <div class="item-name">{{ $item->product_title }}</div>
                                @if ($item->product && $item->product->sku)
                                    <div class="item-ref">REF: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                            <td style="font-weight:700">{{ number_format($item->total_price, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Section basse : Totaux & Sceau Officiel -->
            <div class="receipt-bottom-grid">
                <!-- Sceau et vérification QR -->
                <div class="receipt-seal-box">
                    <div class="official-seal">
                        <div class="seal-stamp">
                            @if ($order->payment_status === 'paid')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>PAYÉ</span>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                                <span>À LIVRER</span>
                            @endif
                        </div>
                        <div class="seal-text">
                            <div class="seal-title">
                                {{ $order->payment_status === 'paid' ? 'Certifié Acquitté & Conforme' : 'Paiement à la Livraison' }}
                            </div>
                            <div class="seal-desc">
                                Document électronique émis par <strong>{{ $shop->name }}</strong>. Fait foi de bon d'achat et justificatif de transaction commerciale.
                            </div>
                        </div>
                    </div>

                    <!-- QR Code de vérification publique en ligne -->
                    <div class="receipt-qr-section">
                        @php
                            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($publicReceiptUrl);
                        @endphp
                        <img src="{{ $qrUrl }}" alt="QR Code Reçu" class="receipt-qr-img" loading="lazy">
                        <div class="receipt-qr-text">
                            <div><strong>Vérification d'authenticité :</strong></div>
                            Scannez ce QR Code avec votre téléphone ou appareil photo pour vérifier l'authenticité de cette facture en ligne sur la plateforme Zanika.
                        </div>
                    </div>
                </div>

                <!-- Récapitulatif financier -->
                <div class="receipt-totals">
                    <div class="tot-row">
                        <span>Sous-total articles :</span>
                        <span>{{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div class="tot-row">
                        <span>Frais de livraison :</span>
                        <span>{{ $order->shipping_cost === 0 ? 'Offerte (0 FCFA)' : number_format($order->shipping_cost, 0, ',', ' ') . ' FCFA' }}</span>
                    </div>
                    <div class="tot-row grand-total">
                        <span>TOTAL NET :</span>
                        <span class="val">{{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>
            </div>

            <!-- Pied de page officiel -->
            <div class="receipt-footer">
                <div>Merci de votre confiance et pour votre achat chez <span class="receipt-footer-brand">{{ $shop->name }}</span> !</div>
                <div style="font-size:.74rem;margin-top:.4rem;color:#94a3b8">
                    Plateforme de commerce propulsée par <strong>Zanika</strong> • Reçu certifié #REC-{{ $order->order_number }}
                </div>
            </div>
        </div>
    </div>

    <!-- Toast de copie -->
    <div id="receiptToast" class="receipt-toast">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="toastMessage">Lien copié dans le presse-papier !</span>
    </div>

    <script>
        function showToast(msg) {
            const toast = document.getElementById('receiptToast');
            const toastMsg = document.getElementById('toastMessage');
            toastMsg.innerText = msg;
            toast.classList.add('active');
            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }

        function copyReceiptLink() {
            const url = "{{ $publicReceiptUrl }}";
            navigator.clipboard.writeText(url).then(() => {
                showToast("Lien du reçu copié ! Vous pouvez le coller sur WhatsApp.");
            }).catch(() => {
                showToast("Impossible de copier le lien.");
            });
        }

        // Téléchargement de l'image PNG haute résolution
        async function downloadAsImage() {
            const btn = document.getElementById('btnDownloadImage');
            const btnText = document.getElementById('btnDownloadText');
            const receiptCard = document.getElementById('receipt-card');

            if (typeof html2canvas === 'undefined') {
                showToast("Chargement de l'outil de capture en cours...");
                return;
            }

            btn.disabled = true;
            btnText.innerText = "Génération de l'image...";

            try {
                const canvas = await html2canvas(receiptCard, {
                    scale: 2, // Double résolution pour un rendu ultra net
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff'
                });

                const link = document.createElement('a');
                link.download = "Recu-{{ $order->order_number }}.png";
                link.href = canvas.toDataURL('image/png');
                link.click();

                showToast("Reçu téléchargé avec succès en PNG !");
            } catch (err) {
                console.error("Erreur capture reçu :", err);
                showToast("Erreur lors de la capture, veuillez utiliser Imprimer / PDF.");
            } finally {
                btn.disabled = false;
                btnText.innerText = "Télécharger Image (PNG)";
            }
        }
    </script>
</body>
</html>
