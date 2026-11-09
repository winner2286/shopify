@extends('layouts.app')

@section('title', 'Cockpit Retours & Qualité IA - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1180px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem">
                <span class="badge" style="background:var(--accent-alpha);color:var(--accent);font-weight:700">Amélioration Continue</span>
                <h1 style="font-size:1.8rem;margin:0">Cockpit Qualité & Évaluations IA</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">Analyse des retours commerçants pour enrichir la base de connaissances RAG sans modification aveugle du modèle.</p>
        </div>

        <div class="admin-nav-pills" style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="cpu" size="15" /> Consommation & Coûts IA
            </a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="zap" size="15" /> Dashboard
            </a>
        </div>
    </div>

    <!-- KPI STATS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem;border-radius:12px">
            <div style="font-size:0.85rem;color:var(--text-soft);margin-bottom:.25rem">Total Évaluations</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--text)">{{ number_format($stats['total']) }}</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">Avis commerçants enregistrés</div>
        </div>

        <div class="card" style="padding:1.25rem;border-radius:12px;border-left:4px solid #10b981">
            <div style="font-size:0.85rem;color:#10b981;font-weight:600;margin-bottom:.25rem">Réponses Utiles</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--text)">{{ number_format($stats['helpful']) }}</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">
                {{ $stats['total'] > 0 ? round(($stats['helpful'] / $stats['total']) * 100, 1) : 0 }}% de satisfaction
            </div>
        </div>

        <div class="card" style="padding:1.25rem;border-radius:12px;border-left:4px solid #ef4444">
            <div style="font-size:0.85rem;color:#ef4444;font-weight:600;margin-bottom:.25rem">Réponses Incorrectes</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--text)">{{ number_format($stats['incorrect']) }}</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">À corriger dans le RAG</div>
        </div>

        <div class="card" style="padding:1.25rem;border-radius:12px;border-left:4px solid #f59e0b">
            <div style="font-size:0.85rem;color:#f59e0b;font-weight:600;margin-bottom:.25rem">Incomplètes ou Peu Pertinentes</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--text)">{{ number_format($stats['incomplete'] + $stats['irrelevant']) }}</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">Précisions documentaires requises</div>
        </div>
    </div>

    <!-- FILTRES -->
    <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap">
        <a href="{{ route('admin.ai_feedbacks') }}" class="btn btn-sm {{ empty($ratingFilter) ? 'btn-primary' : 'btn-ghost' }}">Tous ({{ $stats['total'] }})</a>
        <a href="{{ route('admin.ai_feedbacks', ['rating' => 'helpful']) }}" class="btn btn-sm {{ $ratingFilter === 'helpful' ? 'btn-primary' : 'btn-ghost' }}">Utiles ({{ $stats['helpful'] }})</a>
        <a href="{{ route('admin.ai_feedbacks', ['rating' => 'incorrect']) }}" class="btn btn-sm {{ $ratingFilter === 'incorrect' ? 'btn-primary' : 'btn-ghost' }}">Incorrects ({{ $stats['incorrect'] }})</a>
        <a href="{{ route('admin.ai_feedbacks', ['rating' => 'incomplete']) }}" class="btn btn-sm {{ $ratingFilter === 'incomplete' ? 'btn-primary' : 'btn-ghost' }}">Incomplets ({{ $stats['incomplete'] }})</a>
        <a href="{{ route('admin.ai_feedbacks', ['rating' => 'irrelevant']) }}" class="btn btn-sm {{ $ratingFilter === 'irrelevant' ? 'btn-primary' : 'btn-ghost' }}">Peu pertinents ({{ $stats['irrelevant'] }})</a>
    </div>

    <!-- TABLE DES RETOURS -->
    <div class="card" style="padding:0;overflow:hidden;border-radius:12px">
        <div class="table-responsive">
            <table class="table" style="margin:0;width:100%;font-size:.9rem">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);background:var(--surface-muted)">
                        <th style="padding:1rem">Date & Boutique</th>
                        <th style="padding:1rem">Évaluation</th>
                        <th style="padding:1rem;width:40%">Extrait du Message IA Évalué</th>
                        <th style="padding:1rem">Commentaire Commerçant</th>
                        <th style="padding:1rem">Validation & RAG</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feedbacks as $fb)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:1rem;white-space:nowrap">
                                <div><strong>{{ $fb->shop->name ?? 'Boutique #'.$fb->shop_id }}</strong></div>
                                <div style="font-size:0.8rem;color:var(--text-soft)">{{ $fb->user->name ?? 'Commerçant' }}</div>
                                <div style="font-size:0.75rem;color:var(--text-soft)">{{ $fb->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td style="padding:1rem;white-space:nowrap">
                                @if($fb->rating === 'helpful')
                                    <span class="badge" style="background:rgba(16,185,129,0.12);color:#10b981;font-weight:600">Utile</span>
                                @elseif($fb->rating === 'incorrect')
                                    <span class="badge" style="background:rgba(239,68,68,0.12);color:#ef4444;font-weight:600">Incorrect</span>
                                @elseif($fb->rating === 'incomplete')
                                    <span class="badge" style="background:rgba(245,158,11,0.12);color:#f59e0b;font-weight:600">Incomplet</span>
                                @else
                                    <span class="badge" style="background:rgba(100,116,139,0.12);color:#64748b;font-weight:600">Peu pertinent</span>
                                @endif
                            </td>
                            <td style="padding:1rem;color:var(--text)">
                                <div style="font-size:0.85rem;line-height:1.4;max-height:80px;overflow:hidden;text-overflow:ellipsis">
                                    {{ Str::limit($fb->message->content ?? 'Message non disponible', 200) }}
                                </div>
                            </td>
                            <td style="padding:1rem">
                                @if($fb->comment)
                                    <div style="font-size:0.85rem;color:var(--text);font-style:italic">"{{ $fb->comment }}"</div>
                                @else
                                    <span style="font-size:0.8rem;color:var(--text-soft)">Aucun commentaire</span>
                                @endif
                            </td>
                            <td style="padding:1rem;white-space:nowrap">
                                @if($fb->admin_reviewed)
                                    <span class="badge" style="background:rgba(16,185,129,0.12);color:#10b981;font-size:0.75rem">✓ Révisé</span>
                                @else
                                    <form method="POST" action="{{ route('admin.ai_feedbacks.resolve', $fb->id) }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="admin_notes" value="Validé depuis le cockpit qualité">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="font-size:0.75rem">Marquer révisé</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:3rem 1rem;color:var(--text-soft)">
                                Aucun retour enregistré pour le moment. Les avis des commerçants apparaîtront ici automatiquement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($feedbacks->hasPages())
            <div style="padding:1rem;border-top:1px solid var(--border)">
                {{ $feedbacks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
