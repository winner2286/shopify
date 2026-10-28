@extends('layouts.app')

@section('title', 'Bibliothèque de Publicités : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:960px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Bibliothèque de Publicités (H-05)</h1>
            <p style="margin:0;color:var(--text-soft)">Retrouvez et réutilisez à tout moment vos annonces et scripts vidéo générés.</p>
        </div>

        <a href="{{ route('ads.create') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="sparkles" :size="15" />
            <span>+ Nouvelle publicité IA</span>
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
