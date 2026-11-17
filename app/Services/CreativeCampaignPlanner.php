<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Str;

class CreativeCampaignPlanner
{
    public const LAYOUTS = ['hero', 'split', 'collage', 'detail', 'typography', 'price', 'cta'];

    public function plan(Shop $shop, array $products, array $brief, array $aiScripts = []): array
    {
        $seed = random_int(1, 999999);
        $directions = [
            ['id' => 'drop', 'title' => 'Le drop', 'concept' => 'Une accroche typographique, des cuts et un produit qui prend toute la place.', 'layouts' => ['typography', 'hero', 'detail', 'price', 'cta']],
            ['id' => 'editorial', 'title' => 'Le magazine', 'concept' => 'Une sélection éditoriale : compositions décalées, grands visuels et respiration.', 'layouts' => ['split', 'detail', 'collage', 'hero', 'cta']],
            ['id' => 'selection', 'title' => 'La sélection', 'concept' => 'Une découverte rythmée du catalogue, entre mosaïque, détails et prix.', 'layouts' => ['collage', 'split', 'hero', 'price', 'cta']],
        ];
        $duration = max(10, min(60, (int) ($brief['target_duration'] ?? 20)));
        $hero = $products[0] ?? ['title' => $shop->name, 'price' => '', 'old_price' => null, 'category' => 'La collection'];
        $scripts = [];
        foreach ($directions as $index => $direction) {
            $suggestion = $aiScripts[$index] ?? [];
            $aiHook = collect($suggestion['scenes'] ?? [])->firstWhere('type', 'hook');
            $hooks = [
                ['La pièce du jour', 'Regardez les détails', 'À découvrir maintenant'],
                ['La sélection du moment', 'Une autre perspective', 'Dans notre sélection'],
                ['Votre prochain coup de cœur ?', 'Faites votre sélection', 'Le catalogue en mouvement'],
            ];
            $hook = $aiHook['title'] ?? $hooks[$index][$seed % 3];
            $hook = Str::limit(strip_tags((string) $hook), 85, '');
            $scenes = [];
            $usedVoice = [];
            $aiScenes = is_array($suggestion['scenes'] ?? null) ? $suggestion['scenes'] : [];
            $hasAiLayouts = count(array_unique(array_intersect(array_column($aiScenes, 'layout'), self::LAYOUTS))) >= 3;
            $count = max(5, min(9, count($aiScenes) ?: count($products) + 4));
            for ($shot = 0; $shot < $count; $shot++) {
                $candidate = $aiScenes[$shot] ?? null;
                $productIndex = count($products) ? max(0, ($shot - 1 + $index) % count($products)) : null;
                if (is_int($candidate['product_index'] ?? null) && isset($products[$candidate['product_index']])) {
                    $productIndex = $candidate['product_index'];
                }
                $product = $products[$productIndex ?? 0] ?? $hero;
                $last = $shot === $count - 1;
                $layout = $last ? 'cta' : $direction['layouts'][$shot % (count($direction['layouts']) - 1)];
                if (! $last && $hasAiLayouts && in_array($candidate['layout'] ?? null, self::LAYOUTS, true) && $candidate['layout'] !== 'cta') {
                    $layout = $candidate['layout'];
                }
                $title = $shot === 0 ? $hook : ($last ? 'Découvrez la boutique' : $product['title']);
                $subtitle = $last ? $shop->name : ($layout === 'price' ? ($product['price'] ?? '') : ($product['category'] ?? ''));
                $voiceover = $last ? 'Découvrez la sélection dans notre boutique.' : ($shot === 0 ? $hook : '');
                $productScene = collect($suggestion['scenes'] ?? [])->first(fn ($candidate) => ($candidate['type'] ?? '') === 'product' && ($candidate['product_index'] ?? null) === $productIndex);
                if ($shot === 0 && ! empty($aiHook['voiceover'])) {
                    $voiceover = (string) $aiHook['voiceover'];
                } elseif (! $last && $shot > 0 && $productScene && ! isset($usedVoice[$productIndex ?? 0])) {
                    $subtitle = (string) ($productScene['subtitle'] ?? $subtitle);
                    $voiceover = (string) ($productScene['voiceover'] ?? '');
                    $usedVoice[$productIndex ?? 0] = true;
                }
                if ($shot > 0 && ! $last && $candidate && in_array($candidate['type'] ?? '', ['product', 'feature'], true)
                    && (! isset($candidate['product_index']) || $candidate['product_index'] === $productIndex)) {
                    $title = Str::limit(strip_tags((string) ($candidate['title'] ?? $title)), 120, '');
                    $subtitle = Str::limit(strip_tags((string) ($candidate['subtitle'] ?? $subtitle)), 180, '');
                } elseif ($shot > 0 && ! $last && $layout === 'detail') {
                    $title = ['Dans les détails', 'Une autre perspective', 'Regardez de plus près'][$index];
                }
                $scenes[] = [
                    'id' => (string) Str::uuid(), 'type' => $last ? 'cta' : ($shot === 0 ? 'hook' : 'product'),
                    'title' => $title, 'subtitle' => $subtitle, 'voiceover' => $voiceover,
                    'duration' => is_numeric($candidate['duration'] ?? null) ? max(0.5, min(15, (float) $candidate['duration'])) : match ($layout) {
                        'collage' => 0.8, 'detail' => 0.9, 'cta' => 1.3, default => 1.1
                    }, 'product_index' => $productIndex,
                    'layout' => $layout, 'motion' => in_array($candidate['motion'] ?? null, ['push', 'pan', 'rise', 'none'], true) ? $candidate['motion'] : ['push', 'pan', 'rise'][($shot + $index) % 3],
                    'transition' => in_array($candidate['transition'] ?? null, ['cut', 'dissolve', 'wipe'], true) ? $candidate['transition'] : ($index === 1 ? 'dissolve' : ($shot % 3 === 2 ? 'wipe' : 'cut')),
                    'fit' => $layout === 'detail' ? 'cover' : 'contain', 'media_id' => null,
                    'image_index' => $shot % max(1, count($product['gallery'] ?? [0])),
                    'media_start' => 0,
                ];
            }
            $totalWeight = array_sum(array_column($scenes, 'duration'));
            foreach ($scenes as &$scene) {
                $scene['duration'] = max(0.5, min(15, round($duration * $scene['duration'] / $totalWeight, 2)));
            }
            unset($scene);
            $remainder = round($duration - array_sum(array_column($scenes, 'duration')), 2);
            foreach ($scenes as &$scene) {
                $before = $scene['duration'];
                $scene['duration'] = max(0.5, min(15, round($before + $remainder, 2)));
                $remainder = round($remainder - ($scene['duration'] - $before), 2);
            }
            unset($scene);
            $scripts[] = [
                'id' => $direction['id'], 'title' => Str::limit(strip_tags((string) ($suggestion['title'] ?? $direction['title'])), 100, ''), 'concept' => Str::limit(strip_tags((string) ($suggestion['concept'] ?? $direction['concept'])), 350, ''),
                'seed' => $seed + $index, 'scenes' => $scenes, 'source' => $suggestion ? 'ai_directed' : 'composed',
                'shooting_tips' => [
                    'Filmez le produit de face, puis un détail, avec une lumière stable.',
                    'Gardez 1 seconde avant et après chaque mouvement pour faciliter les raccords.',
                    'Importez des plans courts ; vous pouvez activer leur son plan par plan.',
                ],
            ];
        }

        return $scripts;
    }
}
