<dl class="plan-allowances">
    <div><dt>Boutiques par compte</dt><dd>{{ number_format($plan->maxShops(), 0, ',', ' ') }}</dd></div>
    <div><dt>Produits par boutique</dt><dd>{{ number_format($plan->max_products, 0, ',', ' ') }}</dd></div>
    <div><dt>Stockage par boutique</dt><dd>{{ $plan->storage_mb >= 1024 ? ($plan->storage_mb / 1024).' Go' : $plan->storage_mb.' Mo' }}</dd></div>
    <div><dt>Configurations assistées par boutique</dt><dd>{{ number_format($plan->full_generations, 0, ',', ' ') }}</dd></div>
    <div><dt>Crédits IA par mois</dt><dd>{{ number_format($plan->ai_credits, 0, ',', ' ') }}</dd></div>
    <div><dt>Décors générés par mois</dt><dd>{{ number_format($plan->studioScenesMonthly(), 0, ',', ' ') }}</dd></div>
    <div><dt>Crédits Photoroom par mois</dt><dd>{{ number_format($plan->photoroomCreditsMonthly(), 0, ',', ' ') }}</dd></div>
</dl>
