@php
    $photoShop = auth()->user()?->shop;
    $sceneAvailable = $photoShop && $photoShop->canUsePhotoroomGenerativeScene() && app(\App\Services\Ai\PhotoroomService::class)->isConfigured();
@endphp
<link rel="stylesheet" href="{{ asset('css/product-photo-studio.css') }}?v={{ filemtime(public_path('css/product-photo-studio.css')) }}">
<div id="product-photo-studio" class="photo-studio-backdrop" hidden data-url="{{ route('ai.products.verify_quality') }}" data-scene-available="{{ $sceneAvailable ? 'true' : 'false' }}">
    <section class="photo-studio-dialog" role="dialog" aria-modal="true" aria-labelledby="photo-studio-title" tabindex="-1">
        <header class="photo-studio-head"><div><span class="photo-studio-eyebrow">Studio photo</span><h2 id="photo-studio-title">Votre produit, sous son meilleur jour.</h2><p>Choisissez un rendu et comparez-le à votre photo.</p></div><button type="button" class="btn btn-ghost" id="photo-studio-close" aria-label="Fermer le studio">✕</button></header>
        <div class="photo-studio-layout">
            <div class="photo-studio-preview">
                <div class="photo-studio-compare"><figure><figcaption>Votre photo</figcaption><div><img id="photo-studio-source" alt="Photo originale du produit"></div></figure><figure><figcaption id="photo-studio-result-label">Aperçu à préparer</figcaption><div><img id="photo-studio-result" alt="Aperçu du rendu choisi"></div></figure></div>
                <p class="photo-studio-note">Vérifiez les contours, les couleurs, les étiquettes et les détails du produit. Les décors générés peuvent comporter des imperfections.</p>
            </div>
            <div class="photo-studio-controls">
                <label for="photo-studio-source-select">1. La photo à retoucher</label><select id="photo-studio-source-select" class="input"></select>
                <fieldset id="photo-studio-styles"><legend>2. Le rendu souhaité</legend>
                    @foreach(['retouch'=>['Retouche légère','Conserve le décor, contraste léger et cadrage carré.'],'studio_white'=>['Fond blanc','Un packshot simple pour le catalogue.'],'studio_ambient'=>['Fond ivoire','Un fond neutre et chaleureux.'],'studio_dark'=>['Fond graphite','Un fond sombre pour faire ressortir le produit.'],'lifestyle'=>['Décor IA personnalisé','Décrivez un environnement adapté à votre article.']] as $value=>$labels)
                    <label class="photo-studio-style"><input type="radio" name="photo_studio_style" value="{{ $value }}" @checked($value === 'retouch') @disabled($value === 'lifestyle' && !$sceneAvailable)><span><strong>{{ $labels[0] }}</strong><small>{{ $labels[1] }}</small></span></label>
                    @endforeach
                </fieldset>
                <div id="photo-studio-scene-field" hidden><label for="photo-studio-scene">Le décor, la lumière, l’ambiance</label><textarea id="photo-studio-scene" class="input" maxlength="500" rows="3" placeholder="Ex. Pour ce parfum : une table en pierre claire, une lumière de fenêtre et un fond beige épuré."></textarea></div>
                @unless($sceneAvailable)<p class="photo-studio-note">Décors IA disponibles avec Photoroom configuré et une formule compatible.</p>@endunless
                <p class="photo-studio-note">Chaque préparation : 12 crédits IA. Le détourage ou le décor utilise aussi 1 crédit Photoroom lorsqu’il est disponible. Un échec de préparation restitue les crédits réservés.</p>
                <button type="button" class="btn btn-primary" id="photo-studio-generate">Préparer l’aperçu · 12 crédits IA</button>
                <p id="photo-studio-status" role="status" aria-live="polite"></p>
            </div>
        </div>
        <footer class="photo-studio-footer"><button type="button" class="btn btn-ghost" id="photo-studio-original">Conserver ma photo</button><button type="button" class="btn btn-primary" id="photo-studio-apply" disabled>Utiliser ce rendu</button></footer>
    </section>
</div>
<script src="{{ asset('js/product-photo-studio.js') }}?v={{ filemtime(public_path('js/product-photo-studio.js')) }}" defer></script>
