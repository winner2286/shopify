<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $shop->name }} : Boutique temporairement en pause</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Outfit:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 2rem 1rem;
        }
        .m-card {
            max-width: 520px; width: 100%; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            padding: 3rem 2rem; border-radius: var(--radius);
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .m-logo {
            width: 72px; height: 72px; border-radius: 18px;
            background: {{ $shop->brand_color ?: '#6d3df2' }};
            color: #fff; font-size: 2rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 40%, transparent);
        }
    </style>
</head>
<body>
    <div class="m-card">
        <div class="m-logo">
            {{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}
        </div>
        <h1 style="font-size:1.8rem;margin-bottom:.5rem">{{ $shop->name }}</h1>
        <p style="color:var(--warn);font-weight:600;font-size:1.05rem;margin-bottom:1rem">
            Notre boutique prend une courte pause !
        </p>
        <p style="color:var(--text-soft);margin-bottom:2rem;line-height:1.6">
            Nous préparons de nouveaux arrivages et mettons à jour notre catalogue. Nous serons de retour très prochainement.
        </p>

        @if ($shop->whatsapp_formatted || $shop->phone)
            <div style="background:var(--bg-soft);padding:1.5rem;border-radius:var(--radius-sm);margin-bottom:1.5rem">
                <div style="font-size:.9rem;font-weight:600;margin-bottom:.8rem">Besoin d'une commande urgente ?</div>
                <div style="display:flex;flex-direction:column;gap:.6rem">
                    @if ($shop->whatsapp_formatted)
                        <a href="https://wa.me/{{ $shop->whatsapp_formatted }}?text={{ urlencode('Bonjour '.$shop->name.', je visite votre boutique en ligne et souhaite des renseignements.') }}" target="_blank" class="btn btn-primary" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                            <x-icon name="whatsapp" size="18" /> Nous écrire sur WhatsApp
                        </a>
                    @endif
                    @if ($shop->phone)
                        <a href="tel:{{ $shop->phone }}" class="btn btn-ghost" style="display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                            <x-icon name="phone" size="16" /> Appeler : {{ $shop->phone }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        @if ($shop->address || $shop->city)
            <div style="background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);margin-bottom:1.5rem;font-size:.88rem">
                <div style="font-weight:600;display:flex;align-items:center;justify-content:center;gap:.35rem;margin-bottom:.3rem">
                    <x-icon name="map-pin" size="15" />
                    <span>Notre magasin physique reste ouvert</span>
                </div>
                <div style="color:var(--text-soft)">{{ $shop->address ? $shop->address . ', ' : '' }}{{ $shop->city ?: 'Lomé, Togo' }}</div>
                @if ($shop->maps_url)
                    <div style="margin-top:.6rem">
                        <a href="{{ $shop->maps_url }}" target="_blank" rel="noopener noreferrer" style="font-size:.82rem;color:var(--brand-primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
                            <x-icon name="map" size="13" />
                            <span>Itinéraire Google Maps ↗</span>
                        </a>
                    </div>
                @endif
            </div>
        @endif

        <div style="font-size:.85rem;color:var(--text-faint)">
            Propulsé par <a href="{{ route('home') }}" style="color:var(--text);display:inline-flex;align-items:center;gap:.35rem"><img src="{{ asset('images/brand/02_icone_application.jpg') }}" alt="Zanika" style="height:16px;width:16px;border-radius:4px;vertical-align:middle"> <strong style="font-family:'Montserrat',sans-serif">Zanika</strong> <x-icon name="sparkles" size="12" /></a>
        </div>
    </div>
</body>
</html>
