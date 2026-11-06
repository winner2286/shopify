@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigation par pages" class="zanika-pagination-nav">
        {{-- Résumé textuel discret --}}
        <div class="zanika-pagination-info">
            Affichage de <strong>{{ $paginator->firstItem() }}</strong> à <strong>{{ $paginator->lastItem() }}</strong> sur <strong>{{ $paginator->total() }}</strong> résultats
        </div>

        {{-- Barre de pagination avec boutons compacts --}}
        <div class="zanika-pagination-wrap">
            {{-- Bouton Précédent --}}
            @if ($paginator->onFirstPage())
                <span class="zanika-page-btn disabled" aria-disabled="true" aria-label="Page précédente">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="zanika-page-btn" aria-label="Page précédente">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
            @endif

            {{-- Éléments de pagination numérotés --}}
            <div class="zanika-page-numbers">
                @foreach ($elements as $element)
                    {{-- Séparateur "..." --}}
                    @if (is_string($element))
                        <span class="zanika-page-ellipsis">{{ $element }}</span>
                    @endif

                    {{-- Liens de pages --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="zanika-page-btn active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="zanika-page-btn">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Bouton Suivant --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="zanika-page-btn" aria-label="Page suivante">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            @else
                <span class="zanika-page-btn disabled" aria-disabled="true" aria-label="Page suivante">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
