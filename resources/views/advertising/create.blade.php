@extends('layouts.app')

@section('title', 'Générer une Publicité par l\'IA : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:840px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span class="svg-icon" style="color:var(--brand-2)"><x-icon name="ads" :size="24" /></span>
                <h1 style="font-size:1.8rem;margin:0">Assistant Publicitaire IA (Module H)</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">Générez en quelques secondes des textes captivants pour vos statuts WhatsApp, TikTok et SMS.</p>
        </div>

        <a href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="folder" :size="15" />
            <span>Mes publicités générées</span>
        </a>
    </div>

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

    <div class="card">
        <form method="POST" action="{{ route('ads.store') }}">
            @csrf

            <!-- Produit à promouvoir -->
            <div style="margin-bottom:1.5rem">
                <label for="product_id" class="label">Produit à mettre en avant *</label>
                <select id="product_id" name="product_id" class="input" style="font-size:1rem" onchange="updateProductPrice(this)">
                    <option value="">-- Toute la boutique / Collection générale --</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" data-price="{{ $p->price }}" {{ $selectedProductId === $p->id ? 'selected' : '' }}>
                            {{ $p->title }} ({{ number_format($p->price, 0, ',', ' ') }} FCFA)
                        </option>
                    @endforeach
                </select>
                <p class="hint">Sélectionnez un article de votre catalogue ou faites la promotion globale de votre boutique.</p>
            </div>

            <!-- Canal de diffusion (CDC H-01) -->
            <div style="margin-bottom:1.5rem">
                <label class="label">Canal de diffusion principal *</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.8rem">
                    <label style="display:flex;align-items:center;gap:.6rem;padding:.9rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                        <input type="radio" name="channel" value="whatsapp" {{ old('channel', 'whatsapp') === 'whatsapp' ? 'checked' : '' }} style="accent-color:var(--brand-1)">
                        <div>
                            <div style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <span style="color:#25D366"><x-icon name="whatsapp" :size="16" /></span>
                                <span>Statut WhatsApp</span>
                            </div>
                            <div style="font-size:.8rem;color:var(--text-soft)">Texte court optimisé</div>
                        </div>
                    </label>

                    <label style="display:flex;align-items:center;gap:.6rem;padding:.9rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                        <input type="radio" name="channel" value="tiktok" {{ old('channel') === 'tiktok' ? 'checked' : '' }} style="accent-color:var(--brand-1)">
                        <div>
                            <div style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <x-icon name="tiktok" :size="16" />
                                <span>TikTok & Reels</span>
                            </div>
                            <div style="font-size:.8rem;color:var(--text-soft)">Légende & hashtags</div>
                        </div>
                    </label>

                    <label style="display:flex;align-items:center;gap:.6rem;padding:.9rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                        <input type="radio" name="channel" value="sms" {{ old('channel') === 'sms' ? 'checked' : '' }} style="accent-color:var(--brand-1)">
                        <div>
                            <div style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <x-icon name="sms" :size="16" />
                                <span>Campagne SMS</span>
                            </div>
                            <div style="font-size:.8rem;color:var(--text-soft)">≤ 160 caractères</div>
                        </div>
                    </label>

                    <label style="display:flex;align-items:center;gap:.6rem;padding:.9rem;background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer">
                        <input type="radio" name="channel" value="instagram" {{ old('channel') === 'instagram' ? 'checked' : '' }} style="accent-color:var(--brand-1)">
                        <div>
                            <div style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <x-icon name="instagram" :size="16" />
                                <span>Instagram</span>
                            </div>
                            <div style="font-size:.8rem;color:var(--text-soft)">Post / Story</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Objectif & Ton -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.5rem">
                <div>
                    <label for="objective" class="label">Objectif commercial *</label>
                    <select id="objective" name="objective" class="input">
                        <option value="nouveaute" {{ old('objective') === 'nouveaute' ? 'selected' : '' }}>Annoncer une nouveauté</option>
                        <option value="promotion" {{ old('objective') === 'promotion' ? 'selected' : '' }}>Lancer une promotion / réduction</option>
                        <option value="destockage" {{ old('objective') === 'destockage' ? 'selected' : '' }}>Déstockage express (urgence)</option>
                        <option value="rappel" {{ old('objective') === 'rappel' ? 'selected' : '' }}>Rappel client / Réactivation</option>
                    </select>
                </div>

                <div>
                    <label for="tone" class="label">Ton de communication *</label>
                    <select id="tone" name="tone" class="input">
                        <option value="amical" {{ old('tone') === 'amical' ? 'selected' : '' }}>Amical & Chaleureux (proche des clients)</option>
                        <option value="premium" {{ old('tone') === 'premium' ? 'selected' : '' }}>Élégant & Haut de gamme</option>
                        <option value="dynamique" {{ old('tone') === 'dynamique' ? 'selected' : '' }}>Dynamique & Vendeur</option>
                    </select>
                </div>
            </div>

            <!-- Prix promotionnel saisi par le commerçant (Règle critique CDC 7.3 : l'IA n'invente jamais de prix !) -->
            <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1.2rem;margin-bottom:2rem">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
                    <span style="color:var(--warn);display:inline-flex"><x-icon name="info" :size="16" /></span>
                    <strong style="font-size:.95rem">Règle de fiabilité des prix (CDC 7.3) :</strong>
                </div>
                <p style="font-size:.85rem;color:var(--text-soft);margin-bottom:1rem">
                    L'intelligence artificielle n'invente aucun prix ni fausse remise. Si vous souhaitez mettre en avant un prix promotionnel, renseignez-le ci-dessous :
                </p>

                <div style="max-width:280px">
                    <label for="promo_price" class="label" style="font-size:.85rem">Prix promotionnel en FCFA (facultatif)</label>
                    <input id="promo_price" type="number" name="promo_price" value="{{ old('promo_price') }}" min="0" placeholder="Ex. 10000" class="input">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:1rem">
                <a href="{{ route('dashboard') }}" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary" style="font-size:1.05rem;padding:.8rem 1.8rem;display:inline-flex;align-items:center;gap:.5rem">
                    <x-icon name="sparkles" :size="18" />
                    <span>Générer ma publicité & mes scripts vidéo</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
