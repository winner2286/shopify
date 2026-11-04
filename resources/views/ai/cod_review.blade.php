@extends('layouts.app')

@section('title', 'Radar IA Commandes COD & Relation Client | ' . config('platform.name'))

@section('content')
<div class="container dash cod-review-container">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('orders.index') }}" style="color:var(--brand-1);text-decoration:none">← Retour aux Commandes</a></p>
            <div style="display:flex;align-items:center;gap:.65rem;margin-top:.35rem">
                <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg, #10b981, #059669);display:flex;align-items:center;justify-content:center;color:#fff">
                    <x-icon name="shield" :size="20" />
                </div>
                <div>
                    <h1 style="margin:0;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em">Radar IA Commandes COD & Relation Client</h1>
                    <p style="margin:0;font-size:.85rem;color:var(--text-soft)">Détection prédictive des refus de colis, sécurisation des tournées et réponses WhatsApp 24/7.</p>
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
            <a href="{{ route('ai.assistant') }}" class="btn btn-sm btn-ghost" style="border:1px solid var(--border)">
                <x-icon name="sparkles" :size="14" />
                <span>Ouvrir l'Assistant IA</span>
            </a>
        </div>
    </div>

    <!-- KPI RADAR COD -->
    <div class="ai-cod-kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-top:1.5rem;margin-bottom:1.5rem">
        <div class="card panel" style="padding:1.25rem;border-left:4px solid #10b981">
            <span style="font-size:.8rem;color:var(--text-soft);font-weight:600">Espèces en tournée à encaisser</span>
            <div style="font-size:1.5rem;font-weight:800;color:var(--text);margin-top:.3rem">
                {{ number_format($dashboard['total_cash_to_collect_fcfa'] ?? 0, 0, ',', ' ') }} {{ $dashboard['currency'] ?? 'FCFA' }}
            </div>
            <span style="font-size:.75rem;color:#10b981;font-weight:600">Sur {{ $dashboard['total_active_cod_orders'] ?? 0 }} commande(s) active(s)</span>
        </div>

        <div class="card panel" style="padding:1.25rem;border-left:4px solid #ef4444">
            <span style="font-size:.8rem;color:var(--text-soft);font-weight:600">Commandes à risque élevé d'échec</span>
            <div style="font-size:1.5rem;font-weight:800;color:#ef4444;margin-top:.3rem">
                {{ $dashboard['risk_breakdown']['high_risk'] ?? 0 }}
            </div>
            <span style="font-size:.75rem;color:var(--text-soft)">Confirmation téléphonique ou acompte requis</span>
        </div>

        <div class="card panel" style="padding:1.25rem;border-left:4px solid #f59e0b">
            <span style="font-size:.8rem;color:var(--text-soft);font-weight:600">En attente de confirmation</span>
            <div style="font-size:1.5rem;font-weight:800;color:#f59e0b;margin-top:.3rem">
                {{ $dashboard['pending_confirmation_count'] ?? 0 }}
            </div>
            <span style="font-size:.75rem;color:var(--text-soft)">Colis non encore confiés au coursier</span>
        </div>

        <div class="card panel" style="padding:1.25rem;border-left:4px solid #3b82f6">
            <span style="font-size:.8rem;color:var(--text-soft);font-weight:600">En cours d'acheminement</span>
            <div style="font-size:1.5rem;font-weight:800;color:#3b82f6;margin-top:.3rem">
                {{ $dashboard['shipped_in_transit_count'] ?? 0 }}
            </div>
            <span style="font-size:.75rem;color:var(--text-soft)">Chez les livreurs en ce moment</span>
        </div>
    </div>

    <!-- ONGLETS : 1. RADAR COD & LIVRAISONS | 2. ASSISTANT RELATION CLIENT -->
    <div class="ai-tabs-bar" style="display:flex;gap:.75rem;border-bottom:1px solid var(--border);margin-bottom:1.5rem">
        <button type="button" class="ai-tab-btn active" id="tab-btn-orders" onclick="switchCodTab('orders')">
            <x-icon name="orders" :size="16" />
            <span>Audit & Sécurisation des Commandes COD ({{ count($dashboard['orders'] ?? []) }})</span>
        </button>
        <button type="button" class="ai-tab-btn" id="tab-btn-support" onclick="switchCodTab('support')">
            <x-icon name="message-square" :size="16" />
            <span>Assistant SAV & Réponses WhatsApp 24/7</span>
        </button>
    </div>

    <!-- SECTION 1 : RADAR COMMANDES COD -->
    <section id="section-orders" class="card panel" style="padding:0;overflow:hidden">
        <div style="padding:1rem 1.25rem;background:var(--bg-soft);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
            <strong>Revue des commandes en paiement à la livraison</strong>
            <span style="font-size:.82rem;color:var(--text-soft)">Classées par niveau de vigilance IA</span>
        </div>

        @if(empty($dashboard['orders']))
            <div style="padding:3rem 1.5rem;text-align:center;color:var(--text-soft)">
                <div style="margin-bottom:.5rem;color:#10b981;display:flex;justify-content:center"><x-icon name="check-circle" size="36" /></div>
                <h4 style="margin:0 0 .35rem;color:var(--text)">Aucune commande COD en attente</h4>
                <p style="margin:0;font-size:.85rem">Toutes les livraisons en cours sont finalisées ou encaissées !</p>
            </div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.88rem;text-align:left">
                    <thead>
                        <tr style="background:rgba(255,255,255,0.02);border-bottom:1px solid var(--border)">
                            <th style="padding:.75rem 1rem">Commande</th>
                            <th style="padding:.75rem 1rem">Client & Contact</th>
                            <th style="padding:.75rem 1rem">Lieu de livraison</th>
                            <th style="padding:.75rem 1rem">Niveau de risque IA</th>
                            <th style="padding:.75rem 1rem">Diagnostic & Action recommandée</th>
                            <th style="padding:.75rem 1rem;text-align:right">Action WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dashboard['orders'] as $ord)
                            @php
                                $badgeStyle = match($ord['risk_level']) {
                                    'faible' => 'background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3)',
                                    'modéré' => 'background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3)',
                                    default => 'background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3)',
                                };
                            @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:.85rem 1rem;font-family:monospace;font-weight:700">
                                    <a href="{{ route('orders.show', $ord['order_id']) }}" style="color:var(--brand-1);text-decoration:none">
                                        {{ $ord['order_number'] }}
                                    </a>
                                </td>
                                <td style="padding:.85rem 1rem">
                                    <div style="font-weight:600">{{ $ord['order_id'] ? (\App\Models\Order::find($ord['order_id'])?->customer_name ?? 'Client') : 'Client' }}</div>
                                    <div style="font-size:.78rem;color:var(--text-soft)">{{ $ord['order_id'] ? (\App\Models\Order::find($ord['order_id'])?->customer_phone ?? '') : '' }}</div>
                                </td>
                                <td style="padding:.85rem 1rem;font-size:.82rem">
                                    {{ $ord['order_id'] ? (\App\Models\Order::find($ord['order_id'])?->delivery_zone_name ?? 'Standard') : 'Standard' }}
                                </td>
                                <td style="padding:.85rem 1rem">
                                    <span style="font-size:.75rem;font-weight:700;padding:3px 8px;border-radius:99px;text-transform:uppercase;{{ $badgeStyle }}">
                                        {{ $ord['risk_level'] }} ({{ $ord['risk_score'] }}/100)
                                    </span>
                                </td>
                                <td style="padding:.85rem 1rem;font-size:.82rem;line-height:1.4">
                                    <strong style="color:var(--text)">{{ $ord['recommended_action'] }}</strong>
                                    @if(!empty($ord['risk_factors']))
                                        <div style="color:#ef4444;font-size:.75rem;margin-top:.2rem">
                                            <span style="font-weight:600">Attention :</span> {{ implode(' ', $ord['risk_factors']) }}
                                        </div>
                                    @endif
                                </td>
                                <td style="padding:.85rem 1rem;text-align:right">
                                    @if(!empty($ord['whatsapp_click_url']))
                                        <a href="{{ $ord['whatsapp_click_url'] }}" target="_blank" class="btn btn-sm" style="background:#25D366;color:#fff;border:none;font-weight:700;display:inline-flex;align-items:center;gap:.35rem">
                                            <x-icon name="whatsapp" :size="14" />
                                            <span>Confirmer</span>
                                        </a>
                                    @else
                                        <span style="color:var(--text-soft);font-size:.8rem">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <!-- SECTION 2 : ASSISTANT RELATION CLIENT & SAV 24/7 -->
    <section id="section-support" class="card panel" style="display:none;padding:1.5rem">
        <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
            <x-icon name="message-square" :size="20" />
            <span>Simulateur & Générateur de Réponses SAV WhatsApp 24/7</span>
        </h3>
        <p style="font-size:.88rem;color:var(--text-soft);margin-bottom:1.5rem">
            Collez la question ou la réclamation d'un client : l'IA vérifie instantanément vos stocks réels, vos délais de livraison, vos tarifs et rédige une réponse WhatsApp courtoise prête à être expédiée.
        </p>

        <form id="ai-support-form" onsubmit="handleSupportSubmit(event)">
            <div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem">
                <div>
                    <div class="field">
                        <label for="client_question">Message ou question reçue du client <span style="color:var(--danger)">*</span></label>
                        <textarea id="client_question" class="input" rows="3" placeholder="Ex: Bonjour, je voudrais savoir si la montre connectée est encore disponible et combien coûte la livraison à Agoè ? J'ai aussi la commande CMD-2026-0001." required></textarea>
                    </div>
                </div>
                <div>
                    <div class="field">
                        <label for="client_phone">Téléphone WhatsApp du client (optionnel)</label>
                        <input type="text" id="client_phone" class="input" placeholder="Ex: +228 90 00 00 00">
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-support-submit" style="width:100%;padding:.75rem;margin-top:.35rem;display:inline-flex;align-items:center;justify-content:center;gap:.35rem">
                        <x-icon name="sparkles" size="16" /> <span>Générer la réponse idéale</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Résultat généré -->
        <div id="support-result-box" style="display:none;margin-top:1.5rem;padding:1.25rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <span id="support-intent-badge" class="badge badge-ok" style="font-size:.78rem">Intention détectée</span>
                <span id="support-escalation-alert" style="display:none;color:#ef4444;font-size:.8rem;font-weight:700">
                    Transfert vers conseiller humain recommandé
                </span>
            </div>

            <div class="field" style="margin-bottom:1rem">
                <label style="font-weight:600">Message WhatsApp préparé :</label>
                <div id="support-reply-text" style="background:rgba(0,0,0,0.3);padding:1rem;border-radius:8px;font-size:.92rem;line-height:1.5;white-space:pre-wrap"></div>
            </div>

            <div style="display:flex;gap:.75rem;align-items:center">
                <button type="button" class="btn btn-sm" onclick="copySupportReply()" style="background:rgba(255,255,255,0.1);color:#fff">
                    Copier le texte
                </button>
                <a id="support-wa-btn" href="#" target="_blank" class="btn btn-sm" style="display:none;background:#25D366;color:#fff;font-weight:700">
                    <x-icon name="whatsapp" :size="15" />
                    <span>Envoyer directement au client</span>
                </a>
            </div>
        </div>
    </section>
