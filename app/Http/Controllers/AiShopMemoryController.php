<?php

namespace App\Http\Controllers;

use App\Models\AiShopMemory;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiShopMemoryController extends Controller
{
    /**
     * Affiche l'interface de consultation et gestion de la mémoire commerciale de la boutique.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);

        if (! $shop) {
            abort(403, "Aucune boutique rattachée.");
        }

        $memories = AiShopMemory::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->orderBy('category')
            ->latest('updated_at')
            ->get();

        $grouped = $memories->groupBy('category');

        $categoriesLabels = [
            AiShopMemory::CAT_PROFILE => 'Profil & Niche Métier',
            AiShopMemory::CAT_PREFERENCE => 'Préférences de Communication & Style',
            AiShopMemory::CAT_GOAL => 'Objectifs Commerciaux & Chiffres Clés',
            AiShopMemory::CAT_HABIT => 'Habitudes Logistiques & Canaux de Vente',
            AiShopMemory::CAT_RECOMMENDATION => 'Stratégies Recommandées Précédentes',
            'manual_instruction' => 'Consignes & Règles Personnalisées',
        ];

        return view('ai.memory', compact('shop', 'memories', 'grouped', 'categoriesLabels'));
    }

    /**
     * Enregistre manuellement une nouvelle consigne ou information mémorisée.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);

        if (! $shop) {
            abort(403);
        }

        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'memory_key' => 'required|string|max:100',
            'memory_value' => 'required|string|max:1000',
        ]);

        AiShopMemory::withoutGlobalScopes()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'memory_key' => \Illuminate\Support\Str::slug($validated['memory_key'], '_'),
            ],
            [
                'category' => $validated['category'],
                'memory_value' => trim($validated['memory_value']),
                'confidence' => 1.00,
                'source' => 'manual_merchant',
                'last_recalled_at' => now(),
            ]
        );

        return redirect()->route('ai.memory.index')->with('success', 'Information commerciale enregistrée dans la mémoire de votre assistant !');
    }

    /**
     * Met à jour une mémoire existante.
     */
    public function update(Request $request, AiShopMemory $memory): JsonResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);

        if (! $shop || ($memory->shop_id !== $shop->id && ! $user->isAdmin())) {
            return response()->json(['status' => 'error', 'message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validate([
            'memory_value' => 'required|string|max:1000',
        ]);

        $memory->update([
            'memory_value' => trim($validated['memory_value']),
            'confidence' => 1.00,
            'source' => 'manual_merchant',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Mémoire mise à jour avec succès.']);
    }

    /**
     * Supprime définitivement un fait mémorisé par l'IA.
     */
    public function destroy(Request $request, AiShopMemory $memory): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);

        if (! $shop || ($memory->shop_id !== $shop->id && ! $user->isAdmin())) {
            abort(403);
        }

        $memory->delete();

        return redirect()->route('ai.memory.index')->with('success', 'Information supprimée de la mémoire de l\'assistant.');
    }
}
