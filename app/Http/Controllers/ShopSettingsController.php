<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopSettingsController extends Controller
{
    /**
     * Affiche les paramètres et la page de publication de la boutique
     */
    public function edit(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('onboarding.step1')->with('status', 'Veuillez initialiser votre boutique.');
        }

        $checklist = $shop->publicationChecklist();

        return view('shop.settings', compact('shop', 'checklist'));
    }

    /**
     * Enregistre les modifications apportées aux réglages de la boutique
     */
    public function update(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $reserved = config('platform.reserved_subdomains', []);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/',
                Rule::unique('shops', 'subdomain')->ignore($shop->id),
                Rule::notIn($reserved),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['required', 'string', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'business_category' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ], [
            'subdomain.regex' => 'Le sous-domaine doit contenir uniquement des lettres minuscules, chiffres ou tirets (sans tiret au début ou à la fin).',
            'subdomain.not_in' => 'Ce sous-domaine est réservé par la plateforme.',
            'subdomain.unique' => 'Ce sous-domaine est déjà réservé par une autre boutique.',
            'brand_color.regex' => 'Veuillez spécifier une couleur hexadécimale valide (ex. #6d3df2).',
        ]);

        $contact = $shop->contact ?? [];
        $contact['phone'] = $validated['phone'] ?? null;
        $contact['whatsapp'] = $validated['whatsapp'] ?? null;
        $contact['email'] = $validated['email'] ?? null;
        $contact['address'] = $validated['address'] ?? null;
        $contact['city'] = $validated['city'] ?? 'Lomé';

        $shop->update([
            'name' => $validated['name'],
            'subdomain' => strtolower($validated['subdomain']),
            'description' => $validated['description'] ?? null,
            'brand_color' => $validated['brand_color'],
            'business_category' => $validated['business_category'] ?? $shop->business_category,
            'contact' => $contact,
        ]);

        return redirect()->route('shop.settings')->with('status', 'Paramètres de la boutique mis à jour avec succès.');
    }

    /**
     * Publie la boutique si tous les critères CDC B-07 sont remplis
     */
    public function publish(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $checklist = $shop->publicationChecklist();

        if (! $checklist['can_publish']) {
            return back()->withErrors($checklist['errors'])->with('error', 'Impossible de publier la boutique : des critères obligatoires ne sont pas remplis.');
        }

        $shop->update(['status' => Shop::PUBLISHED]);

        return redirect()->route('shop.settings')->with('published_success', true)->with('status', 'Félicitations ! Votre boutique est désormais en ligne et accessible à vos clients.');
    }

    /**
     * Met en pause ou réactive la boutique (B-09, RG-13)
     */
    public function togglePause(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop->status === Shop::PUBLISHED) {
            $shop->update(['status' => Shop::PAUSED]);
            $msg = 'Votre boutique a été mise en pause. Vos visiteurs verront la page de maintenance.';
        } elseif ($shop->status === Shop::PAUSED) {
            // Vérifier à nouveau la checklist
            $checklist = $shop->publicationChecklist();
            if (! $checklist['can_publish']) {
                return back()->withErrors($checklist['errors'])->with('error', 'Impossible de réactiver : vérifiez vos informations et produits.');
            }
            $shop->update(['status' => Shop::PUBLISHED]);
            $msg = 'Votre boutique est de nouveau en ligne et accessible au public !';
        } else {
            return back()->with('error', 'La boutique doit d\'abord être publiée.');
        }

        return redirect()->route('shop.settings')->with('status', $msg);
    }

    /**
     * Vérification d'un sous-domaine en direct
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $sub = strtolower(trim($request->query('subdomain', '')));
        $shop = $request->user()->shop;
        $reserved = config('platform.reserved_subdomains', []);

        $validFormat = (bool) preg_match('/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/', $sub);
        $isReserved = in_array($sub, $reserved, true);
        $exists = Shop::where('subdomain', $sub)
            ->when($shop, fn ($q) => $q->where('id', '!=', $shop->id))
            ->exists();

        $available = $validFormat && ! $isReserved && ! $exists;

        return response()->json([
            'subdomain' => $sub,
            'available' => $available,
            'format_valid' => $validFormat,
            'is_reserved' => $isReserved,
            'exists' => $exists,
        ]);
    }

    /**
     * Supprime définitivement la boutique et toutes ses données associées (Zone de danger)
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop) {
            return redirect()->route('dashboard')->with('error', 'Aucune boutique associée à ce compte.');
        }

        $request->validate([
            'confirm_shop_name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'confirm_shop_name.required' => 'Veuillez saisir le nom exact de votre boutique pour confirmer la suppression.',
            'password.required' => 'Veuillez renseigner votre mot de passe actuel pour confirmer.',
        ]);

        // Vérification 1 : Nom de la boutique conforme
        if (trim(strtolower($request->input('confirm_shop_name'))) !== trim(strtolower($shop->name))) {
            return back()->withErrors(['confirm_shop_name' => 'Le nom saisi ne correspond pas exactement au nom de votre boutique ("' . $shop->name . '").']);
        }

        // Vérification 2 : Mot de passe du marchand
        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Le mot de passe saisi est incorrect.']);
        }

        $shopName = $shop->name;

        // Nettoyage physique des fichiers
        try {
            Storage::disk('public')->deleteDirectory("shops/{$shop->id}");
        } catch (\Throwable $e) {
            report($e);
        }

        // Suppression de la boutique (cascade sur categories, products, orders, etc.)
        $shop->delete();

        return redirect()->route('onboarding.step1')->with('status', 'La boutique "' . $shopName . '" a été définitivement supprimée. Vous pouvez désormais créer une nouvelle boutique.');
    }
}
