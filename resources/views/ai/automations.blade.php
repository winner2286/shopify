@extends('layouts.app')

@section('title', 'Automatisations IA : '.config('platform.name'))

@section('content')
<div class="container automations-page-container" style="max-width:1160px;padding:2rem 1rem">
    
    <!-- En-tête -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.5rem;margin-bottom:2rem">
        <div>
            <div style="display:inline-flex;align-items:center;gap:.5rem;padding:.3rem .8rem;border-radius:999px;background:linear-gradient(135deg, rgba(16,185,129,0.15), rgba(99,102,241,0.15));border:1px solid rgba(16,185,129,0.3);color:var(--ok);font-size:.8rem;font-weight:600;margin-bottom:.6rem">
                <x-icon name="zap" size="14" /> MODULE 10 • MOTEUR D'AUTOMATISATIONS INTELLIGENTES
            </div>
            <h1 style="font-size:1.85rem;margin:0;font-family:var(--font-display)">Automatisations & Déclencheurs IA</h1>
            <p style="margin:.4rem 0 0 0;color:var(--text-soft);max-width:680px;font-size:.95rem">
                Laissez votre agent IA surveiller en arrière-plan vos stocks critiques, préparer les relances pour vos commandes COD et compiler vos briefings quotidiens.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
            <button type="button" id="btn-run-now" onclick="runAutomationsNow()" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="play" size="16" /> Exécuter & Vérifier maintenant
            </button>
            <a href="{{ route('ai.marketing') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="edit-3" size="16" /> Studio Marketing
            </a>
            <a href="{{ route('ai.assistant') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="message-square" size="16" /> Assistant IA
            </a>
        </div>
    </div>

    <!-- Rapport d'exécution en direct -->
    <div id="execution-report-card" class="card" style="display:none;padding:1.5rem;margin-bottom:2rem;background:linear-gradient(135deg, rgba(99,102,241,0.06), rgba(16,185,129,0.06));border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--brand-2)">
                <x-icon name="check-circle" size="20" /> Résultat de l'Exécution des Règles
            </div>
            <button onclick="document.getElementById('execution-report-card').style.display='none'" class="btn btn-ghost btn-sm" style="padding:.2rem .5rem">✕ Fermer</button>
        </div>
        <div id="execution-report-content" style="display:flex;flex-direction:column;gap:.8rem;font-size:.9rem"></div>
    </div>

    <!-- STATS DES AUTOMATISATIONS -->
    @php
        $activeCount = $automations->where('is_active', true)->count();
        $totalTriggers = $automations->sum('trigger_count');
        $lastRun = $automations->whereNotNull('last_triggered_at')->sortByDesc('last_triggered_at')->first()?->last_triggered_at;
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.2rem;margin-bottom:2rem">
        <div class="card" style="padding:1.3rem">
            <span style="font-size:.82rem;color:var(--text-soft)">Règles Actives</span>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);color:var(--ok);margin-top:.3rem">
                {{ $activeCount }} / {{ $automations->count() }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.3rem">Surveillent votre boutique</div>
        </div>

        <div class="card" style="padding:1.3rem">
            <span style="font-size:.82rem;color:var(--text-soft)">Déclenchements Totaux</span>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);margin-top:.3rem">
                {{ $totalTriggers }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.3rem">Alertes & actions créées</div>
        </div>

        <div class="card" style="padding:1.3rem">
            <span style="font-size:.82rem;color:var(--text-soft)">Dernière Activité</span>
            <div style="font-size:1.1rem;font-weight:700;margin-top:.6rem">
                {{ $lastRun ? $lastRun->diffForHumans() : 'Aucune encore' }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.3rem">Cycle de vérification régulier</div>
        </div>
    </div>

    <!-- LISTE DES RÈGLES D'AUTOMATISATION -->
    <h2 style="font-size:1.35rem;margin:0 0 1.2rem 0">Règles & Déclencheurs Configurés</h2>

    <div style="display:flex;flex-direction:column;gap:1rem">
        @foreach ($automations as $auto)
            <div class="card" style="padding:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.2rem;border-left:4px solid {{ $auto->is_active ? 'var(--ok)' : 'var(--border)' }}">
                
                <div style="display:flex;align-items:flex-start;gap:1.2rem">
                    <div style="width:44px;height:44px;border-radius:12px;background:{{ $auto->is_active ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'var(--bg-soft)' }};display:flex;align-items:center;justify-content:center;color:{{ $auto->is_active ? 'var(--ok)' : 'var(--text-soft)' }}">
                        @if ($auto->event_type === 'low_stock')
                            <x-icon name="alert-triangle" size="22" />
                        @elseif ($auto->event_type === 'pending_cod_order')
                            <x-icon name="truck" size="22" />
                        @elseif ($auto->event_type === 'daily_summary')
                            <x-icon name="sun" size="22" />
                        @else
                            <x-icon name="shopping-bag" size="22" />
                        @endif
                    </div>

                    <div>
                        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
                            <strong style="font-size:1.05rem">{{ $auto->name }}</strong>
                            <span id="badge-status-{{ $auto->id }}" class="badge" style="background:{{ $auto->is_active ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'var(--bg-soft)' }};color:{{ $auto->is_active ? 'var(--ok)' : 'var(--text-soft)' }};font-size:.75rem">
                                {{ $auto->is_active ? 'Actif' : 'En pause' }}
                            </span>
                        </div>

                        <p style="margin:.3rem 0 .5rem 0;font-size:.88rem;color:var(--text-soft)">
                            @if ($auto->event_type === 'low_stock')
                                Détecte automatiquement tout produit atteignant ou passant sous son seuil d'alerte (seuil par défaut : 3 unités) et prévient le commerçant.
                            @elseif ($auto->event_type === 'pending_cod_order')
                                Repère les commandes en paiement à la livraison (COD) sans confirmation depuis plus de 2 heures pour lancer une relance WhatsApp avant risque d'annulation.
                            @elseif ($auto->event_type === 'daily_summary')
                                Calcule chaque matin à 08h00 le récapitulatif des commandes de la veille et suggère les priorités de la journée.
                            @else
                                Détecte les abandons de panier et prépare une notification incitative.
                            @endif
                        </p>

                        <div style="display:flex;align-items:center;gap:1.2rem;font-size:.8rem;color:var(--text-soft)">
                            <span>Déclenché <strong>{{ $auto->trigger_count }}</strong> fois</span>
                            @if ($auto->last_triggered_at)
                                <span>Dernier déclenchement : {{ $auto->last_triggered_at->translatedFormat('d M Y à H:i') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Bouton Bascule -->
                <div>
                    <button type="button" 
                            id="btn-toggle-{{ $auto->id }}"
                            onclick="toggleAutomation({{ $auto->id }})" 
                            class="btn {{ $auto->is_active ? 'btn-ghost' : 'btn-primary' }} btn-sm" 
                            style="min-width:130px;display:inline-flex;align-items:center;justify-content:center;gap:.4rem">
                        <x-icon name="{{ $auto->is_active ? 'pause' : 'play' }}" size="14" />
                        <span>{{ $auto->is_active ? 'Mettre en pause' : 'Activer' }}</span>
                    </button>
                </div>

            </div>
        @endforeach
    </div>

</div>

<script>
async function toggleAutomation(id) {
    const btn = document.getElementById(`btn-toggle-${id}`);
    const badge = document.getElementById(`badge-status-${id}`);

    btn.disabled = true;

    try {
        const response = await fetch(`/assistant-ia/automatisations/${id}/basculer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        btn.disabled = false;

        if (response.ok && data.status === 'success') {
            if (data.is_active) {
                badge.textContent = 'Actif';
                badge.style.background = 'color-mix(in srgb, var(--ok) 15%, transparent)';
                badge.style.color = 'var(--ok)';
                btn.className = 'btn btn-ghost btn-sm';
                btn.innerHTML = `<x-icon name="pause" size="14" /> <span>Mettre en pause</span>`;
            } else {
                badge.textContent = 'En pause';
                badge.style.background = 'var(--bg-soft)';
                badge.style.color = 'var(--text-soft)';
                btn.className = 'btn btn-primary btn-sm';
                btn.innerHTML = `<x-icon name="play" size="14" /> <span>Activer</span>`;
            }
        } else {
            alert(data.message || "Erreur lors du changement de statut.");
        }
    } catch (err) {
        btn.disabled = false;
        alert("Erreur réseau.");
    }
}

async function runAutomationsNow() {
    const btn = document.getElementById('btn-run-now');
    const reportCard = document.getElementById('execution-report-card');
    const reportContent = document.getElementById('execution-report-content');

    btn.disabled = true;
    btn.innerHTML = `<span style="display:inline-block;animation:spin 1s linear infinite">⏳</span> <span>Vérification en cours...</span>`;

    try {
        const response = await fetch("{{ route('ai.automations.run_now') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        btn.disabled = false;
        btn.innerHTML = `<x-icon name="play" size="16" /> <span>Exécuter & Vérifier maintenant</span>`;

        if (response.ok && data.status === 'success' && data.report) {
            reportCard.style.display = 'block';
            reportContent.innerHTML = '';

            data.report.reports.forEach(r => {
                const item = document.createElement('div');
                item.style.padding = '.8rem 1rem';
                item.style.borderRadius = 'var(--radius-sm)';
                item.style.background = r.status === 'triggered' ? 'color-mix(in srgb, var(--brand-1) 10%, transparent)' : 'var(--bg-soft)';
                item.style.border = '1px solid var(--border)';

                item.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem">
                        <strong>${escapeHtml(r.automation)}</strong>
                        <span class="badge" style="background:${r.status === 'triggered' ? 'var(--brand-2)' : 'var(--ok)'};color:#fff;font-size:.7rem">
                            ${r.status === 'triggered' ? 'Déclenché' : 'Optimal'}
                        </span>
                    </div>
                    <div style="color:var(--text);font-size:.86rem">${escapeHtml(r.summary)}</div>
                    ${r.items ? `<div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">Articles concernés : ${r.items.map(escapeHtml).join(', ')}</div>` : ''}
                    ${r.orders ? `<div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">Commandes concernées : ${r.orders.map(escapeHtml).join(', ')}</div>` : ''}
                `;
                reportContent.appendChild(item);
            });
        } else {
            alert(data.message || "Erreur lors de l'exécution des règles.");
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = `<x-icon name="play" size="16" /> <span>Exécuter & Vérifier maintenant</span>`;
        alert("Erreur de communication avec le serveur.");
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<style>
@media (max-width: 768px) {
    .automations-page-container {
        padding-bottom: 85px !important;
    }
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
@endsection
