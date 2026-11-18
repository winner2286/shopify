@php
    $photoShop = auth()->user()?->shop;
    $openAiScenes = config('services.product_studio.scene_provider', 'openai') === 'openai';
    $sceneProviderAvailable = $photoShop && ($openAiScenes
        ? app(\App\Services\Ai\OpenAiProductSceneService::class)->isConfigured()
        : $photoShop->canUsePhotoroomGenerativeScene() && app(\App\Services\Ai\PhotoroomService::class)->isConfigured());
    $sceneRemaining = $openAiScenes ? ($photoShop?->studioScenesRemaining() ?? 0) : ($photoShop?->photoroomCreditsRemaining() ?? 0);
    $sceneAvailable = $sceneProviderAvailable && $sceneRemaining > 0;
@endphp
<link rel="stylesheet" href="{{ asset('css/product-photo-studio.css') }}?v={{ filemtime(public_path('css/product-photo-studio.css')) }}">
<div id="product-photo-studio" class="photo-studio-backdrop" hidden data-url="{{ route('ai.products.verify_quality') }}" data-scene-available="{{ $sceneAvailable ? 'true' : 'false' }}" data-scene-provider="{{ $openAiScenes ? 'openai' : 'photoroom' }}" data-scenes-remaining="{{ $sceneRemaining }}">
    <section class="photo-studio-dialog" role="dialog" aria-modal="true" aria-labelledby="photo-studio-title" tabindex="-1">
        <header class="photo-studio-head"><div><h2 id="photo-studio-title">Studio photo produit</h2><p>Recadrez votre photo et comparez le rendu avant de l’utiliser.</p></div><button type="button" class="btn btn-ghost" id="photo-studio-close" aria-label="Fermer le studio">✕</button></header>
        <div class="photo-studio-layout">
            <div class="photo-studio-preview">
                <div class="photo-studio-compare"><figure><figcaption>Photo originale</figcaption><div><img id="photo-studio-source" alt="Photo originale du produit"></div></figure><figure><figcaption id="photo-studio-result-label">Rendu du studio</figcaption><div><img id="photo-studio-result" alt="Aperçu du rendu choisi" hidden><span id="photo-studio-result-empty">L’aperçu apparaîtra ici</span></div></figure></div>
                <p class="photo-studio-note" id="photo-studio-compare-note" hidden></p>
            </div>
            <div class="photo-studio-controls">
                <label for="photo-studio-source-select">La photo à retoucher</label><select id="photo-studio-source-select" class="input"></select>
                <details id="photo-studio-crop-editor"><summary>Recadrer et retirer les bords</summary>
                    <p class="photo-studio-note">Ajustez chaque bord pour garder le produit entier et écarter ce qui gêne.</p>
                    <canvas id="photo-studio-crop-canvas" class="photo-studio-crop-preview" aria-label="Aperçu du recadrage"></canvas>
                    <fieldset id="photo-studio-crop-fields" class="photo-studio-sliders">
                        @foreach(['top' => 'Haut', 'right' => 'Droite', 'bottom' => 'Bas', 'left' => 'Gauche'] as $side => $label)
                            <label>{{ $label }} <input id="photo-studio-crop-{{ $side }}" type="range" min="0" max="35" value="0"><output>0 %</output></label>
                        @endforeach
                        <button type="button" class="btn btn-ghost" id="photo-studio-crop-reset">Réinitialiser le cadre</button>
                    </fieldset>
                </details>
                <details id="photo-studio-style-picker"><summary id="photo-studio-style-summary">Choisir le rendu</summary><fieldset id="photo-studio-styles"><legend>Choisir le rendu</legend>
                    @foreach(['lifestyle'=>['Mise en scène IA',$openAiScenes ? 'Un décor et une lumière composés autour de votre produit.' : 'Un décor inédit à partir de votre description. Quota Photoroom nécessaire.'],'retouch'=>['Recadrage et retouche','Ajuste le cadrage, la lumière et les détails de la photo.'],'studio_white'=>['Fond blanc','Une photo nette pour le catalogue.'],'studio_ambient'=>['Fond ivoire','Un fond neutre et chaleureux.'],'studio_dark'=>['Fond graphite','Un fond sombre pour faire ressortir le produit.'],'scene_template'=>['Décor du studio','Choisissez un décor photographique prêt à l’emploi.'],'custom_background'=>['Mon propre décor','Importez une photo de chambre, de boutique ou un autre fond.']] as $value=>$labels)
                    <label class="photo-studio-style"><input type="radio" name="photo_studio_style" value="{{ $value }}" @checked($value === (($openAiScenes && $sceneAvailable) ? 'lifestyle' : 'retouch')) @disabled($value === 'lifestyle' && !$sceneAvailable)><span><strong>{{ $labels[0] }}</strong><small>{{ $labels[1] }}</small></span></label>
                    @endforeach
                </fieldset></details>
                <div id="photo-studio-decor-field" hidden><label for="photo-studio-decor">Votre décor</label><select id="photo-studio-decor" class="input">@foreach(\App\Services\Ai\ProductPhotoComposer::SCENES as $key=>$decor)<option value="{{ $key }}" data-x="{{ $decor['x'] }}" data-y="{{ $decor['y'] }}" data-scale="{{ $decor['scale'] }}" data-shadow="{{ $decor['shadow'] ?? 22 }}" data-image="{{ asset('images/ai_scenes/'.$decor['file']) }}">{{ $decor['title'] }}</option>@endforeach</select><img id="photo-studio-decor-preview" class="photo-studio-decor-thumb" alt="Décor photographique sélectionné"></div>
                <div id="photo-studio-background-field" hidden><label for="photo-studio-background">Importer votre fond</label><input type="file" id="photo-studio-background" class="input" accept="image/jpeg,image/png,image/webp"><p class="photo-studio-note">JPG, PNG ou WebP · 5 Mo maximum. Le produit doit pouvoir être détouré, automatiquement ou avec la correction des contours.</p></div>
                <details id="photo-studio-adjustments"><summary>Lumière, détails et placement</summary><fieldset id="photo-studio-adjustment-fields" class="photo-studio-sliders">
                    <label>Luminosité <input id="photo-studio-brightness" type="range" min="-15" max="15" value="0"><output>0</output></label>
                    <label>Contraste <input id="photo-studio-contrast" type="range" min="-10" max="10" value="0"><output>0</output></label>
                    <label><input type="checkbox" id="photo-studio-sharpen"> Accentuer légèrement les détails</label>
                    <div id="photo-studio-placement" hidden><label>Taille du produit <input id="photo-studio-scale" type="range" min="20" max="85" value="68"><output>Auto</output></label><label>Position horizontale <input id="photo-studio-position-x" type="range" min="10" max="90" value="50"><output>Auto</output></label><label>Position verticale <input id="photo-studio-position-y" type="range" min="15" max="90" value="82"><output>Auto</output></label><label>Ombre de contact <input id="photo-studio-shadow" type="range" min="0" max="50" value="27"><output>Auto</output></label><button type="button" class="btn btn-ghost" id="photo-studio-placement-reset">Revenir au placement automatique</button></div>
                </fieldset></details>
                <details id="photo-studio-mask-editor"><summary>Corriger les contours du produit</summary><p class="photo-studio-note">Effacez ce qui gêne autour du produit. Le pinceau « Rétablir » remet les pixels de votre photo originale.</p><fieldset id="photo-studio-mask-tools"><button type="button" class="btn btn-ghost" id="photo-studio-mask-from-preview" hidden>Reprendre le détourage de l’aperçu</button><div class="photo-studio-mask-actions"><button type="button" class="btn btn-ghost" id="photo-studio-mask-erase" aria-pressed="true">Effacer</button><button type="button" class="btn btn-ghost" id="photo-studio-mask-restore" aria-pressed="false">Rétablir</button><button type="button" class="btn btn-ghost" id="photo-studio-mask-reset">Recommencer</button></div><label>Taille du pinceau <input type="range" id="photo-studio-brush" min="3" max="100" value="35"></label><div class="photo-studio-mask-board"><canvas id="photo-studio-mask-canvas" aria-label="Détourage manuel : effacer ou rétablir les contours" width="512" height="512"></canvas></div><button type="button" class="btn btn-ghost" id="photo-studio-mask-use" disabled>Utiliser ce détourage</button><button type="button" class="btn btn-ghost" id="photo-studio-mask-disable">Revenir au détourage automatique</button></fieldset></details>
                <div id="photo-studio-scene-field" hidden>
                    @if($openAiScenes)
                        <label for="photo-studio-presentation">Position du produit dans la scène</label>
                        <select id="photo-studio-presentation" class="input"><option value="auto">Automatique</option><option value="standing">Posé sur un support</option><option value="hanging">Suspendu à un portant</option><option value="flat">À plat, vu du dessus</option></select>
                    @endif
                    <label for="photo-studio-scene">Le décor, la lumière, l’ambiance</label><textarea id="photo-studio-scene" class="input" maxlength="500" rows="3" placeholder="Ex. Une table en pierre claire et une lumière douce de fenêtre."></textarea>@if($openAiScenes)<p class="photo-studio-note">Laissez vide pour proposer un décor selon votre article. Le moteur de génération reçoit une référence cadrée du produit pour créer le décor.</p>@endif
                </div>
                @if ($sceneProviderAvailable)
                    <p class="photo-studio-note"><strong id="photo-studio-scenes-remaining">{{ $sceneRemaining }}</strong> {{ $openAiScenes ? 'décors IA restants' : 'crédits Photoroom restants' }} sur votre compte.</p>
                @else
                    <p class="photo-studio-note">La génération de décors est actuellement indisponible. Vous pouvez utiliser les décors du studio.</p>
                @endif
                <p class="photo-studio-note">Le recadrage, les retouches et les décors préparés ne débitent aucun crédit IA. Un détourage effectué par Photoroom utilise un crédit Photoroom.</p>
            </div>
        </div>
        <footer class="photo-studio-footer">
            <p id="photo-studio-status" role="status" aria-live="polite"></p>
            <div class="photo-studio-actions">
                <button type="button" class="btn btn-ghost" id="photo-studio-original">Conserver ma photo</button>
                <button type="button" class="btn btn-primary" id="photo-studio-generate">Préparer l’aperçu</button>
                <button type="button" class="btn btn-primary" id="photo-studio-choose-decor" hidden>Choisir un décor du studio</button>
                <button type="button" class="btn btn-primary" id="photo-studio-apply" disabled hidden>Utiliser ce rendu</button>
            </div>
        </footer>
    </section>
</div>
<script src="{{ asset('js/product-photo-studio.js') }}?v={{ filemtime(public_path('js/product-photo-studio.js')) }}" defer></script>
