<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\AiGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function __construct(private AiGenerationService $aiService) {}

    /**
     * Étape 1 (B-01) : Description de l'activité
     */
    public function step1(Request $request)
    {
        $shop = $request->user()->shop;

        return view('onboarding.step1', compact('shop'));
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop;
        if (! $shop) {
            $shop = Shop::create([
                'user_id' => $user->id,
                'name' => 'Ma Boutique',
                'status' => Shop::DRAFT,
                'onboarding_step' => 1,
            ]);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:20', 'max:1000'],
            'business_category' => ['required', 'string', 'max:50'],
        ], [
            'description.required' => 'Veuillez décrire votre activité en quelques mots.',
            'description.min' => 'La description doit faire au moins 20 caractères pour que l\'IA comprenne votre commerce.',
            'description.max' => 'La description ne doit pas dépasser 1 000 caractères.',
            'business_category.required' => 'Veuillez choisir un secteur d\'activité.',
        ]);

        $data = $shop->onboarding_data ?? [];
        $data['description'] = $validated['description'];
        $data['business_category'] = $validated['business_category'];

        $shop->update([
            'description' => $validated['description'],
            'business_category' => $validated['business_category'],
            'onboarding_step' => max($shop->onboarding_step, 2),
            'onboarding_data' => $data,
        ]);

        return redirect()->route('onboarding.step2');
    }

    /**
     * Étape 2 (B-02) : Questionnaire guidé
     */
    public function step2(Request $request)
    {
        $shop = $request->user()->shop;

        return view('onboarding.step2', compact('shop'));
    }

    public function storeStep2(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'suggested_name' => ['required', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'target_audience' => ['nullable', 'string', 'max:100'],
            'visual_style' => ['required', 'in:moderne,elegant,colore,minimaliste,artisanal'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'maps_url' => ['nullable', 'string', 'max:500'],
        ], [
            'suggested_name.required' => 'Indiquez un nom pour votre boutique.',
            'visual_style.required' => 'Choisissez un style visuel.',
        ]);

        $data = $shop->onboarding_data ?? [];
        $data['suggested_name'] = $validated['suggested_name'];
        $data['whatsapp'] = $validated['whatsapp'] ?? null;
        $data['target_audience'] = $validated['target_audience'] ?? 'Grand public';
        $data['visual_style'] = $validated['visual_style'];
        $data['brand_color'] = $validated['brand_color'] ?? '#6d3df2';
        $data['address'] = $validated['address'] ?? null;
        $data['city'] = $validated['city'] ?? null;
        $data['maps_url'] = $validated['maps_url'] ?? null;

        $contact = $shop->contact ?? [];
        if (! empty($validated['whatsapp'])) {
            $contact['whatsapp'] = $validated['whatsapp'];
            $contact['phone'] = $contact['phone'] ?? $validated['whatsapp'];
        }
        if (isset($validated['address'])) {
            $contact['address'] = $validated['address'];
        }
        if (isset($validated['city'])) {
            $contact['city'] = $validated['city'];
        }
        if (isset($validated['maps_url'])) {
            $contact['maps_url'] = $validated['maps_url'];
        }

        $shop->update([
            'name' => $validated['suggested_name'],
            'visual_style' => $validated['visual_style'],
            'brand_color' => $validated['brand_color'] ?? '#6d3df2',
            'target_audience' => $validated['target_audience'] ?? 'Grand public',
            'contact' => $contact,
            'onboarding_step' => max($shop->onboarding_step, 3),
            'onboarding_data' => $data,
        ]);

        return redirect()->route('onboarding.step3');
    }

    /**
     * Étape 3 (B-03) : Envoi des photos de produits
     */
    public function step3(Request $request)
    {
        $shop = $request->user()->shop;

        return view('onboarding.step3', compact('shop'));
    }

    public function storeStep3(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $request->validate([
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp,jpg', 'max:5120'], // Max 5 Mo par photo
        ], [
            'photos.max' => 'Vous pouvez envoyer au maximum 20 photos.',
        ]);

        $storedPaths = [];
        if ($request->hasFile('photos')) {
            $imageService = app(\App\Services\ImageService::class);
            foreach ($request->file('photos') as $file) {
                $processed = $imageService->processAndStore($file, "shops/{$shop->id}/onboarding");
                $storedPaths[] = $processed['path'];
            }
        }

        $data = $shop->onboarding_data ?? [];
        $data['uploaded_photos'] = array_merge($data['uploaded_photos'] ?? [], $storedPaths);

        $shop->update([
            'onboarding_step' => max($shop->onboarding_step, 4),
            'onboarding_data' => $data,
        ]);

        return redirect()->route('onboarding.step4');
    }

    /**
     * Étape 4 (B-04) : Progression et exécution de la génération par l'IA
     */
    public function step4(Request $request)
    {
        $shop = $request->user()->shop;

        return view('onboarding.step4', compact('shop'));
    }

    public function runGeneration(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $shop->onboarding_data ?? [];
        $photos = $data['uploaded_photos'] ?? [];

        // Exécution de la chaîne IA complète
        $this->aiService->generateShop($shop, $data, $photos);

        return redirect()->route('onboarding.preview')->with('status', 'Votre boutique a été générée avec succès par l\'IA !');
    }

    /**
     * Étape 5 (B-05 / B-06) : Aperçu & Personnalisation
     */
    public function preview(Request $request)
    {
        $shop = $request->user()->shop;
        $shop->load(['categories', 'products.images', 'pages']);

        $logos = $shop->onboarding_data['generated_logos'] ?? [];

        return view('onboarding.preview', compact('shop', 'logos'));
    }

    /**
     * Sélection du logo préféré généré par l'IA
     */
    public function selectLogo(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $selectedId = $request->input('logo_id');

        $logos = $shop->onboarding_data['generated_logos'] ?? [];
        $chosen = collect($logos)->firstWhere('id', $selectedId);

        if ($chosen) {
            $data = $shop->onboarding_data ?? [];
            $data['chosen_logo'] = $chosen;
            
            // Si un logo customisé existait sur le disque, on l'efface pour activer le logo IA sélectionné
            if ($shop->logo_path && Storage::disk('public')->exists($shop->logo_path)) {
                Storage::disk('public')->delete($shop->logo_path);
            }

            $shop->update([
                'logo_path' => null,
                'onboarding_data' => $data,
            ]);
        }

        return back()->with('status', 'Logo appliqué à votre boutique.');
    }

    /**
     * Téléversement direct du logo propre au commerçant (Fichier image personnalisé)
     */
    public function uploadCustomLogo(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $request->validate([
            'custom_logo' => ['required', 'image', 'mimes:jpeg,png,webp,jpg,svg', 'max:5120'],
        ], [
            'custom_logo.required' => 'Veuillez sélectionner votre fichier de logo.',
            'custom_logo.image' => 'Le fichier sélectionné doit être une image (PNG, JPG, WebP ou SVG).',
            'custom_logo.max' => 'La taille maximale autorisée est de 5 Mo.',
        ]);

        $imageService = app(\App\Services\ImageService::class);
        $processed = $imageService->processAndStore($request->file('custom_logo'), "shops/{$shop->id}/logo");

        if (!empty($processed['path'])) {
            if ($shop->logo_path && Storage::disk('public')->exists($shop->logo_path)) {
                Storage::disk('public')->delete($shop->logo_path);
            }

            $data = $shop->onboarding_data ?? [];
            unset($data['chosen_logo']);

            $shop->update([
                'logo_path' => $processed['path'],
                'onboarding_data' => $data,
            ]);
        }

        return back()->with('status', 'Votre propre logo a été importé et appliqué avec succès à votre boutique !');
    }
}
