<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    /**
     * Formulaire minimaliste de création de boutique (Inspiration selo.ci, 100% charte Zanika).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // 1. Vérification de la limite du forfait
        if (! $user->canCreateShop()) {
            return view('shops.limit_reached', [
                'user' => $user,
                'currentPlan' => $user->currentPlan(),
                'shopsCount' => $user->shopsCount(),
                'maxShops' => $user->maxShops(),
            ]);
        }

        $countryCode = strtoupper($user->country ?? 'TG');
        $profile = config("platform.country_profiles.{$countryCode}") ?? config('platform.country_profiles.TG');
        $defaultCurrency = $profile['currency'] ?? 'XOF';

        return view('shops.create', [
            'user' => $user,
            'defaultCountry' => $countryCode,
            'defaultCurrency' => $defaultCurrency,
            'countries' => config('platform.countries', ['TG' => 'Togo', 'CI' => 'Côte d\'Ivoire', 'BJ' => 'Bénin', 'SN' => 'Sénégal']),
            'currencies' => config('platform.currencies', ['XOF' => ['name' => 'Franc CFA (BCEAO)']]),
            'currentPlan' => $user->currentPlan(),
            'shopsCount' => $user->shopsCount(),
            'maxShops' => $user->maxShops(),
        ]);
    }

    /**
     * Enregistrement ultra-rapide (Nom + Devise/Pays uniquement).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Règle de sécurité : Bloquer si la limite du forfait est atteinte
        if (! $user->canCreateShop()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'limit_reached',
                    'message' => 'Limite de boutiques atteinte pour votre forfait.',
                    'upgrade_url' => route('subscription.index'),
                ], 403);
            }

            return redirect()->route('shops.create')->with(
                'upgrade_prompt', 
                'Vous avez atteint la limite de boutiques autorisées par votre forfait. Passez au forfait supérieur pour créer une nouvelle boutique.'
            );
        }

        $validCurrencies = array_keys(config('platform.currencies', ['XOF' => []]));
        $validCountries = array_keys(config('platform.countries', ['TG' => 'Togo']));

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'country' => ['required', 'string', 'in:' . implode(',', $validCountries)],
            'currency' => ['required', 'string', 'in:' . implode(',', $validCurrencies)],
        ], [
            'name.required' => 'Le nom de votre boutique est requis.',
            'name.min' => 'Le nom doit comporter au moins 2 caractères.',
        ]);

        $countryCode = strtoupper($validated['country']);
        $profile = config("platform.country_profiles.{$countryCode}") ?? config('platform.country_profiles.TG');

        // Génération automatique d'un sous-domaine propre et unique
        $baseSubdomain = Str::slug($validated['name']);
        if (empty($baseSubdomain)) {
            $baseSubdomain = 'boutique-' . Str::lower(Str::random(5));
        }

        $subdomain = $baseSubdomain;
        $counter = 1;

        while (Shop::where('subdomain', $subdomain)->exists()) {
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
        }

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'subdomain' => $subdomain,
            'currency' => $validated['currency'],
            'status' => Shop::DRAFT,
            'contact' => [
                'country' => $countryCode,
                'city' => $profile['city'] ?? 'Lomé',
            ],
            'onboarding_step' => 1,
        ]);

        // Configuration automatique des zones de livraison locales
        $shop->ensureDefaultShippingZones();

        // Si le compte a déjà un abonnement actif (ex: Pro ou Premium), dupliquer l'abonnement sur la nouvelle boutique
        $activeSub = Subscription::whereIn('shop_id', $user->shops()->where('id', '!=', $shop->id)->pluck('id'))
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->latest('id')
            ->first();

        if ($activeSub) {
            $shop->subscriptions()->create([
                'plan_id' => $activeSub->plan_id,
                'status' => $activeSub->status,
                'starts_at' => $activeSub->starts_at,
                'trial_ends_at' => $activeSub->trial_ends_at,
                'renews_at' => $activeSub->renews_at,
                'ends_at' => $activeSub->ends_at,
            ]);
        }

        // Basculer la boutique active sur la nouvelle créée
        session(['active_shop_id' => $shop->id]);

        return redirect()->route('dashboard')->with('status', 'Votre boutique "' . $shop->name . '" a été créée avec succès !');
    }

    /**
     * Basculer la boutique active dans la session.
     */
    public function switchShop(Request $request, Shop $shop): RedirectResponse
    {
        $user = $request->user();

        if ($shop->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Accès non autorisé à cette boutique.');
        }

        session(['active_shop_id' => $shop->id]);

        return redirect()->route('dashboard')->with('status', 'Boutique active : ' . $shop->name);
    }
}

