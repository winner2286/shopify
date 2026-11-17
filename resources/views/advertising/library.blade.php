@extends('layouts.app')
@section('title', 'Studio créatif : '.config('platform.name'))
@section('content')
<link rel="stylesheet" href="{{ asset('css/creative-studio.css') }}?v={{ filemtime(public_path('css/creative-studio.css')) }}">
<div class="creative-shell">
    <div class="creative-top">
        <div><span class="creative-eyebrow">Zanika · Studio créatif</span><h1>Des produits. Une histoire.</h1><p>Composez vos vidéos, vos affiches et vos prochains lancements dans un même projet.</p></div>
        <a href="{{ route('ads.create') }}" class="btn btn-primary">+ Créer un projet</a>
    </div>
    <div class="creative-feature">
        <div><h2>Choisissez une direction.<br>Puis faites-la vôtre.</h2><p>Partez du catalogue, ajoutez vos photos et vos clips, choisissez un concept, puis ajustez chaque plan.</p><div class="creative-actions"><a class="btn btn-primary" href="{{ route('ads.create') }}">Créer une vidéo</a><a class="btn btn-ghost" href="{{ route('ads.flyers') }}">Composer une affiche</a></div></div>
        <div class="creative-directions" aria-hidden="true"><div class="creative-direction"><span>01 / CUTS & TYPO</span>LE<br>DROP<span>Un lancement rythmé</span></div><div class="creative-direction"><span>02 / ÉDITORIAL</span>LA<br>SÉLECTION<span>Une autre perspective</span></div><div class="creative-direction"><span>03 / EN MOUVEMENT</span>VOTRE<br>HISTOIRE<span>Des plans à assembler</span></div></div>
    </div>
    <div class="creative-section-label"><h2>Vos projets</h2><span class="creative-hint">{{ $ads->total() }} projet(s)</span></div>
    @if($ads->isEmpty())
        <div class="creative-empty"><h3>Votre prochain contenu commence ici.</h3><p>Un produit, une idée, ou une vidéo filmée avec votre téléphone : c’est assez pour commencer.</p><a href="{{ route('ads.create') }}" class="btn btn-primary">Créer mon premier projet</a></div>
    @else
        <div class="creative-project-grid">
        @foreach($ads as $ad)
            @php($scripts = $ad->generated_scripts ?? [])
            <a class="creative-project" href="{{ route('ads.show', $ad) }}">
                <div class="creative-project-art">
                    @if($ad->product?->primaryImage?->url)<img src="{{ $ad->product->primaryImage->url }}" alt="{{ $ad->product->title }}" loading="lazy">@endif
                </div>
                <div class="creative-project-meta"><p class="creative-project-direction">{{ $scripts[0]['title'] ?? 'Votre création' }}</p><h3>{{ $ad->product?->title ?: $shop->name }}</h3><small>{{ $ad->channel_label }} · {{ count($scripts) }} direction(s) · {{ $ad->updated_at->format('d/m/Y') }}</small></div>
            </a>
        @endforeach
        </div>
        <div style="margin-top:1.5rem">{{ $ads->links() }}</div>
    @endif
</div>
@endsection
