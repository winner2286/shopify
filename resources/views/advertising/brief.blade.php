@extends('layouts.app')
@section('title', 'Nouveau projet créatif : '.config('platform.name'))
@section('content')
<link rel="stylesheet" href="{{ asset('css/creative-studio.css') }}?v={{ filemtime(public_path('css/creative-studio.css')) }}">
<div class="creative-shell creative-brief">
    <div class="creative-top"><div><a class="creative-eyebrow" href="{{ route('ads.index') }}">← Vos projets</a><h1>Qu’allez-vous raconter ?</h1><p>Choisissez vos produits et donnez une intention. Vous pourrez tout ajuster dans le studio.</p></div></div>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('ads.studio.store') }}" id="creative-brief-form">
        @csrf
        <input type="hidden" name="initial_format" value="{{ $initialFormat }}">
        <div class="creative-brief-card">
            <h2>1. Les produits à mettre en scène</h2>
            @if($products->isEmpty())<p>Aucun produit actif. Vous pourrez importer vos propres médias dans le studio.</p>@endif
            <div class="creative-products">
            @foreach($products as $product)
                <label class="creative-product"><input type="checkbox" name="selected_product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, old('selected_product_ids', request('produit_id') ? [(int) request('produit_id')] : [])))>
                    @if($product->primaryImage?->url)<img src="{{ $product->primaryImage->url }}" alt="" loading="lazy">@endif
                    <span>{{ $product->title }}</span><small>{{ number_format($product->price, 0, ',', ' ') }} {{ $shop->currencyLabel() }}</small>
                </label>
            @endforeach
            </div><div class="creative-hint" style="margin-top:.7rem">Jusqu’à 5 produits. Sans sélection, le studio utilisera les derniers produits du catalogue.</div>
        </div>
        <div class="creative-brief-card"><h2>2. Votre intention créative</h2>
            <div class="creative-field"><label for="creative-brief">Que voulez-vous montrer ou faire ressentir ?</label><textarea class="input" id="creative-brief" name="creative_brief" maxlength="1200" placeholder="Ex. Une vidéo mode avec des cuts nets, un gros plan sur les matières et une ambiance de lancement. Je veux donner envie de découvrir la collection.">{{ old('creative_brief') }}</textarea></div>
            <div class="creative-fields" style="margin-top:1rem"><div class="creative-field"><label for="creative-audience">Pour qui ? <span class="creative-hint">facultatif</span></label><input class="input" id="creative-audience" name="audience" maxlength="180" value="{{ old('audience') }}" placeholder="Ex. Jeunes actifs, passionnés de sneakers"></div><div class="creative-field"><label for="creative-channel">Où allez-vous publier ?</label><select class="input" id="creative-channel" name="channel">@foreach(['whatsapp'=>'WhatsApp','tiktok'=>'TikTok','instagram'=>'Instagram Reels','facebook'=>'Facebook','general'=>'Tous réseaux'] as $value=>$label)<option value="{{ $value }}" @selected(old('channel', $shop->canUseFeature('tiktok_ads') ? 'tiktok' : 'whatsapp') === $value)>{{ $label }}</option>@endforeach</select></div></div>
            <details class="creative-drawer"><summary>Durée et rythme</summary><div class="creative-field"><label for="creative-duration">Durée cible</label><select class="input" id="creative-duration" name="target_duration">@foreach([15,20,25,30,45] as $duration)<option value="{{ $duration }}" @selected((int) old('target_duration',20)===$duration)>{{ $duration }} secondes</option>@endforeach</select><small class="creative-hint">La durée de chaque plan restera modifiable dans le montage.</small></div></details>
            <details class="creative-drawer"><summary>Retouche des photos du catalogue</summary><div><label class="creative-hint"><input type="checkbox" name="enhance_photoroom" value="1" @checked(old('enhance_photoroom'))> Détourer les photos des produits</label><p class="creative-hint">Option appliquée avec « Proposer 3 directions ». Le détourage local est inclus. Un traitement de secours par Photoroom utilise un crédit Photoroom.</p></div></details>
        </div>
        <div class="creative-brief-foot"><span class="creative-hint">Catalogue, photos, clips et plans IA<br>réunis dans le même studio.</span><div class="creative-actions"><button class="btn btn-ghost" name="use_ai" value="0" type="submit">Commencer sans IA</button><button class="btn btn-primary" name="use_ai" value="1" type="submit">Proposer 3 directions · 1 crédit IA</button></div></div>
    </form>
</div>
<script>
document.getElementById('creative-brief-form').addEventListener('submit', function(event) {
    if (this.querySelectorAll('input[name="selected_product_ids[]"]:checked').length > 5) {event.preventDefault(); alert('Choisissez au maximum 5 produits pour ce projet.'); return;}
    if (event.submitter) event.submitter.textContent = 'Préparation du projet…';
});
</script>
@endsection
