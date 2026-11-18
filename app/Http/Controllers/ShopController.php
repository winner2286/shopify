<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    /**
     * Formulaire minimaliste de création de boutique (Inspiration selo.ci, 100% charte Zanika).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $currentPlan = $user->currentPlan();
        $shopsCount = $user->shopsCount();
        $maxShops = $currentPlan->maxShops();

        if ($shopsCount >= $maxShops) {
            $upgradePlans = Plan::query()->where('active', true)->orderBy('monthly_price')->get()
                ->filter(fn (Plan $plan): bool => $plan->maxShops() > $shopsCount);

            return view('shops.limit_reached', [
                'currentPlan' => $currentPlan,
                'shopsCount' => $shopsCount,
                'maxShops' => $maxShops,
                'upgradePlans' => $upgradePlans,
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
            'currentPlan' => $currentPlan,
            'shopsCount' => $shopsCount,
            'maxShops' => $maxShops,
        ]);
    }

    /**
     * Enregistrement ultra-rapide (Nom + Devise/Pays uniquement).
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $validCurrencies = array_keys(config('platform.currencies', ['XOF' => []]));
        $validCountries = array_keys(config('platform.countries', ['TG' => 'Togo']));

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'country' => ['required', 'string', 'in:'.implode(',', $validCountries)],
            'currency' => ['required', 'string', 'in:'.implode(',', $validCurrencies)],
        ], [
            'name.required' => 'Le nom de votre boutique est requis.',
            'name.min' => 'Le nom doit comporter au moins 2 caractères.',
        ]);

        $countryCode = strtoupper($validated['country']);
        $profile = config("platform.country_profiles.{$countryCode}") ?? config('platform.country_profiles.TG');

        $shop = DB::transaction(function () use ($user, $validated, $countryCode, $profile): ?Shop {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $user->canCreateShop()) {
                return null;
            }

            $subscription = $user->accountSubscription();
            $shop = $user->shops()->create([
                'name' => $validated['name'],
                'subdomain' => Shop::generateUniqueSubdomain($validated['name']),
                'currency' => $validated['currency'],
                'status' => Shop::DRAFT,
                'contact' => ['country' => $countryCode, 'city' => $profile['city'] ?? 'Lomé'],
                'onboarding_step' => 1,
            ]);
            $shop->ensureDefaultShippingZones();
            if ($subscription) {
                $shop->subscriptions()->create($subscription->only(['plan_id', 'monthly_price', 'status', 'starts_at', 'trial_ends_at', 'renews_at', 'ends_at']));
            }

            return $shop;
        });
        if (! $shop) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'limit_reached',
                    'message' => 'Limite de boutiques atteinte pour votre forfait.',
                    'upgrade_url' => route('shops.create'),
                ], 403);
            }

            return redirect()->route('shops.create')->with('upgrade_prompt', 'Vous avez atteint la limite de boutiques de votre forfait.');
        }

        // Basculer la boutique active sur la nouvelle créée
        session(['active_shop_id' => $shop->id]);

        return redirect()->route('dashboard')->with('status', 'Votre boutique "'.$shop->name.'" a été créée avec succès !');
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

        return redirect()->route('dashboard')->with('status', 'Boutique active : '.$shop->name);
    }
}
