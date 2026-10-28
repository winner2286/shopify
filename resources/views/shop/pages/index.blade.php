@extends('layouts.app')

@section('title', 'Pages d\'information : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:960px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Pages d'information</h1>
            <p style="margin:0;color:var(--text-soft)">Personnalisez les textes de présentation, vos modalités de livraison et vos conditions de vente (B-08).</p>
        </div>
        <a href="{{ route('shop.settings') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
            <x-icon name="arrow-left" size="14" /> Retour aux paramètres
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr;gap:1rem">
        @forelse ($pages as $p)
            <div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
                <div style="flex:1;min-width:240px">
                    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                        <span style="color:var(--brand-primary);display:flex;align-items:center">
                            @if ($p->type === 'about') <x-icon name="file-text" size="20" />
                            @elseif ($p->type === 'contact') <x-icon name="phone" size="20" />
                            @elseif ($p->type === 'shipping') <x-icon name="truck" size="20" />
                            @else <x-icon name="file-text" size="20" />
                            @endif
                        </span>
                        <h3 style="margin:0;font-size:1.15rem">{{ $p->title }}</h3>
                        @if ($p->is_published)
                            <span class="badge badge-ok" style="font-size:.75rem">Publiée</span>
                        @else
                            <span class="badge badge-draft" style="font-size:.75rem">Masquée</span>
                        @endif
                    </div>
                    <p style="margin:0;font-size:.9rem;color:var(--text-soft);max-width:650px">
                        {{ \Illuminate\Support\Str::limit($p->content, 140) }}
                    </p>
                </div>

                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    @if ($shop->subdomain && $p->is_published)
                        <a href="{{ url('/b/' . $shop->subdomain . '/p/' . $p->type) }}" target="_blank" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem" title="Voir la page publique">
                            <x-icon name="eye" size="14" /> Voir <x-icon name="external-link" size="12" />
                        </a>
                    @endif
                    <a href="{{ route('shop.pages.edit', $p) }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                        <x-icon name="edit" size="14" /> Modifier
                    </a>
                </div>
            </div>
        @empty
            <div class="card" style="text-align:center;padding:3rem">
                <p>Aucune page d'information trouvée.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
