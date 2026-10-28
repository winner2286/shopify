<?php

namespace App\Http\Controllers;

use App\Models\ShopPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopPageController extends Controller
{
    /**
     * Affiche la liste des pages d'information de la boutique (B-08)
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $pages = $shop->pages()->orderBy('type')->get();

        return view('shop.pages.index', compact('shop', 'pages'));
    }

    /**
     * Formulaire d'édition d'une page
     */
    public function edit(Request $request, ShopPage $page)
    {
        $shop = $request->user()->shop;

        // Protection de scope isolée (RG-02)
        if ($page->shop_id !== $shop->id) {
            abort(403);
        }

        return view('shop.pages.edit', compact('shop', 'page'));
    }

    /**
     * Enregistre les modifications d'une page d'information
     */
    public function update(Request $request, ShopPage $page): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($page->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $page->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()->route('shop.pages.index')->with('status', "La page « {$page->title} » a été enregistrée avec succès.");
    }
}