</div>

<style>
.ai-tab-btn {
    background: transparent;
    border: none;
    padding: 0.65rem 1.25rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-soft, #a1a1aa);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.ai-tab-btn:hover {
    color: var(--text, #fff);
}

.ai-tab-btn.active {
    color: #10b981;
    border-bottom-color: #10b981;
}

@media (max-width: 768px) {
    .cod-review-container {
        padding-bottom: 85px !important;
    }
    .ai-tabs-bar {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .ai-tabs-bar::-webkit-scrollbar {
        display: none;
    }
}
</style>

<script>
function switchCodTab(tab) {
    document.getElementById('tab-btn-orders').classList.toggle('active', tab === 'orders');
    document.getElementById('tab-btn-support').classList.toggle('active', tab === 'support');

    document.getElementById('section-orders').style.display = tab === 'orders' ? 'block' : 'none';
    document.getElementById('section-support').style.display = tab === 'support' ? 'block' : 'none';
}

async function handleSupportSubmit(e) {
    e.preventDefault();

    const question = document.getElementById('client_question').value.trim();
    const phone = document.getElementById('client_phone').value.trim();
    if (!question) return;

    const btn = document.getElementById('btn-support-submit');
    btn.disabled = true;
    btn.innerText = 'Rédaction en cours...';

    try {
        const res = await fetch('{{ route('ai.customer.answer') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question, phone })
        });

        const data = await res.json();

        if (data.status === 'success' || data.reply_message) {
            document.getElementById('support-reply-text').innerText = data.reply_message;
            document.getElementById('support-intent-badge').innerText = `Intention : ${data.intent || 'Question générale'}`;
            
            const alertEl = document.getElementById('support-escalation-alert');
            if (data.requires_human) {
                alertEl.style.display = 'inline';
            } else {
                alertEl.style.display = 'none';
            }

            const waBtn = document.getElementById('support-wa-btn');
            if (data.whatsapp_click_url) {
                waBtn.href = data.whatsapp_click_url;
                waBtn.style.display = 'inline-flex';
            } else {
                waBtn.style.display = 'none';
            }

            document.getElementById('support-result-box').style.display = 'block';
        }
    } catch (err) {
        console.error(err);
        alert('Erreur lors de la génération de la réponse.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Générer la réponse idéale';
    }
}

function copySupportReply() {
    const text = document.getElementById('support-reply-text').innerText;
    navigator.clipboard.writeText(text);
    alert('Réponse copiée dans le presse-papier !');
}
</script>
@endsection
