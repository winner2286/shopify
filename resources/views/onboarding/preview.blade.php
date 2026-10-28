@extends('layouts.app')

@section('title', 'Aperçu de votre boutique : '.$shop->name.' - '.config('platform.name'))

@section('content')
<div class="container dash" style="max-width:980px">
    
    <div class="dash-head">
        <div>
            <span class="badge" style="margin-bottom:.5rem"><span class="dot"></span> Génération IA terminée avec succès</span>
            <h1>{{ $shop->name }}</h1>
            <p style="margin:0;color:var(--text-soft)">Votre boutique est prête en brouillon. Choisissez votre logo préféré et vérifiez vos produits.</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('products.index') }}" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:.4rem">Fixer mes prix & stocks <x-icon name="arrow-right" size="16" /></a>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost">Tableau de bord</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif

    <!-- Section 1 : Sélection du logo (B-05) -->
    <section class="card panel" style="margin-bottom:2rem">
        <h3>1. Choisissez votre logo officiel (3 propositions de l'IA)</h3>
        <p style="color:var(--text-soft);font-size:.9rem;margin-bottom:1.5rem">
            Touchez le logo qui correspond le mieux à l'image de marque de votre commerce.
        </p>

        @php $chosenLogoId = $shop->onboarding_data['chosen_logo']['id'] ?? 1; @endphp
        <div class="dash-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            @foreach ($logos as $logo)
                <form method="POST" action="{{ route('onboarding.select_logo') }}">
                    @csrf
                    <input type="hidden" name="logo_id" value="{{ $logo['id'] }}">
                    <button type="submit" class="logo-option {{ $chosenLogoId == $logo['id'] ? 'selected' : '' }}" style="width:100%;background:transparent;color:inherit;font:inherit;">
                        <div class="logo-svg-wrap">
                            {!! $logo['svg'] !!}
                        </div>
                        <strong>{{ $logo['name'] }}</strong>
                        <div style="margin-top:.4rem;font-size:.82rem;color:{{ $chosenLogoId == $logo['id'] ? 'var(--ok)' : 'var(--text-soft)' }}">
                            @if ($chosenLogoId == $logo['id'])
                                <span style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="check" size="14" /> Logo sélectionné</span>
                            @else
                                Choisir ce logo
                            @endif
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    </section>

    <!-- Section 2 : Rayons & Catégories générées (B-05) -->
    <section class="card panel" style="margin-bottom:2rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0">2. Rayons créés pour votre boutique ({{ $shop->categories->count() }})</h3>
            <a href="{{ route('categories.index') }}" class="btn btn-ghost btn-sm">Modifier</a>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem">
            @foreach ($shop->categories as $cat)
                <span style="background:var(--bg-soft);padding:.4rem .9rem;border-radius:999px;border:1px solid var(--border);font-size:.9rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="folder" size="16" /> {{ $cat->name }}
                </span>
            @endforeach
        </div>
    </section>

    <!-- Section 3 : Fiches produits générées (B-05, C-01) -->
    <section class="card panel" style="margin-bottom:2rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <div>
                <h3 style="margin:0">3. Fiches produits rédigées ({{ $shop->products->count() }})</h3>
                <p style="font-size:.85rem;color:var(--text-soft);margin:.2rem 0 0;display:inline-flex;align-items:center;gap:.3rem">
                    <x-icon name="alert-triangle" size="15" style="color:var(--warn)" />
                    Conformément aux règles, l'IA n'invente ni prix ni stock : renseignez vos tarifs avant de publier.
                </p>
            </div>
            <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm">Gérer le stock</a>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px">Aperçu</th>
                        <th>Titre généré</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shop->products as $p)
                        <tr>
                            <td>
                                @if ($p->primaryImage)
                                    <img src="{{ $p->primaryImage->thumbnail_url }}" alt="{{ $p->title }}" class="thumb-preview" loading="lazy">
                                @else
                                    <div class="thumb-preview" style="display:grid;place-items:center;color:var(--text-soft)">
                                        <x-icon name="camera" size="18" />
                                    </div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $p->title }}</strong>
                                <div style="font-size:.82rem;color:var(--text-soft);max-width:380px">{{ Str::limit($p->description, 80) }}</div>
                            </td>
                            <td>
                                {{ $p->categories->pluck('name')->join(', ') ?: 'Général' }}
                            </td>
                            <td>
                                @if ($p->price > 0)
                                    <strong>{{ number_format($p->price, 0, ',', ' ') }} FCFA</strong>
                                @else
                                    <span class="badge-status badge-low-stock">À fixer</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('products.edit', $p) }}" class="btn btn-primary btn-sm">Fixer le prix</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section 4 : Pages types générées -->
    <section class="card panel">
        <h3>4. Pages d'information prêtes pour vos clients (B-08)</h3>
        <div class="dash-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-top:1rem">
            @foreach ($shop->pages as $page)
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;border:1px solid var(--border)">
                    <strong style="display:inline-flex;align-items:center;gap:.4rem;font-size:.95rem;margin-bottom:.3rem">
                        <x-icon name="file-text" size="16" />
                        {{ $page->title }}
                    </strong>
                    <p style="font-size:.8rem;color:var(--text-soft);margin:0">{{ Str::limit($page->content, 90) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Bouton final -->
    <div style="text-align:center;margin:3rem 0 1rem">
        <a href="{{ route('products.index') }}" class="btn btn-primary" style="padding:1rem 3rem;font-size:1.15rem;display:inline-flex;align-items:center;justify-content:center;gap:.6rem;margin:0 auto">
            Accéder à la gestion de mes produits
            <x-icon name="arrow-right" size="18" />
        </a>
    </div>

</div>
@endsection
