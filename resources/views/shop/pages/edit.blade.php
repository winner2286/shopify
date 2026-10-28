@extends('layouts.app')

@section('title', 'Modifier : '.$page->title)

@section('content')
<div class="container" style="max-width:800px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="font-size:.85rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
                Édition de page ({{ $page->type_label }})
            </div>
            <h1 style="font-size:1.8rem;margin:0">{{ $page->title }}</h1>
        </div>
        <a href="{{ route('shop.pages.index') }}" class="btn btn-ghost btn-sm">
            ← Toutes les pages
        </a>
    </div>

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
        <form method="POST" action="{{ route('shop.pages.update', $page) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom:1.5rem">
                <label for="title" class="label">Titre affiché aux clients *</label>
                <input id="title" type="text" name="title" class="input" value="{{ old('title', $page->title) }}" required maxlength="120">
            </div>

            <div style="margin-bottom:1.5rem">
                <label for="content" class="label">Contenu de la page *</label>
                <textarea id="content" name="content" class="input" rows="12" required style="line-height:1.6;font-size:1rem">{{ old('content', $page->content) }}</textarea>
                <p class="hint">Vous pouvez séparer vos paragraphes avec un saut de ligne. Restez clair et rassurant pour vos acheteurs.</p>
            </div>

            <div style="margin-bottom:2rem;background:var(--bg-soft);padding:1rem;border-radius:var(--radius-sm)">
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer">
                    <input type="checkbox" name="is_published" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--brand-1)">
                    <span style="font-weight:600">Afficher cette page sur la vitrine publique</span>
                </label>
                <p class="hint" style="margin:.3rem 0 0 1.8rem">Si décochée, la page ne sera pas accessible aux visiteurs.</p>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:1rem">
                <a href="{{ route('shop.pages.index') }}" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer la page</button>
            </div>
        </form>
    </div>
</div>
@endsection
