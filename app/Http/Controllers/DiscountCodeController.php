<?php

namespace App\Http\Controllers;

use App\Models\DiscountCode;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop ?? Shop::firstOrCreate(['user_id' => $user->id], ['name' => 'Ma Boutique', 'status' => Shop::DRAFT]);

        $discounts = DiscountCode::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->get();

        $canManageDiscounts = $user->isAdmin() || $shop->canUseFeature('promo_codes');

        return view('discounts.index', compact('shop', 'discounts', 'canManageDiscounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop) {
            abort(403);
        }

        if (! $user->isAdmin() && ! $shop->canUseFeature('promo_codes')) {
            return redirect()->route('discounts.index')
                ->with('error', 'Les codes promo sont disponibles à partir de la formule Pro. Passez au forfait Pro pour débloquer cette fonctionnalité.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ], [
            'code.regex' => 'Le code promo ne doit contenir que des lettres, chiffres, tirets (-) ou underscores (_).',
        ]);

        if ($validated['type'] === DiscountCode::TYPE_PERCENTAGE && (int) $validated['value'] > 100) {
            return back()->withInput()->withErrors([
                'value' => 'Une réduction en pourcentage ne peut pas dépasser 100%.',
            ]);
        }

        if (! empty($validated['starts_at']) && ! empty($validated['expires_at'])
            && strtotime($validated['expires_at']) < strtotime($validated['starts_at'])) {
            return back()->withInput()->withErrors([
                'expires_at' => 'La date d\'expiration doit être postérieure à la date de début.',
            ]);
        }

        $normalizedCode = strtoupper(trim($validated['code']));

        $alreadyExists = DiscountCode::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->exists();

        if ($alreadyExists) {
            return back()->withInput()->withErrors([
                'code' => "Le code promo « {$normalizedCode} » existe déjà dans votre boutique.",
            ]);
        }

        DiscountCode::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'code' => $normalizedCode,
            'type' => $validated['type'],
            'value' => (int) $validated['value'],
            'min_order_amount' => ! empty($validated['min_order_amount']) ? (int) $validated['min_order_amount'] : null,
            'max_uses' => ! empty($validated['max_uses']) ? (int) $validated['max_uses'] : null,
            'used_count' => 0,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('discounts.index')
            ->with('status', "Code promo « {$normalizedCode} » créé avec succès.");
    }

    public function toggle(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop || $discountCode->shop_id !== $shop->id) {
            abort(403);
        }

        if (! $user->isAdmin() && ! $shop->canUseFeature('promo_codes')) {
            return redirect()->route('discounts.index')
                ->with('error', 'Les codes promo sont disponibles à partir de la formule Pro.');
        }

        $discountCode->update(['is_active' => ! $discountCode->is_active]);

        $state = $discountCode->is_active ? 'activé' : 'désactivé';

        return redirect()->route('discounts.index')
            ->with('status', "Code promo « {$discountCode->code} » {$state}.");
    }

    public function destroy(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop || $discountCode->shop_id !== $shop->id) {
            abort(403);
        }

        if (! $user->isAdmin() && ! $shop->canUseFeature('promo_codes')) {
            return redirect()->route('discounts.index')
                ->with('error', 'Les codes promo sont disponibles à partir de la formule Pro.');
        }

        $code = $discountCode->code;
        $discountCode->delete();

        return redirect()->route('discounts.index')
            ->with('status', "Code promo « {$code} » supprimé.");
    }
}

