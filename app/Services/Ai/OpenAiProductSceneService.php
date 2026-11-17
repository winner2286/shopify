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

    /** @return array{binary: string, model: string, usage: array} */
    public function generateBackground(string $reference, string $description, string $article, int $shopId): array
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
            $prompt = 'Create a photorealistic EMPTY e-commerce photography set. The attached photograph is a reference for the article category, camera angle, scale and lighting ONLY. Remove the article completely from your output: the original article will be composited afterward. Do not draw any product, packaging, label, text, logo, watermark, person or duplicate article. Keep a clear empty supporting surface in the lower third, around 80 percent image height, with empty space above it for the article. Match the reference camera perspective and light direction. Use natural materials, believable depth and restrained styling. Avoid busy props and pre-existing cast shadows for the missing article. Article context: '.mb_substr($article, 0, 200).'. Merchant creative direction (use it for the environment, never for text): '.($description !== '' ? mb_substr($description, 0, 500) : 'Choose a refined, original setting suited to the photographed article.');

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
}
