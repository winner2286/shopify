@extends('layouts.app')

@section('title', 'Étape 2 : Style & Marque - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:760px;padding:2.5rem 1rem 5rem">
    
    <!-- Wizard -->
    <div class="wizard-steps">
        <a href="{{ route('onboarding.step1') }}" class="wizard-step done" style="text-decoration:none">
            <span class="circle"><x-icon name="check" size="14" /></span>
            <span>Activité</span>
        </a>
        <div class="wizard-line" style="background:var(--ok)"></div>
        <div class="wizard-step active">
            <span class="circle">2</span>
            <span>Style</span>
        </div>
        <div class="wizard-line"></div>
        <div class="wizard-step">
            <span class="circle">3</span>
            <span>Photos</span>
        </div>
        <div class="wizard-line"></div>
        <div class="wizard-step">
            <span class="circle">4</span>
            <span>Génération</span>
        </div>
    </div>

    <div class="card panel">
        <h1 style="font-size:2rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.6rem">
            Personnalisez votre identité
            <x-icon name="palette" size="28" style="color:var(--brand-primary)" />
        </h1>
        <p style="color:var(--text-soft);margin-bottom:1.8rem">
            Définissez le nom, les couleurs et l'ambiance visuelle de votre future boutique en ligne.
        </p>

        @if ($errors->any())
            <div class="alert alert-err" role="alert">
                <ul style="margin:0;padding-left:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.step2.store') }}" data-once>
            @csrf

            <!-- Nom de la boutique -->
            <div class="field">
                <label for="suggested_name">Nom de votre boutique <span style="color:var(--danger)">*</span></label>
                <input id="suggested_name" name="suggested_name" type="text" class="input" value="{{ old('suggested_name', $shop->name ?? 'Maison Élégance') }}" placeholder="Ex: Lomé Chic, Wax & Co..." required>
                <div class="hint">Ce nom sera utilisé pour votre logo, votre adresse et vos factures.</div>
            </div>

            <!-- Numéro WhatsApp de la boutique -->
            <div class="field" style="margin-top:1.4rem">
                <label for="whatsapp" style="display:flex;align-items:center;gap:.4rem">
                    <x-icon name="whatsapp" size="18" style="color:#25D366" />
                    Numéro WhatsApp de la boutique (pour recevoir vos alertes de commandes)
                </label>
                <input id="whatsapp" name="whatsapp" type="text" class="input" value="{{ old('whatsapp', $shop->whatsapp ?? '') }}" placeholder="Ex: +228 90 12 34 56">
                <div class="hint" style="color:var(--text-soft)">Votre numéro pour recevoir les alertes de commandes avec récapitulatif instantané et discuter avec vos clients.</div>
            </div>

            <!-- Localisation & Adresse physique de la boutique -->
            <div class="field" style="margin-top:1.4rem;padding:1.25rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm)">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;margin-bottom:.5rem">
                    <label style="font-weight:700;display:flex;align-items:center;gap:.4rem;margin-bottom:0">
                        <x-icon name="map-pin" size="18" style="color:var(--brand-primary)" />
                        Localisation de votre boutique physique (facultatif)
                    </label>
                    <button type="button" id="btn-geolocation" class="btn btn-ghost btn-sm" onclick="useCurrentLocation()" style="padding:.35rem .85rem;font-size:.82rem;display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--brand-primary);color:var(--brand-primary);border-radius:20px;cursor:pointer;background:transparent" title="Détecter automatiquement la position GPS de votre magasin">
                        <x-icon name="map-pin" :size="14" />
                        <span id="geo-btn-text">📍 Utiliser ma position actuelle</span>
                    </button>
                </div>
                <div class="hint" style="color:var(--text-soft);margin-bottom:.9rem">
                    Permettez à vos futurs clients de localiser facilement votre magasin ou point de vente sur Google Maps dès que votre boutique sera publiée en ligne.
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem">
                    <div>
                        <label for="address" style="font-size:.85rem;font-weight:600;color:var(--text-soft)">Quartier / Adresse précise</label>
                        <input id="address" name="address" type="text" class="input" value="{{ old('address', $shop->address ?? '') }}" placeholder="Ex: Tokoin Doumasséssé, carrefour Rex...">
                    </div>
                    <div>
                        <label for="city" style="font-size:.85rem;font-weight:600;color:var(--text-soft)">Ville</label>
                        <input id="city" name="city" type="text" class="input" value="{{ old('city', $shop->city ?? 'Lomé') }}" placeholder="Lomé">
                    </div>
                </div>

                <input type="hidden" id="maps_url" name="maps_url" value="{{ old('maps_url', $shop->maps_url ?? '') }}">

                <!-- Carte interactive et statut GPS -->
                <div id="geo-feedback-box" style="display:{{ old('maps_url', $shop->maps_url ?? '') ? 'block' : 'none' }};margin-top:.85rem;padding:.75rem;background:var(--surface);border:1px solid var(--border);border-radius:8px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem;flex-wrap:wrap">
                        <span style="font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.35rem;color:var(--ok)">
                            <x-icon name="check-circle" :size="15" />
                            <span id="geo-status-msg">Position GPS enregistrée avec succès</span>
                        </span>
                        <a id="geo-maps-link" href="{{ old('maps_url', $shop->maps_url ?? '#') }}" target="_blank" style="font-size:.8rem;color:var(--brand-primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:3px">
                            <x-icon name="map" :size="13" />
                            <span>Ouvrir dans Google Maps ↗</span>
                        </a>
                    </div>
                    <div style="height:150px;width:100%;border-radius:6px;overflow:hidden;border:1px solid var(--border);position:relative">
                        <iframe id="geo-map-iframe" style="width:100%;height:100%;border:none;background:#f8fafc" loading="lazy" src=""></iframe>
                    </div>
                </div>
            </div>

            <!-- Public visé -->
            <div class="field" style="margin-top:1.4rem">
                <label for="target_audience">À qui s'adressent vos produits ?</label>
                <input id="target_audience" name="target_audience" type="text" class="input" value="{{ old('target_audience', $shop->target_audience ?? 'Jeunes actifs, femmes élégantes, professionnels') }}" placeholder="Ex: Familles, étudiants, amateurs de mode...">
            </div>

            <!-- Ambiance / Style visuel -->
            <div class="field" style="margin-top:1.6rem">
                <label>Style visuel souhaité <span style="color:var(--danger)">*</span></label>
                @php $currentStyle = old('visual_style', $shop->visual_style ?? 'moderne'); @endphp
                <div class="choice-grid">
                    @foreach ([
                        ['moderne', 'rocket', 'Moderne', 'Épuré, violet néon, high-tech'],
                        ['elegant', 'crown', 'Élégant', 'Doré, noir profond, luxe'],
                        ['colore', 'flower', 'Coloré', 'Vibrant, festif, chaleureux'],
                        ['minimaliste', 'sun', 'Minimaliste', 'Sobre, noir et blanc, essentiel'],
                        ['artisanal', 'craft', 'Artisanal', 'Terre cuite, naturel, authentique'],
                    ] as [$style, $ico, $label, $sub])
                        <label class="choice-card {{ $currentStyle === $style ? 'selected' : '' }}">
                            <input type="radio" name="visual_style" value="{{ $style }}" @checked($currentStyle === $style) onchange="document.querySelectorAll('.choice-card').forEach(c=>c.classList.remove('selected')); this.closest('.choice-card').classList.add('selected');">
                            <span class="icon"><x-icon :name="$ico" size="24" /></span>
                            <span>{{ $label }}</span>
                            <div style="font-size:.75rem;color:var(--text-faint);margin-top:.2rem">{{ $sub }}</div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Couleur de marque -->
            <div class="field" style="margin-top:1.6rem">
                <label for="brand_color">Couleur principale de marque</label>
                <div style="display:flex;align-items:center;gap:1rem;margin-top:.4rem">
                    <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $shop->brand_color ?? '#6d3df2') }}" style="width:50px;height:46px;border-radius:10px;border:1px solid var(--border);cursor:pointer;background:transparent;">
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        @foreach (['#6d3df2', '#00d2ff', '#d4af37', '#ff007a', '#10b981', '#b45309'] as $c)
                            <button type="button" style="width:34px;height:34px;border-radius:50%;background:{{ $c }};border:2px solid var(--border);cursor:pointer" onclick="document.getElementById('brand_color').value='{{ $c }}';"></button>
                        @endforeach
                    </div>
                </div>
                <div class="hint">Cette couleur sera utilisée pour vos boutons, vos bannières et votre logo.</div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2.5rem">
                <a href="{{ route('onboarding.step1') }}" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem">
                    <x-icon name="arrow-left" size="16" />
                    Précédent
                </a>
                <button id="step2-submit" type="submit" class="btn btn-primary" style="min-width:200px;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                    Continuer : Photos
                    <x-icon name="arrow-right" size="16" />
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function useCurrentLocation() {
    var btn = document.getElementById('btn-geolocation');
    var btnText = document.getElementById('geo-btn-text');
    var addressInput = document.getElementById('address');
    var cityInput = document.getElementById('city');
    var mapsUrlInput = document.getElementById('maps_url');
    var feedbackBox = document.getElementById('geo-feedback-box');
    var statusMsg = document.getElementById('geo-status-msg');
    var mapsLink = document.getElementById('geo-maps-link');
    var mapIframe = document.getElementById('geo-map-iframe');

    if (!navigator.geolocation) {
        alert("La géolocalisation n'est pas supportée par votre navigateur.");
        return;
    }

    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = "📍 Détection en cours...";

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            var lat = pos.coords.latitude.toFixed(6);
            var lng = pos.coords.longitude.toFixed(6);
            var accuracy = Math.round(pos.coords.accuracy);

            var mapsUrl = "https://www.google.com/maps?q=" + lat + "," + lng;
            if (mapsUrlInput) mapsUrlInput.value = mapsUrl;

            var embedUrl = "https://www.openstreetmap.org/export/embed.html?bbox=" + (parseFloat(lng) - 0.005) + "%2C" + (parseFloat(lat) - 0.004) + "%2C" + (parseFloat(lng) + 0.005) + "%2C" + (parseFloat(lat) + 0.004) + "&layer=mapnik&marker=" + lat + "%2C" + lng;

            if (mapsLink) mapsLink.href = mapsUrl;
            if (mapIframe) mapIframe.src = embedUrl;
            if (feedbackBox) feedbackBox.style.display = 'block';

            // Géocodage inverse via OpenStreetMap Nominatim
            fetch("https://nominatim.openstreetmap.org/reverse?format=json&lat=" + lat + "&lon=" + lng + "&zoom=18&addressdetails=1", {
                headers: { 'Accept': 'application/json' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.address) {
                    var a = data.address;
                    var place = a.suburb || a.neighbourhood || a.quarter || a.road || a.residential;
                    var city = a.city || a.town || a.village || a.county || a.state || 'Lomé';
                    if (place && (!addressInput.value.trim() || addressInput.value.includes('Position GPS'))) {
                        addressInput.value = place;
                    }
                    if (city && cityInput) {
                        cityInput.value = city;
                    }
                    if (statusMsg) statusMsg.textContent = "Position GPS détectée (" + (place || 'Boutique') + ", ±" + accuracy + "m)";
                } else {
                    if (!addressInput.value.trim()) {
                        addressInput.value = "Position GPS (" + lat + ", " + lng + ")";
                    }
                    if (statusMsg) statusMsg.textContent = "Position GPS détectée (±" + accuracy + "m)";
                }
            })
            .catch(function () {
                if (!addressInput.value.trim()) {
                    addressInput.value = "Position GPS (" + lat + ", " + lng + ")";
                }
                if (statusMsg) statusMsg.textContent = "Position GPS détectée (±" + accuracy + "m)";
            })
            .finally(function () {
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = "✓ Position actualisée (Recalculer)";
            });
        },
        function (err) {
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = "📍 Utiliser ma position actuelle";

            var msg = "Impossible d'obtenir votre position GPS.";
            if (err.code === 1) {
                msg = "Veuillez autoriser l'accès à votre position dans le navigateur pour activer la localisation.";
            } else if (err.code === 2) {
                msg = "Signal GPS indisponible. Veuillez saisir votre adresse manuellement.";
            } else if (err.code === 3) {
                msg = "Délai de géolocalisation dépassé. Veuillez réessayer.";
            }
            alert(msg);
        },
        {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
        }
    );
}

document.addEventListener('DOMContentLoaded', function() {
    var mapsUrl = document.getElementById('maps_url')?.value;
    if (mapsUrl) {
        var match = mapsUrl.match(/q=([-\d.]+),([-\d.]+)/);
        if (match) {
            var lat = match[1];
            var lng = match[2];
            var embedUrl = "https://www.openstreetmap.org/export/embed.html?bbox=" + (parseFloat(lng) - 0.005) + "%2C" + (parseFloat(lat) - 0.004) + "%2C" + (parseFloat(lng) + 0.005) + "%2C" + (parseFloat(lat) + 0.004) + "&layer=mapnik&marker=" + lat + "%2C" + lng;
            var mapIframe = document.getElementById('geo-map-iframe');
            if (mapIframe) mapIframe.src = embedUrl;
        }
    }
});
</script>
@endsection
