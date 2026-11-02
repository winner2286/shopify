@extends('layouts.app')

@section('title', 'Bibliothèque de Publicités : '.config('platform.name'))

@section('content')
<style>
.ads-header-btn-row {
    display: flex;
    gap: .5rem;
    align-items: center;
    flex-wrap: nowrap;
}
.btn-flyer-cta {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff !important;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    font-weight: 600;
    white-space: nowrap;
    text-decoration: none;
}
.btn-video-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    white-space: nowrap;
    text-decoration: none;
}
@media (max-width: 640px) {
    .ads-header-wrap {
        flex-direction: column;
        align-items: stretch !important;
        gap: .85rem !important;
    }
    .ads-header-btn-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: .5rem !important;
        width: 100% !important;
    }
    .ads-header-btn-row .btn {
        width: 100%;
        padding: .6rem .35rem !important;
        font-size: .8rem !important;
        text-align: center;
        justify-content: center;
    }
}
.ads-tabs-row {
    display: flex;
    gap: .5rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2rem;
    padding-bottom: .5rem;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.ads-tabs-row::-webkit-scrollbar {
    display: none;
}
.ads-tabs-row .btn {
    flex-shrink: 0;
}
</style>

<div class="container" style="max-width:960px;padding:2rem 1rem">
    <div class="ads-header-wrap" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Studio Publicité & Visuels</h1>
            <p style="margin:0;color:var(--text-soft)">Créez et diffusez vos vidéos IA et affiches promotionnelles pour WhatsApp & réseaux sociaux.</p>
        </div>

        <div class="ads-header-btn-row">
            <a href="{{ route('ads.flyers') }}" class="btn btn-sm btn-flyer-cta">
                <span>🎨 + Affiche Promo</span>
            </a>
            <a href="{{ route('ads.create') }}" class="btn btn-primary btn-sm btn-video-cta">
                <x-icon name="sparkles" :size="15" />
                <span>+ Vidéo IA</span>
            </a>
        </div>
    </div>

    <!-- Onglets Studio avec défilement horizontal fluide -->
    <div class="ads-tabs-row">
        <a href="{{ route('ads.index') }}" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>🎬 Bibliothèque Vidéos IA</span>
        </a>
        <a href="{{ route('ads.flyers') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>🎨 Affiches & Statuts WhatsApp</span>
        </a>
        <a href="{{ route('ads.create') }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
            <span>✨ Nouvelle Vidéo IA</span>
        </a>
    </div>

    @if ($ads->isEmpty())
        <div class="card" style="text-align:center;padding:4rem 1rem">
            <div class="svg-icon" style="color:var(--brand-2);margin-bottom:1rem">
                <x-icon name="ads" :size="54" />
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem">Aucune publicité générée</h3>
            <p style="color:var(--text-soft);max-width:420px;margin:0 auto 1.5rem">
                Utilisez l'assistant IA pour créer des textes percutants pour vos statuts WhatsApp, TikTok et campagnes SMS.
            </p>
            <a href="{{ route('ads.create') }}" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="sparkles" :size="16" />
                <span>Générer ma première publicité</span>
            </a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.5rem">
            @foreach ($ads as $ad)
                <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;padding:1.5rem">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
                            <span class="badge badge-ok" style="font-size:.75rem">{{ $ad->channel_label }}</span>
                            <span style="font-size:.8rem;color:var(--text-soft)">{{ $ad->created_at->format('d/m/Y') }}</span>
                        </div>

                        <h3 style="font-size:1.05rem;margin:0 0 .5rem">
                            {{ $ad->product ? $ad->product->title : 'Collection Générale' }}
                        </h3>

                        <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:1rem">
                            Objectif : <strong>{{ $ad->objective_label }}</strong> (Ton : {{ $ad->tone_label }})
                        </div>

                        @if (! empty($ad->generated_texts))
                            <p style="font-size:.9rem;color:var(--text-faint);line-height:1.4;margin:0 0 1rem;font-style:italic">
                                « {{ \Illuminate\Support\Str::limit($ad->generated_texts[0], 80) }} »
                            </p>
                        @endif
                    </div>

                    <div style="border-top:1px solid var(--border);padding-top:.8rem;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:.8rem;color:var(--text-soft)">3 variantes + scripts</span>
                        <a href="{{ route('ads.show', $ad) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                            <span>Voir & Partager</span>
                            <x-icon name="arrow-right" :size="13" />
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:2rem;display:flex;justify-content:center">
            {{ $ads->links() }}
        </div>
    @endif
</div>
@endsection
