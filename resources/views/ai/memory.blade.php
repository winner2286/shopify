@extends('layouts.app')

@section('title', 'Mémoire de votre Assistant IA - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <!-- En-tête -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem">
                <span class="badge" style="background:var(--accent-alpha);color:var(--accent);font-weight:700">Souveraineté des Données</span>
                <h1 style="font-size:1.8rem;margin:0">Mémoire Commerciale de votre Assistant</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">
                Voici tout ce que votre copilote IA a appris et retenu sur votre boutique <strong>{{ $shop->name }}</strong>. Vous pouvez modifier ou supprimer n'importe quel élément à tout moment.
            </p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddMemoryModal()" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="plus" size="14" /> Ajouter une consigne
            </button>
            <a href="{{ route('ai.assistant') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="message-square" size="14" /> Retour au Chat
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1.5rem;padding:.75rem 1rem;border-radius:10px;background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3)">
            ✓ {{ session('success') }}
        </div>
    @endif

    <!-- STATS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem;border-radius:12px">
            <div style="font-size:0.85rem;color:var(--text-soft);margin-bottom:.25rem">Total Éléments Mémorisés</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--text)">{{ $memories->count() }}</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">Faits stables et préférences actives</div>
        </div>

        <div class="card" style="padding:1.25rem;border-radius:12px">
            <div style="font-size:0.85rem;color:var(--text-soft);margin-bottom:.25rem">Isolation des Données</div>
            <div style="font-size:1.4rem;font-weight:800;color:#10b981">100% Hermétique</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">Strictement réservé à votre boutique</div>
        </div>

        <div class="card" style="padding:1.25rem;border-radius:12px">
            <div style="font-size:0.85rem;color:var(--text-soft);margin-bottom:.25rem">Apprentissage</div>
            <div style="font-size:1.4rem;font-weight:800;color:var(--accent)">Continu & Supervisé</div>
            <div style="font-size:0.8rem;color:var(--text-soft);margin-top:.3rem">Pas de réentraînement aveugle</div>
        </div>
    </div>

    <!-- LISTE DES MÉMOIRES PAR CATÉGORIE -->
    @if($memories->isEmpty())
        <div class="card" style="text-align:center;padding:3.5rem 1.5rem;border-radius:12px">
            <div style="margin-bottom:1rem;color:var(--brand-1);display:flex;justify-content:center"><x-icon name="sparkles" size="40" /></div>
            <h3 style="margin-bottom:.5rem">Aucune mémoire enregistrée pour le moment</h3>
            <p style="color:var(--text-soft);max-width:500px;margin:0 auto 1.5rem">
                Discutez avec votre copilote ou ajoutez manuellement vos préférences pour qu'il s'adapte à vos habitudes (ex: zone de livraison prioritaire, objectifs de vente, canaux de discussion).
            </p>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddMemoryModal()">
                + Ajouter une première consigne
            </button>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1.5rem">
            @foreach($grouped as $category => $items)
                <div class="card" style="padding:1.5rem;border-radius:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.75rem">
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <x-icon name="tag" size="16" style="color:var(--brand-1)" />
                            <h3 style="margin:0;font-size:1.1rem;font-weight:700">
                                {{ $categoriesLabels[$category] ?? ucfirst($category) }}
                            </h3>
                            <span class="badge" style="background:var(--surface-muted);color:var(--text-soft);font-size:0.75rem">
                                {{ $items->count() }} fait(s)
                            </span>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1rem">
                        @foreach($items as $m)
                            <div style="padding:1rem;background:var(--surface-muted);border:1px solid var(--border);border-radius:10px;position:relative">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
                                    <strong style="font-size:0.85rem;color:var(--text);letter-spacing:0.02em">
                                        {{ str_replace('_', ' ', strtoupper($m->memory_key)) }}
                                    </strong>
                                    <form action="{{ route('ai.memory.destroy', $m) }}" method="POST" onsubmit="return confirm('Oublier définitivement cette information ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:#ef4444;padding:2px 6px;font-size:0.75rem" title="Supprimer">
                                            ✕
                                        </button>
                                    </form>
                                </div>

                                <div style="font-size:0.9rem;color:var(--text);margin-bottom:.75rem;line-height:1.4">
                                    {{ $m->memory_value }}
                                </div>

                                <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.75rem;color:var(--text-soft);border-top:1px dashed var(--border);padding-top:.5rem">
                                    <span>
                                        @if($m->source === 'manual_merchant')
                                            Défini par vous
                                        @else
                                            Appris au fil de l'eau
                                        @endif
                                    </span>
                                    <span>
                                        {{ $m->updated_at ? $m->updated_at->diffForHumans() : '' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- MODAL D'AJOUT D'UNE CONSIGNE / SOUVENIR -->
<div id="add-memory-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(3px)">
    <div class="card" style="max-width:480px;width:92%;padding:1.5rem;border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.4)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h4 style="margin:0;font-size:1.15rem">Ajouter une consigne à l'Assistant</h4>
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeAddMemoryModal()" style="padding:.2rem .5rem">✕</button>
        </div>

        <form action="{{ route('ai.memory.store') }}" method="POST">
            @csrf
            <div style="margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:.35rem">Catégorie</label>
                <select name="category" class="form-control" required style="width:100%;font-size:.9rem;padding:.5rem;border-radius:8px">
                    <option value="business_profile">Profil & Niche Métier (ex: articles vendus, style)</option>
                    <option value="merchant_preference">Préférence de communication (ex: tutoiement, ton court)</option>
                    <option value="commercial_goal">Objectif commercial (ex: CA visé, panier moyen)</option>
                    <option value="operational_habit">Habitude logistique (ex: coursiers, horaires)</option>
                    <option value="manual_instruction" selected>Règle ou Consigne personnalisée</option>
                </select>
            </div>

            <div style="margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:.35rem">Clé / Thème du souvenir</label>
                <input type="text" name="memory_key" class="form-control" placeholder="ex: livreur_prefere, ville_principale" required style="width:100%;font-size:.9rem;padding:.5rem;border-radius:8px">
            </div>

            <div style="margin-bottom:1.25rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:.35rem">Information détaillée</label>
                <textarea name="memory_value" class="form-control" rows="3" placeholder="ex: Je travaille exclusivement avec les coursiers Zemidjan pour Lomé intra-muros et je ne fais pas de livraison le dimanche." required style="width:100%;font-size:.9rem;padding:.5rem;border-radius:8px;resize:none"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeAddMemoryModal()">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer dans la mémoire</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddMemoryModal() {
    document.getElementById('add-memory-modal').style.display = 'flex';
}
function closeAddMemoryModal() {
    document.getElementById('add-memory-modal').style.display = 'none';
}
</script>
@endsection
