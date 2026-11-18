<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenAiProductSceneService
{
    public function isConfigured(): bool
    {
        $key = trim((string) config('services.openai_images.key'));

        return $key !== '' && ! preg_match('/\s/', $key);
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $box
     * @return array{binary: string, model: string, usage: array}
     */
    public function generateBackground(string $reference, string $description, string $article, int $shopId, string $direction, array $box): array
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages(['style' => 'La génération de décors nécessite une clé API OpenAI configurée sur le serveur.']);
        }

        $lock = Cache::lock('product-scene-openai', 150);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['photo' => 'Un décor est déjà en préparation. Réessayez dans quelques instants ; vos crédits sont restitués.']);
        }

        try {
            $limit = max(0, (int) config('services.openai_images.daily_limit', 50));
            $counter = 'product-scene-openai:'.now('UTC')->format('Y-m-d');
            if ($limit === 0 || (int) Cache::get($counter, 0) >= $limit) {
                throw ValidationException::withMessages(['photo' => 'La limite quotidienne de génération de décors est atteinte. Réessayez demain ou choisissez un décor du studio.']);
            }
            Cache::put($counter, (int) Cache::get($counter, 0) + 1, now()->addDay());

            $model = (string) config('services.openai_images.model', 'gpt-image-2.5-flare');
            $quality = (string) config('services.openai_images.quality', 'medium');
            $prompt = $this->backgroundPrompt($description, $article, $direction, $box);

            try {
                $response = Http::withToken(trim((string) config('services.openai_images.key')))
                    ->acceptJson()->connectTimeout(10)->timeout(120)->withoutRedirecting()
                    ->attach('image', $reference, 'reference.png', ['Content-Type' => 'image/png'])
                    ->post('https://api.openai.com/v1/images/edits', [
                        'model' => $model, 'prompt' => $prompt, 'n' => '1',
                        'quality' => in_array($quality, ['low', 'medium', 'high'], true) ? $quality : 'medium',
                        'size' => '1024x1024', 'output_format' => 'png',
                        'safety_identifier' => hash('sha256', 'product-studio:'.$shopId),
                    ]);
            } catch (ConnectionException) {
                throw ValidationException::withMessages(['photo' => 'OpenAI n’a pas répondu à temps. Aucun rendu n’a été appliqué et vos crédits plateforme sont restitués. Un appel déjà traité peut rester facturé par OpenAI.']);
            }

            if (! $response->successful()) {
                $referenceId = (string) Str::uuid();
                Log::warning('OpenAI product background HTTP error', ['status' => $response->status(), 'reference' => $referenceId]);
                $message = match ($response->status()) {
                    401, 403 => 'L’accès au moteur de décors OpenAI doit être vérifié par l’administrateur.',
                    402, 429 => 'Le solde, le quota ou la limite de requêtes OpenAI ne permet pas de créer ce décor actuellement.',
                    400, 422 => 'Ce décor n’a pas pu être généré. Essayez une autre description ou une autre photo.',
                    default => 'Le moteur de décors OpenAI est temporairement indisponible.',
                };
                throw ValidationException::withMessages(['photo' => $message.' Votre photo est conservée et vos crédits plateforme sont restitués. Référence : '.$referenceId]);
            }

            $encoded = $response->json('data.0.b64_json');
            $binary = is_string($encoded) && strlen($encoded) <= 16 * 1024 * 1024 ? base64_decode($encoded, true) : false;
            $size = $binary ? @getimagesizefromstring($binary) : false;
            $image = $size && $size[0] === 1024 && $size[1] === 1024 && $size[2] === IMAGETYPE_PNG ? @imagecreatefromstring($binary) : false;
            if (! $image) {
                throw ValidationException::withMessages(['photo' => 'OpenAI a renvoyé un décor invalide. Votre photo est conservée et vos crédits plateforme sont restitués.']);
            }
            imagedestroy($image);

            return ['binary' => $binary, 'model' => $model, 'usage' => (array) $response->json('usage', [])];
        } finally {
            $lock->release();
        }
    }

    /** @param array{x: int, y: int, width: int, height: int} $box */
    private function backgroundPrompt(string $description, string $article, string $direction, array $box): string
    {
        $left = round($box['x'] / 10, 1);
        $right = round(($box['x'] + $box['width']) / 10, 1);
        $top = round($box['y'] / 10, 1);
        $bottom = round(($box['y'] + $box['height']) / 10, 1);
        $support = match ($direction) {
            'top' => "Shoot straight on at garment height. Run a real, slim horizontal rail just above the retained top edge at y={$top}% from the top. If the reference has hooks, line the rail up with those hooks. Otherwise add discreet clips above the waistband or shoulders, ending at the garment's upper edge. Keep the region below clear for the full length of the garment.",
            'center' => 'Shoot an editorial flat lay, camera orthogonal to the surface. The whole product region rests on one continuous tactile surface with no horizon or other height changes. Keep other objects and their shadows away from the product region.',
            'auto' => "Infer the original camera angle and physical pose from the pictured article. If it is photographed from above, use one continuous flat-lay surface. If it has a hanger, align a real rail with the upper edge at y={$top}% so its hooks meet the rail. For an upright or resting article, build the support under its footprint at y={$bottom}% and extend that surface below it. Choose exactly one arrangement.",
            default => "Place a continuous supporting surface where the product's footprint will touch it at y={$bottom}% from the top. The foreground surface should continue below that contact point. Match the product's photographed viewing angle and scale. Keep open space for its entire height. Never lift the product above the surface.",
        };
        $artDirection = $description !== '' ? mb_substr($description, 0, 500) : 'Invent a distinctive, believable campaign setting inspired by the article’s materials, colors and use. Choose one clear visual concept, a supporting palette and a recognizable material. Use context sparingly around the sides to frame the product.';

        return "Create a photorealistic background plate for a high-end product photograph. The supplied square image shows the exact cutout in its final position, with bounds x={$left}–{$right}%, y={$top}–{$bottom}% (origin at top left). Replace the neutral guide with an art-directed setting that physically accommodates that placement. {$support} Remove the reference product from the output completely. Leave clean space for it at the same exact coordinates. The original cutout will be added back later. Match the direction and softness of the product’s light, use plausible materials and leave the product region visually quiet. Keep any supporting props outside the silhouette. Do not draw duplicates, new products, people, product shadows, text or brand marks. Article: ".mb_substr($article, 0, 200).". Creative direction for the environment: {$artDirection}";
    }
}
