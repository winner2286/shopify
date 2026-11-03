@extends('layouts.app')

@section('title', 'Studio Marketing IA : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1160px;padding:2rem 1rem">
    
    <!-- En-tête -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.5rem;margin-bottom:2rem">
        <div>
            <div style="display:inline-flex;align-items:center;gap:.5rem;padding:.3rem .8rem;border-radius:999px;background:linear-gradient(135deg, rgba(236,72,153,0.15), rgba(168,85,247,0.15));border:1px solid rgba(236,72,153,0.3);color:var(--brand-2);font-size:.8rem;font-weight:600;margin-bottom:.6rem">
                <x-icon name="sparkles" size="14" /> MODULE 9 • GÉNÉRATEUR MARKETING MULTI-CANAL
            </div>
            <h1 style="font-size:1.85rem;margin:0;font-family:var(--font-display)">Studio de Rédaction Publicitaire & Marketing IA</h1>
            <p style="margin:.4rem 0 0 0;color:var(--text-soft);max-width:680px;font-size:.95rem">
                Générez en quelques secondes des textes publicitaires à fort impact pour Facebook/Instagram Ads, des diffusions WhatsApp vendeuses, des SMS et des offres de déstockage.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('ai.automations') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="zap" size="16" /> Automatisations IA
            </a>
            <a href="{{ route('ai.studio') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="image" size="16" /> Studio Visuel IA
            </a>
            <a href="{{ route('ai.assistant') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="message-square" size="16" /> Copilote IA
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:360px 1fr;gap:2rem;align-items:start">
        
        <!-- FORMULAIRE DE PARAMÉTRAGE -->
        <div class="card" style="padding:1.6rem">
            <h2 style="font-size:1.15rem;margin:0 0 1.2rem 0;display:flex;align-items:center;gap:.5rem">
                <x-icon name="sliders" size="18" /> Configuration de la Campagne
            </h2>

            <form id="marketing-form" onsubmit="generateCampaign(event)">
                
                <!-- Choix du canal -->
                <div style="margin-bottom:1.2rem">
                    <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem">Canal de diffusion</label>
                    <select id="channel-select" class="form-control" style="width:100%;padding:.6rem;border-radius:var(--radius-sm);background:var(--bg-soft);border:1px solid var(--border);color:var(--text)">
                        <option value="whatsapp">📱 WhatsApp (Statut ou Message Broadcast)</option>
                        <option value="facebook_ads">🎯 Facebook & Instagram Ads (Meta)</option>
                        <option value="sms">💬 SMS Marketing (< 160 caractères)</option>
                        <option value="bundle_offer">🎁 Offre Pack Duo / Bundling Promo</option>
                    </select>
                </div>

                <!-- Choix du produit -->
                <div style="margin-bottom:1.2rem">
                    <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem">Article concerné</label>
                    <select id="product-select" class="form-control" style="width:100%;padding:.6rem;border-radius:var(--radius-sm);background:var(--bg-soft);border:1px solid var(--border);color:var(--text)">
                        <option value="">✨ Promotion globale de la boutique (Tous articles)</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" {{ $selectedProductId === $p->id ? 'selected' : '' }}>
                                {{ $p->title }} ({{ number_format($p->price, 0, ',', ' ') }} FCFA)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Angle psychologique -->
                <div style="margin-bottom:1.2rem">
                    <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem">Angle d'accroche</label>
                    <select id="angle-select" class="form-control" style="width:100%;padding:.6rem;border-radius:var(--radius-sm);background:var(--bg-soft);border:1px solid var(--border);color:var(--text)">
                        <option value="urgency">⚡ Urgence & Rareté (Stocks limités, 48h chrono)</option>
                        <option value="discount">🏷️ Remise & Bon Plan (Prix exceptionnel)</option>
                        <option value="social_proof">⭐ Preuve Sociale (Best-seller plébiscité)</option>
                        <option value="benefit">💡 Bénéfice Direct (Solution à un besoin clé)</option>
                    </select>
                </div>

                <!-- Remise facultative -->
                <div style="margin-bottom:1.2rem">
                    <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem">Avantage offert / Remise (facultatif)</label>
                    <input type="text" id="discount-input" class="form-control" placeholder="Ex: -20% aujourd'hui, Livraison offerte" style="width:100%;padding:.6rem;border-radius:var(--radius-sm);background:var(--bg-soft);border:1px solid var(--border);color:var(--text)">
                </div>

                <!-- Audience / Ville -->
                <div style="margin-bottom:1.5rem">
                    <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem">Audience ciblée (facultatif)</label>
                    <input type="text" id="audience-input" class="form-control" placeholder="Ex: Femmes actives à Lomé, Cotonou, Abidjan" style="width:100%;padding:.6rem;border-radius:var(--radius-sm);background:var(--bg-soft);border:1px solid var(--border);color:var(--text)">
                </div>

                <button type="submit" id="btn-submit" class="btn btn-primary" style="width:100%;padding:.75rem;display:flex;align-items:center;justify-content:center;gap:.5rem">
                    <x-icon name="sparkles" size="18" />
                    <span>Générer 3 Variations Vendeuses</span>
                </button>

                <div style="font-size:.75rem;color:var(--text-soft);text-align:center;margin-top:.6rem">
                    Consomme 1 crédit IA • Solde disponible : <strong>{{ $shop->aiCreditsRemaining() }}</strong>
                </div>

            </form>
        </div>

        <!-- RÉSULTAT DES VARIATIONS GÉNÉRÉES -->
        <div>
            <div id="empty-state" class="card" style="padding:3rem 2rem;text-align:center">
                <div style="width:64px;height:64px;border-radius:50%;background:var(--bg-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem auto;color:var(--brand-2)">
                    <x-icon name="edit-3" size="32" />
                </div>
                <h3 style="margin:0 0 .5rem 0;font-size:1.2rem">Vos copies publicitaires apparaîtront ici</h3>
                <p style="margin:0;color:var(--text-soft);font-size:.9rem;max-width:440px;margin:0 auto">
                    Sélectionnez un canal et un produit à gauche, puis cliquez sur <strong>Générer 3 Variations</strong>.
                </p>
            </div>

            <div id="loading-state" class="card" style="display:none;padding:3rem 2rem;text-align:center">
                <div style="font-size:2.5rem;margin-bottom:1rem;animation:bounce 1.5s infinite">✨</div>
                <h3 style="margin:0 0 .5rem 0;font-size:1.2rem">Rédaction des textes publicitaires en cours...</h3>
                <p style="margin:0;color:var(--text-soft);font-size:.9rem">L'IA analyse le produit et formule 3 angles psychologiques optimisés.</p>
            </div>

            <!-- Liste des 3 variations -->
            <div id="results-container" style="display:none;display:flex;flex-direction:column;gap:1.5rem"></div>

        </div>

    </div>

</div>

<script>
async function generateCampaign(e) {
    e.preventDefault();

    const channel = document.getElementById('channel-select').value;
    const productId = document.getElementById('product-select').value;
    const angle = document.getElementById('angle-select').value;
    const discount = document.getElementById('discount-input').value;
    const audience = document.getElementById('audience-input').value;

    const btn = document.getElementById('btn-submit');
    const emptyState = document.getElementById('empty-state');
    const loadingState = document.getElementById('loading-state');
    const resultsContainer = document.getElementById('results-container');

    btn.disabled = true;
    emptyState.style.display = 'none';
    loadingState.style.display = 'block';
    resultsContainer.style.display = 'none';
    resultsContainer.innerHTML = '';

    try {
        const response = await fetch("{{ route('ai.marketing.generate') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                channel: channel,
                product_id: productId || null,
                angle: angle,
                custom_discount: discount,
                target_audience: audience
            })
        });

        const data = await response.json();
        loadingState.style.display = 'none';
        btn.disabled = false;

        if (response.ok && data.status === 'success' && data.variations) {
            resultsContainer.style.display = 'flex';

            data.variations.forEach((v, idx) => {
                const card = document.createElement('div');
                card.className = 'card';
                card.style.padding = '1.6rem';

                const encodedText = encodeURIComponent(v.content);
                const whatsappUrl = `https://wa.me/?text=${encodedText}`;

                card.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <span class="badge" style="background:linear-gradient(135deg, var(--brand-1), var(--brand-2));color:#fff;font-weight:700">#${idx + 1}</span>
                            <strong style="font-size:1.05rem">${v.title || 'Variation ' + (idx + 1)}</strong>
                        </div>
                        <div style="display:flex;gap:.5rem">
                            <button onclick="copyToClipboard(this, ${JSON.stringify(v.content)})" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                                📋 Copier
                            </button>
                            <a href="${whatsappUrl}" target="_blank" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;border-color:#25D366;color:#fff">
                                📲 Envoyer sur WhatsApp
                            </a>
                        </div>
                    </div>

                    ${v.hook ? `<div style="font-size:.85rem;color:var(--brand-2);font-weight:600;margin-bottom:.6rem">⚡ Accroche : ${v.hook}</div>` : ''}

                    <div style="background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);font-size:.92rem;line-height:1.65;white-space:pre-wrap;border:1px solid var(--border);color:var(--text);margin-bottom:.8rem">${escapeHtml(v.content)}</div>

                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;font-size:.8rem;color:var(--text-soft)">
                        ${v.call_to_action ? `<div><strong>CTA recommandé :</strong> ${escapeHtml(v.call_to_action)}</div>` : ''}
                        ${v.targeting_tip ? `<div>💡 <strong>Conseil :</strong> ${escapeHtml(v.targeting_tip)}</div>` : ''}
                    </div>
                `;

                resultsContainer.appendChild(card);
            });

        } else {
            emptyState.style.display = 'block';
            alert(data.message || "Erreur lors de la génération du contenu.");
        }
    } catch (err) {
        loadingState.style.display = 'none';
        emptyState.style.display = 'block';
        btn.disabled = false;
        alert("Erreur réseau lors de la communication avec le serveur.");
    }
}

function copyToClipboard(btn, text) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '✓ Copié !';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
</style>
@endsection
