@extends('layouts.app')

@section('title', 'Campagne Publicitaire : ' . $ad->channel_label . ' - ' . config('platform.name'))

@section('content')
<div class="container" style="max-width:960px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="sparkles" :size="20" /></span>
                <h1 style="font-size:1.8rem;margin:0">Publicité & Scripts Générés par l'IA</h1>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <span class="badge badge-ok">{{ $ad->channel_label }}</span>
                <span class="badge badge-draft">{{ $ad->objective_label }}</span>
                <span class="badge badge-draft">{{ $ad->tone_label }}</span>
                @if ($ad->product)
                    <span class="badge badge-draft">Produit : {{ $ad->product->title }}</span>
                @endif
            </div>
        </div>

        <div class="header-actions">
            <a href="{{ route('ads.create') }}" class="btn btn-primary btn-sm">+ Nouvelle publicité</a>
            <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="folder" :size="15" />
                <span>Bibliothèque</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Bloc 1 : Variantes de textes publicitaires (CDC H-02) -->
    <div style="margin-bottom:3rem">
        <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
            <span class="svg-icon" style="color:var(--brand-1)"><x-icon name="edit" :size="18" /></span>
            <span>3 Variantes de Textes Prêts à Publier (H-02)</span>
        </h2>
        <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
            Choisissez la variante qui correspond le mieux à votre style et copiez-la ou partagez-la directement.
        </p>

        <div style="display:grid;grid-template-columns:1fr;gap:1.5rem">
            @foreach ($ad->generated_texts as $index => $text)
                <div class="card" style="padding:1.5rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.6rem">
                        <strong style="font-size:.95rem;color:var(--brand-2)">Proposition #{{ $index + 1 }}</strong>
                        <div style="font-size:.8rem;color:var(--text-soft)">{{ mb_strlen($text) }} caractères</div>
                    </div>

                    <div id="ad-text-{{ $index }}" style="font-size:1rem;line-height:1.7;color:var(--text);white-space:pre-line;background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);margin-bottom:1.2rem;border:1px solid var(--border)">
                        {{ $text }}
                    </div>

                    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('ad-text-{{ $index }}').innerText); alert('Texte copié dans le presse-papier !');" style="display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="copy" :size="15" />
                            <span>Copier le texte</span>
                        </button>

                        <a href="https://wa.me/?text={{ urlencode($text) }}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="whatsapp" :size="16" />
                            <span>Partager sur mon statut WhatsApp</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Bloc 2 : Scripts de vidéos courtes TikTok / Reels (CDC H-04) -->
    @if (! empty($ad->generated_scripts))
        <div style="margin-bottom:3rem">
            <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="video" :size="20" /></span>
                <span>Idées & Scripts de Vidéos TikTok / Reels (H-04)</span>
            </h2>
            <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
                Tournez facilement une vidéo vendeuse de 15 à 25 secondes avec votre téléphone pour faire exploser votre visibilité.
            </p>

            <div style="display:grid;grid-template-columns:1fr;gap:1.5rem">
                @foreach ($ad->generated_scripts as $script)
                    <div class="card" style="padding:1.5rem">
                        <h3 style="margin-top:0;font-size:1.15rem">{{ $script['title'] }}</h3>
                        <p style="font-size:.9rem;color:var(--text-soft);margin-bottom:1.2rem;display:flex;align-items:center;gap:.4rem">
                            <x-icon name="sparkles" :size="14" style="color:var(--brand-2)" />
                            <em>{{ $script['concept'] }}</em>
                        </p>

                        <div style="display:flex;flex-direction:column;gap:1rem">
                            @foreach ($script['steps'] as $step)
                                <div style="display:flex;gap:1rem;align-items:start;background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);border:1px solid var(--border)">
                                    <span style="font-weight:700;font-family:monospace;font-size:.85rem;background:var(--surface);padding:.2rem .6rem;border-radius:6px;flex-shrink:0">
                                        {{ $step['time'] }}
                                    </span>
                                    <div style="flex:1">
                                        <div style="font-size:.9rem;color:var(--text-soft);margin-bottom:.3rem;display:flex;align-items:center;gap:.4rem">
                                            <x-icon name="camera" :size="14" />
                                            <span><strong>Visuel caméra :</strong> {{ $step['visual'] }}</span>
                                        </div>
                                        <div style="font-size:.95rem;font-weight:600;color:var(--text);display:flex;align-items:center;gap:.4rem">
                                            <x-icon name="user" :size="14" />
                                            <span><strong>Voix off / Paroles :</strong> « {{ $step['voiceover'] }} »</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Bloc 3 : Carte Visuelle Réseaux Sociaux (CDC H-03) -->
    @if (! empty($ad->visual_config))
        @php $vc = $ad->visual_config; @endphp
        <div>
            <h2 style="font-size:1.35rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
                <span class="svg-icon" style="color:var(--brand-1)"><x-icon name="palette" :size="20" /></span>
                <span>Visuel Promotionnel pour Réseaux Sociaux (H-03)</span>
            </h2>
            <p style="color:var(--text-soft);margin-top:0;margin-bottom:1.5rem">
                Gabarit d'affiche promotionnelle aux couleurs de votre boutique, optimisé pour les stories et statuts.
            </p>

            <div style="display:flex;justify-content:center">
                <div style="width:340px;background:linear-gradient(135deg, {{ $vc['brand_color'] }}, #0d0d1a);border-radius:24px;padding:2rem 1.5rem;color:#fff;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.3);position:relative;overflow:hidden">
                    <div style="position:absolute;top:-40px;right:-40px;width:120px;height:120px;background:rgba(255,255,255,0.1);border-radius:50%"></div>

                    <div style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;font-weight:700;opacity:.8;margin-bottom:.5rem">
                        {{ $vc['shop_name'] }}
                    </div>

                    <div style="display:inline-block;background:var(--danger);color:#fff;font-size:.8rem;font-weight:700;padding:.3rem .8rem;border-radius:20px;margin-bottom:1.5rem">
                        {{ $vc['headline'] }}
                    </div>

                    <div style="width:160px;height:160px;background:rgba(255,255,255,0.15);border-radius:18px;margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;border:2px dashed rgba(255,255,255,0.3)">
                        <x-icon name="shopping-bag" :size="54" />
                    </div>

                    <h3 style="font-size:1.25rem;margin:0 0 .8rem;line-height:1.3">
                        {{ $vc['product_title'] }}
                    </h3>

                    <div style="background:rgba(0,0,0,0.3);padding:.8rem 1rem;border-radius:12px;display:inline-block;margin-bottom:1.5rem">
                        <span style="font-size:1.5rem;font-weight:700;font-family:var(--font-display)">
                            {{ $vc['price'] }}
                        </span>
                        @if ($vc['old_price'])
                            <span style="font-size:.9rem;opacity:.7;text-decoration:line-through;margin-left:.5rem">
                                {{ $vc['old_price'] }}
                            </span>
                        @endif
                    </div>

                    <div style="font-size:.85rem;opacity:.9;display:flex;align-items:center;justify-content:center;gap:.4rem">
                        <x-icon name="whatsapp" :size="15" />
                        <span>Commande rapide WhatsApp : <strong>{{ $vc['phone'] ?: 'Contact en bio' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
