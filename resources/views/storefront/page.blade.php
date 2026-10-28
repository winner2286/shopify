@extends('storefront.layout')

@section('title', $page->title . ' - ' . $shop->name)

@section('content')
<div class="container" style="max-width:840px;padding-top:2.5rem">
    <nav style="font-size:.85rem;color:var(--text-faint);margin-bottom:1.5rem;display:flex;gap:.5rem;align-items:center">
        <a href="{{ url('/b/' . $shop->subdomain) }}" style="color:var(--text-faint)">Accueil</a>
        <span>/</span>
        <span style="color:var(--text);font-weight:500">{{ $page->title }}</span>
    </nav>

    <article class="card" style="padding:2.5rem 2rem">
        <h1 style="font-size:clamp(1.8rem, 4vw, 2.4rem);margin:0 0 1.5rem;line-height:1.2">
            {{ $page->title }}
        </h1>

        <div style="font-size:1.05rem;line-height:1.8;color:var(--text);white-space:pre-line;margin-bottom:2rem">
            {{ $page->content }}
        </div>

        @if ($page->type === 'contact')
            <div style="border-top:1px solid var(--border);padding-top:2rem;margin-top:2rem">
                <h2 style="font-size:1.3rem;margin-bottom:1.2rem">Moyens de contact direct</h2>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem">
                    @if ($shop->whatsapp_formatted)
                        <a href="https://wa.me/{{ $shop->whatsapp_formatted }}" target="_blank" class="card" style="background:color-mix(in srgb, #25D366 12%, transparent);border-color:#25D366;text-decoration:none;text-align:center;padding:1.5rem">
                            <div style="margin-bottom:.5rem;display:flex;justify-content:center;color:#25D366"><x-icon name="whatsapp" size="32" /></div>
                            <div style="font-weight:700;color:var(--text);margin-bottom:.2rem">WhatsApp</div>
                            <div style="font-size:.85rem;color:var(--text-soft)">Discuter instantanément</div>
                        </a>
                    @endif

                    @if ($shop->phone)
                        <a href="tel:{{ $shop->phone }}" class="card" style="text-decoration:none;text-align:center;padding:1.5rem">
                            <div style="margin-bottom:.5rem;display:flex;justify-content:center;color:var(--store-primary)"><x-icon name="phone" size="32" /></div>
                            <div style="font-weight:700;color:var(--text);margin-bottom:.2rem">Appel téléphonique</div>
                            <div style="font-size:.85rem;color:var(--text-soft)">{{ $shop->phone }}</div>
                        </a>
                    @endif

                    @if ($shop->email_contact)
                        <a href="mailto:{{ $shop->email_contact }}" class="card" style="text-decoration:none;text-align:center;padding:1.5rem">
                            <div style="margin-bottom:.5rem;display:flex;justify-content:center;color:var(--store-primary)"><x-icon name="mail" size="32" /></div>
                            <div style="font-weight:700;color:var(--text);margin-bottom:.2rem">Email</div>
                            <div style="font-size:.85rem;color:var(--text-soft)">{{ $shop->email_contact }}</div>
                        </a>
                    @endif
                </div>

                @if ($shop->address || $shop->city)
                    <div style="background:var(--bg-soft);padding:1.2rem;border-radius:var(--radius-sm);margin-top:1.5rem">
                        <strong style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="map-pin" size="16" /> Adresse de la boutique :</strong>
                        <p style="margin:.3rem 0 0;color:var(--text-soft)">
                            {{ $shop->address ? $shop->address . ' - ' : '' }}{{ $shop->city ?: 'Lomé, Togo' }}
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </article>
</div>
@endsection
