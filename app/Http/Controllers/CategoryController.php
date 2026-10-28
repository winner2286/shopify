<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        $categories = $shop->categories()->withCount('products')->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('categories', 'name')->where('shop_id', $shop->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique' => 'Cette catégorie existe déjà dans votre boutique.',
        ]);

        $shop->categories()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'display_order' => $validated['display_order'] ?? ($shop->categories()->max('display_order') + 1),
            'is_visible' => $request->boolean('is_visible', true),
        ]);

        return back()->with('status', 'Catégorie créée avec succès.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('categories', 'name')->where('shop_id', $shop->id)->ignore($category->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'display_order' => $validated['display_order'] ?? $category->display_order,
            'is_visible' => $request->boolean('is_visible', true),
        ]);

        return back()->with('status', 'Catégorie mise à jour.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Si la catégorie contient des produits, possibilité de réattribuer (C-03)
        $newCategoryId = $request->input('replacement_category_id');
        if ($newCategoryId && $newCategoryId != $category->id) {
            $replacement = $shop->categories()->find($newCategoryId);
            if ($replacement) {
                foreach ($category->products as $product) {
                    $product->categories()->syncWithoutDetaching([$replacement->id]);
                }
            }
        }

        $category->products()->detach();
        $category->delete();

        return back()->with('status', 'Catégorie supprimée.');
    }
}
