<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
            throw new ProductSceneUnavailable('Un décor est déjà en préparation. Réessayez dans quelques instants. Votre quota de décors IA est rétabli.');
        }

        try {
            $limit = max(0, (int) config('services.openai_images.daily_limit', 50));
            $counter = 'product-scene-openai:'.now('UTC')->format('Y-m-d');
            if ($limit === 0 || (int) Cache::get($counter, 0) >= $limit) {
                throw new ProductSceneUnavailable('La limite quotidienne de décors IA de Zanika est atteinte. Vous pourrez générer un nouveau décor IA demain. Votre quota est rétabli.');
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
                throw new ProductSceneUnavailable('OpenAI n’a pas répondu à temps. Votre quota de décors IA est rétabli. Un appel déjà traité peut rester facturé par OpenAI.');
            }

            if (! $response->successful()) {
                throw $this->providerFailure($response);
            }

            $encoded = $response->json('data.0.b64_json');
            $binary = is_string($encoded) && strlen($encoded) <= 16 * 1024 * 1024 ? base64_decode($encoded, true) : false;
            $size = $binary ? @getimagesizefromstring($binary) : false;
            $image = $size && $size[0] === 1024 && $size[1] === 1024 && $size[2] === IMAGETYPE_PNG ? @imagecreatefromstring($binary) : false;
            if (! $image) {
                throw new ProductSceneUnavailable('OpenAI a renvoyé un décor invalide. Votre photo est conservée et votre quota de décors IA est rétabli.');
            }
            imagedestroy($image);

            return ['binary' => $binary, 'model' => $model, 'usage' => (array) $response->json('usage', [])];
        } finally {
            $lock->release();
        }
    }

    private function providerFailure(Response $response): ProductSceneUnavailable
    {
        $code = $response->json('error.code');
        $type = $response->json('error.type');
        $reason = match ($code) {
            'credit_balance_exhausted', 'organization_spend_limit_exceeded', 'project_spend_limit_exceeded',
            'organization_usage_limit_exceeded', 'insufficient_quota', 'billing_hard_limit_reached',
            'rate_limit_exceeded', 'slow_down', 'server_is_overloaded' => $code,
            default => match ($type) {
                'insufficient_quota', 'rate_limit_error' => $type,
                default => 'unknown',
            },
        };
        $status = $response->status();
        $transient = in_array($reason, ['rate_limit_exceeded', 'slow_down', 'rate_limit_error', 'server_is_overloaded'], true)
            || ($reason === 'unknown' && $status === 503);
        $retryAfter = $transient
            ? (filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null)
            : null;

        $message = match ($reason) {
            'credit_balance_exhausted' => 'Le solde de crédits de l’API OpenAI est épuisé. L’administrateur doit vérifier la facturation de l’API.',
            'organization_spend_limit_exceeded', 'project_spend_limit_exceeded', 'billing_hard_limit_reached' => 'Le plafond de dépenses de l’API OpenAI est atteint. L’administrateur doit vérifier ce plafond.',
            'organization_usage_limit_exceeded' => 'La limite d’utilisation de l’API OpenAI est atteinte. L’administrateur doit vérifier le quota de l’organisation.',
            'insufficient_quota' => 'Le compte API OpenAI a atteint une limite de facturation ou de quota. L’administrateur doit vérifier le compte.',
            'rate_limit_exceeded', 'slow_down', 'rate_limit_error' => 'OpenAI reçoit trop de demandes de décors pour le moment.',
            'server_is_overloaded' => 'Le moteur de décors OpenAI est surchargé pour le moment.',
            default => match ($status) {
                401, 403 => 'L’administrateur doit vérifier l’accès au moteur de décors OpenAI.',
                402 => 'OpenAI a refusé la génération pour un motif de facturation. L’administrateur doit vérifier le compte API.',
                429 => 'OpenAI a limité la génération de décors. L’administrateur peut vérifier le motif avec la référence ci-dessous.',
                400, 422 => 'Le décor ne peut pas être généré à partir de cette demande. Essayez une autre description ou une autre photo.',
                default => 'Le moteur de décors OpenAI est temporairement indisponible.',
            },
        };
        if ($transient) {
            $message .= $retryAfter ? " Réessayez dans au moins {$retryAfter} s." : ' Réessayez dans quelques instants.';
        }

        $referenceId = (string) Str::uuid();
        $requestId = $response->header('x-request-id');
        Log::warning('OpenAI product background HTTP error', [
            'status' => $status,
            'reference' => $referenceId,
            'provider_code' => $reason,
            'provider_request_id' => preg_match('/\Areq_[0-9a-f]{16,64}\z/i', $requestId) ? $requestId : null,
            'retry_after' => $retryAfter,
        ]);

        return new ProductSceneUnavailable($message.' Votre photo est conservée et votre quota de décors IA est rétabli. Référence '.$referenceId, $retryAfter);
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
