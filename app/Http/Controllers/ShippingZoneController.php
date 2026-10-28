<?php

namespace App\Http\Controllers;

use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    /**
     * G-01 : Liste et configuration des zones de livraison
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $shop->ensureDefaultShippingZones();
        $zones = $shop->shippingZones()->get();

        return view('shipping.index', compact('shop', 'zones'));
    }

    /**
     * Ajout d'une nouvelle zone
     */
    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
            'delivery_estimate' => ['required', 'string', 'max:80'],
            'is_pickup' => ['nullable', 'boolean'],
        ]);

        $maxOrder = (int) $shop->shippingZones()->max('display_order');

        ShippingZone::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'free_shipping_threshold' => $validated['free_shipping_threshold'] ? (int) $validated['free_shipping_threshold'] : null,
            'delivery_estimate' => $validated['delivery_estimate'],
            'is_pickup' => $request->boolean('is_pickup', false),
            'is_active' => true,
            'display_order' => $maxOrder + 1,
        ]);

        return redirect()->route('shipping.index')->with('status', 'Nouvelle zone de livraison ajoutée avec succès.');
    }

    /**
     * Modification d'une zone
     */
    public function update(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shippingZone->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
            'delivery_estimate' => ['required', 'string', 'max:80'],
            'is_pickup' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $shippingZone->update([
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'free_shipping_threshold' => $validated['free_shipping_threshold'] ? (int) $validated['free_shipping_threshold'] : null,
            'delivery_estimate' => $validated['delivery_estimate'],
            'is_pickup' => $request->boolean('is_pickup', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('shipping.index')->with('status', 'Zone de livraison mise à jour.');
    }

    /**
     * Suppression d'une zone
     */
    public function destroy(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shippingZone->shop_id !== $shop->id) {
            abort(403);
        }

        $shippingZone->delete();

        return redirect()->route('shipping.index')->with('status', 'Zone de livraison supprimée.');
    }
}
