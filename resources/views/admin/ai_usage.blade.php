@extends('layouts.app')

@section('title', 'Journal d\'Usage IA : Super-Admin - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Journal & Coûts IA (CDC J-04)</h1>
            <p style="margin:0;color:var(--text-soft)">Suivi des fournisseurs et moteurs locaux. Les coûts sont estimatifs ; une absence de coût fournisseur ne constitue pas une gratuité. Les journaux publicitaires indiquent si l'usage et le coût sont disponibles.</p>
        </div>

        <div class="admin-nav-pills">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="zap" size="15" /> Dashboard
            </a>
            <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="shop" size="15" /> Boutiques
            </a>
            <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="credit-card" size="15" /> Abonnements & CA
            </a>
            <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="diamond" size="15" /> Formules
            </a>
            <a href="{{ route('admin.maintenance') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="wrench" size="15" /> Maintenance
            </a>
        </div>
    </div>

    <!-- 4 Métriques IA -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Appels IA Système</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display);color:var(--brand-2)">
                {{ number_format($systemAiCalls ?? $totalCalls, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Groq / OpenRouter ({{ number_format($systemAiCostFcfa ?? $totalCostFcfa, 0, ',', ' ') }} FCFA)</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Appels Photoroom Studio</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display);color:var(--brand-1)">
                {{ number_format($photoroomCalls ?? 0, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Détourage & Packshots ({{ number_format($photoroomCostFcfa ?? 0, 0, ',', ' ') }} FCFA)</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Coût API Total Estimé</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)" class="gradient-text">
                {{ number_format($totalCostFcfa, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--ok)">Total {{ number_format($totalCalls, 0, ',', ' ') }} générations</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Tokens LLM (Entrée / Sortie)</div>
            <div style="font-size:1.5rem;font-weight:700;font-family:var(--font-display)">
                {{ number_format($totalPromptTokens + $totalCompletionTokens, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">{{ number_format($totalPromptTokens, 0, ',', ' ') }} in • {{ number_format($totalCompletionTokens, 0, ',', ' ') }} out</div>
        </div>
    </div>

    <!-- 3 Cartes Métriques ECOM AI ASSISTANT -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.2rem;background:linear-gradient(135deg, rgba(99,102,241,0.08), transparent);border:1px solid color-mix(in srgb, var(--brand-2) 20%, var(--border))">
            <div style="font-size:.82rem;color:var(--brand-2);font-weight:600">Sessions & Conversations IA</div>
            <div style="font-size:1.6rem;font-weight:700;font-family:var(--font-display);margin-top:.2rem">
                {{ number_format($totalConversations, 0, ',', ' ') }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft)">Échanges marchands avec le Copilote</div>
        </div>

        <div class="card" style="padding:1.2rem;background:linear-gradient(135deg, rgba(236,72,153,0.08), transparent);border:1px solid color-mix(in srgb, #ec4899 20%, var(--border))">
            <div style="font-size:.82rem;color:#ec4899;font-weight:600">Messages & Tool Calls Traités</div>
            <div style="font-size:1.6rem;font-weight:700;font-family:var(--font-display);margin-top:.2rem">
                {{ number_format($totalMessages, 0, ',', ' ') }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft)">Actions d'inventaire, commandes & analyses</div>
        </div>

        <div class="card" style="padding:1.2rem;background:linear-gradient(135deg, rgba(16,185,129,0.08), transparent);border:1px solid color-mix(in srgb, var(--ok) 20%, var(--border))">
            <div style="font-size:.82rem;color:var(--ok);font-weight:600">Règles d'Automatisation Actives</div>
            <div style="font-size:1.6rem;font-weight:700;font-family:var(--font-display);margin-top:.2rem">
                {{ number_format($totalAutomations, 0, ',', ' ') }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft)">Surveillance stocks, relances COD & briefings</div>
        </div>
    </div>

    <!-- Gestion des Quotas & Recharges par Boutique (Module 15) -->
    <div class="card" style="margin-bottom:2rem;padding:1.5rem">
        <h3 style="margin:0 0 1rem 0;font-size:1.2rem">Quotas Séparés : Crédits IA Système vs Photoroom Studio</h3>

        <div style="overflow-x:auto">
            <table class="table" style="width:100%;min-width:920px;border-collapse:collapse;font-size:.88rem">
                <thead>
                    <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                        <th style="padding:.75rem">Boutique</th>
                        <th style="padding:.75rem">Plan Actuel</th>
                        <th style="padding:.75rem;text-align:center">IA Système (Restant / Plan)</th>
                        <th style="padding:.75rem;text-align:center">Photoroom Studio (Restant / Plan)</th>
                        <th style="padding:.75rem;text-align:right;white-space:nowrap">Recharge IA / Photoroom</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topConsumerShops as $s)
                        @php
                            $allocated = $s->aiCreditsMonthly();
                            $remaining = $s->aiCreditsRemaining();
                            $used = $s->aiCreditsUsedThisMonth();
                            $pctUsed = $allocated > 0 ? min(100, round(($used / $allocated) * 100)) : 0;

                            $prAllocated = $s->photoroomCreditsMonthly();
                            $prRemaining = $s->photoroomCreditsRemaining();
                            $prUsed = $s->photoroomCreditsUsedThisMonth();
                            $prPctUsed = $prAllocated > 0 ? min(100, round(($prUsed / $prAllocated) * 100)) : 0;
                        @endphp
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:.75rem;font-weight:600">
                                {{ $s->name }} <span style="font-size:.75rem;color:var(--text-soft)">({{ $s->subdomain }})</span>
                            </td>
                            <td style="padding:.75rem">
                                <span class="badge" style="background:var(--bg-soft)">{{ strtoupper($s->currentPlan()->name ?? 'FREE') }}</span>
                            </td>
                            <td style="padding:.75rem;text-align:center">
                                <strong style="color:{{ $remaining <= 5 ? 'var(--danger)' : 'var(--ok)' }}">{{ $remaining }}</strong> / {{ $allocated }}
                                <div style="font-size:.74rem;color:{{ $pctUsed >= 90 ? 'var(--danger)' : 'var(--text-soft)' }}">{{ $used }} utilisés ({{ $pctUsed }}%)</div>
                            </td>
                            <td style="padding:.75rem;text-align:center">
                                <strong style="color:{{ $prRemaining <= 2 ? 'var(--danger)' : 'var(--brand-1)' }}">{{ $prRemaining }}</strong> / {{ $prAllocated }}
                                <div style="font-size:.74rem;color:{{ $prPctUsed >= 90 ? 'var(--danger)' : 'var(--text-soft)' }}">{{ $prUsed }} utilisés ({{ $prPctUsed }}%)</div>
                            </td>
                            <td style="padding:.6rem .75rem;text-align:right;white-space:nowrap">
                                <div style="display:inline-flex;align-items:center;justify-content:flex-end;gap:.35rem;flex-wrap:wrap">
                                    <form action="{{ route('admin.ai.recharge', $s) }}" method="POST" style="margin:0;display:inline-block">
                                        @csrf
                                        <input type="hidden" name="quota_target" value="ai">
                                        <input type="hidden" name="action_type" value="add">
                                        <input type="hidden" name="bonus_credits" value="100">
                                        <button type="submit" class="btn btn-primary" style="height:30px;min-height:30px;padding:0 .65rem;font-size:.76rem;font-weight:600;border-radius:8px;box-shadow:none" title="Accorder 100 crédits IA bonus">+100 IA</button>
                                    </form>
                                    <form action="{{ route('admin.ai.recharge', $s) }}" method="POST" style="margin:0;display:inline-block">
                                        @csrf
                                        <input type="hidden" name="quota_target" value="ai">
                                        <input type="hidden" name="action_type" value="reset_used">
                                        <input type="hidden" name="bonus_credits" value="0">
                                        <button type="submit" class="btn btn-ghost" style="height:30px;min-height:30px;padding:0 .55rem;font-size:.76rem;border-radius:8px;border:1px solid var(--border);background:var(--bg-soft)" title="Remettre à zéro les crédits IA">Reset IA</button>
                                    </form>
                                    <form action="{{ route('admin.ai.recharge', $s) }}" method="POST" style="margin:0;display:inline-block">
                                        @csrf
                                        <input type="hidden" name="quota_target" value="photoroom">
                                        <input type="hidden" name="action_type" value="add">
                                        <input type="hidden" name="bonus_credits" value="25">
                                        <button type="submit" class="btn btn-primary" style="height:30px;min-height:30px;padding:0 .65rem;font-size:.76rem;font-weight:600;border-radius:8px;background:#7c3aed;border-color:#7c3aed;box-shadow:none" title="Accorder 25 photos Photoroom bonus">+25 Photoroom</button>
                                    </form>
                                    <form action="{{ route('admin.ai.recharge', $s) }}" method="POST" style="margin:0;display:inline-block">
                                        @csrf
                                        <input type="hidden" name="quota_target" value="photoroom">
                                        <input type="hidden" name="action_type" value="reset_used">
                                        <input type="hidden" name="bonus_credits" value="0">
                                        <button type="submit" class="btn btn-ghost" style="height:30px;min-height:30px;padding:0 .55rem;font-size:.76rem;border-radius:8px;border:1px solid var(--border);background:var(--bg-soft)" title="Remettre à zéro le quota Photoroom">Reset PR</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Table des générations récentes -->
    <div class="card" style="padding:0;overflow-x:auto">
        <table class="table" style="width:100%;min-width:760px;border-collapse:collapse;font-size:.92rem">
            <thead>
                <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                    <th style="padding:1rem">Date & Heure</th>
                    <th style="padding:1rem">Boutique</th>
                    <th style="padding:1rem">Type</th>
                    <th style="padding:1rem">Modèle</th>
                    <th style="padding:1rem;text-align:right">Tokens</th>
                    <th style="padding:1rem;text-align:right">Coût estimé</th>
                    <th style="padding:1rem;text-align:center">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($generations as $g)
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:1rem;color:var(--text-soft);font-size:.85rem">
                            {{ $g->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td style="padding:1rem;font-weight:600">
                            {{ $g->shop?->name ?: 'Boutique #' . $g->shop_id }}
                        </td>
                        <td style="padding:1rem">
                            <span style="font-family:monospace;font-size:.85rem">{{ $g->type }}</span>
                        </td>
                        <td style="padding:1rem;color:var(--text-soft);font-size:.85rem">
                            {{ $g->model_used }}
                        </td>
                        <td style="padding:1rem;text-align:right;font-family:monospace">
                            {{ number_format($g->prompt_tokens + $g->completion_tokens, 0, ',', ' ') }}
                        </td>
                        <td style="padding:1rem;text-align:right;font-weight:600">
                            {{ number_format($g->cost_estimated_fcfa, 0, ',', ' ') }} FCFA
                        </td>
                        <td style="padding:1rem;text-align:center">
                            <span class="badge {{ $g->status === 'success' ? 'badge-ok' : 'badge-warn' }}" style="font-size:.75rem">
                                {{ $g->status }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:2rem;display:flex;justify-content:center">
        {{ $generations->links() }}
    </div>
</div>
@endsection
