@extends('layouts.app')

@section('title', 'Paramètres & Publication de la boutique : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:960px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <h1 style="font-size:1.8rem;margin:0">Paramètres de la boutique</h1>
                @if ($shop->isPublished())
                    <span class="badge badge-ok"><span class="dot"></span> En ligne</span>
                @elseif ($shop->isPaused())
                    <span class="badge badge-warn">En pause</span>
                @else
                    <span class="badge badge-draft">Brouillon</span>
                @endif
            </div>
            <p style="margin:0;color:var(--text-soft)">Gérez vos coordonnées, personnalisez l'apparence et publiez votre vitrine.</p>
        </div>

        <div class="header-actions">
            @if ($shop->subdomain)
                <a href="{{ url('/b/' . $shop->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem" title="Voir ma vitrine en direct">
                    <x-icon name="eye" size="14" />
                    <span class="btn-full-text">Prévisualiser la vitrine</span>
                    <span class="btn-short-text">Vitrine</span>
                    <x-icon name="external-link" size="12" />
                </a>
            @endif
            <a href="{{ route('shop.pages.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem" title="Gérer les pages d'information">
                <x-icon name="file-text" size="14" />
                <span class="btn-full-text">Pages d'information</span>
                <span class="btn-short-text">Pages d'info</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Bloc 1 : Statut de Publication & Checklist (B-07, B-09, RG-09, RG-13) -->
    <div class="card" style="margin-bottom:2rem;background:linear-gradient(135deg, color-mix(in srgb, var(--surface) 90%, transparent), color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 10%, transparent));border-color:color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 40%, var(--border))">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.5rem">
            <div style="flex:1;min-width:280px">
                <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                    <x-icon name="rocket" size="20" style="color:var(--brand-primary)" />
                    Publication & Partage
                </h3>

                @if ($shop->isPublished())
                    <p style="color:var(--ok);font-weight:600;margin-bottom:1rem;display:inline-flex;align-items:center;gap:.4rem">
                        <x-icon name="check-circle" size="16" />
                        Votre boutique est actuellement en ligne et visible par tous les clients !
                    </p>

                    <div style="margin-bottom:1.2rem">
                        <label class="label" style="font-size:.85rem">Lien direct de votre boutique :</label>
                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                            <input id="store-url-input" type="text" readonly value="{{ $shop->public_url }}" class="input" style="flex:1;min-width:240px;background:var(--bg-soft);font-weight:600">
                            <button type="button" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem" onclick="navigator.clipboard.writeText(document.getElementById('store-url-input').value); alert('Lien copié dans le presse-papier !');">
                                <x-icon name="copy" size="14" /> Copier le lien
                            </button>
                            <a href="https://wa.me/?text={{ urlencode('Bonjour, venez découvrir notre nouvelle boutique en ligne « '.$shop->name.' » sur Boutiq : ' . $shop->public_url) }}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:.4rem">
                                <x-icon name="whatsapp" size="16" /> Partager sur WhatsApp
                            </a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('shop.toggle_pause') }}" onsubmit="return confirm('Mettre votre boutique en pause ? Les visiteurs verront un message de maintenance.');">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--warn)">
                            Mettre la boutique en pause temporaire
                        </button>
                    </form>

                @elseif ($shop->isPaused())
                    <div style="background:color-mix(in srgb, var(--warn) 15%, transparent);border:1px solid var(--warn);padding:1rem;border-radius:var(--radius-sm);margin-bottom:1.2rem">
                        <p style="margin:0;font-weight:600;color:var(--warn)">
                            Votre boutique est en pause.
                        </p>
                        <p style="margin:.3rem 0 0;font-size:.9rem;color:var(--text-soft)">
                            Les visiteurs extérieurs voient la page de maintenance avec vos coordonnées. Vous pouvez continuer à la prévisualiser et réactiver les ventes dès que vous êtes prêt.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('shop.toggle_pause') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            Réactiver et publier ma boutique
                        </button>
                    </form>

                @else
                    <!-- Statut Brouillon : Checklist de publication -->
                    <p style="margin-bottom:1rem;font-size:.95rem">
                        Pour ouvrir votre boutique au public, veuillez valider les étapes indispensables ci-dessous :
                    </p>

                    <div style="background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm);margin-bottom:1.2rem">
                        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.6rem">
                            @foreach ($checklist['checks'] as $check)
                                <li style="display:flex;align-items:center;gap:.6rem;font-size:.92rem">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:{{ $check['passed'] ? 'var(--ok)' : 'var(--border)' }};color:#fff;font-weight:bold;font-size:.8rem">
                                        @if ($check['passed'])
                                            <x-icon name="check" size="14" />
                                        @else
                                            <x-icon name="x" size="14" />
                                        @endif
                                    </span>
                                    <span style="{{ $check['passed'] ? 'font-weight:600' : 'color:var(--text-soft)' }}">
                                        {{ $check['label'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($checklist['can_publish'])
                        <form method="POST" action="{{ route('shop.publish') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="font-size:1.05rem;padding:.75rem 1.6rem;display:inline-flex;align-items:center;gap:.5rem">
                                <x-icon name="rocket" size="18" /> Publier ma boutique maintenant
                            </button>
                        </form>
                    @else
                        <div style="font-size:.9rem;color:var(--warn);display:flex;align-items:center;gap:.5rem">
                            <span style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="alert-triangle" size="16" style="color:var(--warn)" /> Complétez les éléments ci-dessus pour pouvoir publier.</span>
                            @if (! ($checklist['checks']['products']['passed'] ?? false))
                                <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm" style="text-decoration:underline;display:inline-flex;align-items:center;gap:.3rem">
                                    Fixer le prix d'un produit <x-icon name="arrow-right" size="14" />
                                </a>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            <!-- Aperçu de marque -->
            <div style="width:240px;background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);text-align:center;border:1px solid var(--border)">
                <div style="width:64px;height:64px;border-radius:18px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 8px 24px {{ $shop->brand_color ?: '#6d3df2' }}44;background:{{ $shop->brand_color ?: '#6d3df2' }}">
                    @if (!empty($shop->onboarding_data['chosen_logo']['svg']))
                        <div style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;">
                            {!! str_contains($shop->onboarding_data['chosen_logo']['svg'], 'width=') ? $shop->onboarding_data['chosen_logo']['svg'] : str_replace('<svg ', '<svg width="64" height="64" style="width:64px;height:64px;max-width:64px;max-height:64px;display:block;" ', $shop->onboarding_data['chosen_logo']['svg']) !!}
                        </div>
                    @elseif ($shop->logo_path)
                        <img src="{{ asset('storage/' . $shop->logo_path) }}" alt="{{ $shop->name }}" style="width:100%;height:100%;object-fit:cover;display:block">
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#fff">
                            <x-icon name="sparkles" size="28" />
                        </div>
                    @endif
                </div>
                <div style="font-weight:700;font-size:1.1rem;margin-bottom:.3rem">{{ $shop->name ?: 'Ma Boutique' }}</div>
                <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.8rem">
                    {{ $shop->business_category ? ucfirst($shop->business_category) : 'Commerce' }}
                </div>
                <div style="font-size:.8rem;padding:.3rem .6rem;background:var(--surface);border-radius:20px;display:inline-block">
                    Couleur : <strong style="color:{{ $shop->brand_color ?: '#6d3df2' }}">{{ $shop->brand_color ?: '#6d3df2' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Bloc 2 : Formulaire de Paramètres -->
    <div class="card">
        <h2 style="font-size:1.3rem;margin-top:0;margin-bottom:1.5rem">Informations Générales & Contact</h2>

        <form method="POST" action="{{ route('shop.settings.update') }}">
            @csrf

            <div class="form-grid-2">
                <div>
                    <label for="name" class="label">Nom de la boutique *</label>
                    <input id="name" type="text" name="name" class="input" value="{{ old('name', $shop->name) }}" required maxlength="100">
                </div>

                <div>
                    <label for="subdomain" class="label">Sous-domaine / Adresse web *</label>
                    <div style="display:flex;align-items:center;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden">
                        <span style="padding:0 .8rem;color:var(--text-soft);font-size:.9rem;white-space:nowrap">/b/</span>
                        <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain', $shop->subdomain) }}" required maxlength="30" pattern="^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$" style="border:none;background:transparent;padding:.75rem .5rem;flex:1;color:var(--text);font-family:monospace;font-size:1rem" placeholder="ma-boutique">
                    </div>
                    <p class="hint" style="margin-top:.3rem">Lettres minuscules, chiffres et tirets uniquement (3 à 30 caractères).</p>
                </div>
            </div>

            <div style="margin-top:1.2rem">
                <label for="description" class="label">Slogan ou brève description</label>
                <textarea id="description" name="description" class="input" rows="3" maxlength="500" placeholder="Ex. Votre référence à Lomé pour les vêtements et accessoires tendance.">{{ old('description', $shop->description) }}</textarea>
            </div>

            <div class="form-grid-2" style="margin-top:1.2rem">
                <div>
                    <label for="business_category" class="label">Secteur d'activité</label>
                    <select id="business_category" name="business_category" class="input">
                        @php $cat = old('business_category', $shop->business_category); @endphp
                        <option value="mode" {{ $cat === 'mode' ? 'selected' : '' }}>Mode, Vêtements & Chaussures</option>
                        <option value="beaute" {{ $cat === 'beaute' ? 'selected' : '' }}>Beauté, Cosmétiques & Parfums</option>
                        <option value="electronique" {{ $cat === 'electronique' ? 'selected' : '' }}>Électronique & High-Tech</option>
                        <option value="alimentation" {{ $cat === 'alimentation' ? 'selected' : '' }}>Alimentation, Épicerie & Boissons</option>
                        <option value="maison" {{ $cat === 'maison' ? 'selected' : '' }}>Maison, Déco & Mobilier</option>
                        <option value="autre" {{ $cat === 'autre' ? 'selected' : '' }}>Autre commerce général</option>
                    </select>
                </div>

                <div>
                    <label for="brand_color" class="label">Couleur de marque principale *</label>
                    <div style="display:flex;gap:.8rem;align-items:center">
                        <input id="brand_color_picker" type="color" value="{{ old('brand_color', $shop->brand_color ?: '#6d3df2') }}" style="width:48px;height:44px;border:none;border-radius:8px;cursor:pointer;background:none" oninput="document.getElementById('brand_color').value = this.value">
                        <input id="brand_color" type="text" name="brand_color" class="input" value="{{ old('brand_color', $shop->brand_color ?: '#6d3df2') }}" required style="font-family:monospace;width:120px" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) document.getElementById('brand_color_picker').value = this.value">
                    </div>
                </div>
            </div>

            <h3 style="font-size:1.15rem;margin:2rem 0 1rem;border-top:1px solid var(--border);padding-top:1.5rem;display:flex;align-items:center;gap:.5rem">
                <x-icon name="phone" size="18" style="color:var(--brand-primary)" />
                Coordonnées de Contact & Localisation
            </h3>

            <div class="form-grid-2">
                <div>
                    <label for="whatsapp" class="label">Numéro WhatsApp (recommandé)</label>
                    <input id="whatsapp" type="text" name="whatsapp" class="input" value="{{ old('whatsapp', $shop->whatsapp) }}" placeholder="Ex. +228 90 00 00 00">
                    <p class="hint">Vos clients pourront vous contacter et passer commande en 1 clic via WhatsApp.</p>
                </div>

                <div>
                    <label for="phone" class="label">Téléphone d'appel</label>
                    <input id="phone" type="text" name="phone" class="input" value="{{ old('phone', $shop->phone) }}" placeholder="Ex. +228 91 00 00 00">
                </div>
            </div>

            <div class="form-grid-2" style="margin-top:1.2rem">
                <div>
                    <label for="email" class="label">Email de contact</label>
                    <input id="email" type="email" name="email" class="input" value="{{ old('email', $shop->email_contact) }}" placeholder="contact@maboutique.tg">
                </div>

                <div>
                    <label for="city" class="label">Ville / Commune</label>
                    <input id="city" type="text" name="city" class="input" value="{{ old('city', $shop->city) }}" placeholder="Lomé, Togo">
                </div>
            </div>

            <div style="margin-top:1.2rem">
                <label for="address" class="label">Adresse physique / Quartier</label>
                <input id="address" type="text" name="address" class="input" value="{{ old('address', $shop->address) }}" placeholder="Ex. Tokoin Doumasséssé, près de la pharmacie">
            </div>

            <div style="margin-top:2rem;display:flex;justify-content:flex-end;gap:1rem">
                <button type="submit" class="btn btn-primary">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
