@extends('layouts.app')

@section('title', 'Journal d\'Usage IA : Super-Admin - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Journal & Coûts IA (CDC J-04)</h1>
            <p style="margin:0;color:var(--text-soft)">Suivi technique et financier des requêtes d'intelligence artificielle (Gemini Flash).</p>
        </div>

        <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">← Tableau de bord admin</a>
    </div>

    <!-- 4 Métriques IA -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Appels IA Réalisés</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display);color:var(--brand-2)">
                {{ number_format($totalCalls, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Générations totales</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Coût Total Estimé</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)" class="gradient-text">
                {{ number_format($totalCostFcfa, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--ok)">Marge plateforme respectée</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Tokens Prompt (Entrée)</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">
                {{ number_format($totalPromptTokens, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Consommation prompts</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Tokens Complétion (Sortie)</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">
                {{ number_format($totalCompletionTokens, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Contenus générés</div>
        </div>
    </div>

    <!-- Table des générations récentes -->
    <div class="card" style="padding:0;overflow-x:auto">
        <table class="table" style="width:100%;border-collapse:collapse;font-size:.92rem">
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
