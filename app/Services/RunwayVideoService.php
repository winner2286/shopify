<?php

namespace App\Services;

use App\Models\Advertisement;
use App\Models\AiGeneration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RunwayVideoService
{
    public function isConfigured(): bool
    {
        return filled(config('services.runway.key')) && (int) config('services.runway.credit_cost') > 0;
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.runway.key'))
            ->withHeaders(['X-Runway-Version' => '2024-11-06'])->acceptJson()->timeout(25);
    }

    public function submit(Advertisement $ad, array $data): array
    {
        abort_unless($this->isConfigured(), 503, 'La génération de plans vidéo IA n’est pas encore disponible. Vous pouvez importer vos clips.');
        $prompt = 'Preserve the exact product shape, branding and colors. No added text or logos. '.$data['prompt'];
        abort_if(strlen(mb_convert_encoding($prompt, 'UTF-16LE', 'UTF-8')) > 2000, 422, 'Raccourcissez les instructions du plan vidéo, notamment les émojis.');
        // Only server-resolved project images are sent; arbitrary URLs are never accepted.
        if (! empty($data['media_id'])) {
            $asset = collect($ad->visual_config['media'] ?? [])->firstWhere('id', $data['media_id']);
            abort_unless($asset && $asset['kind'] === 'image', 422, 'Choisissez une photo du projet pour créer ce plan.');
            $image = 'data:'.$asset['mime'].';base64,'.base64_encode(app(PublicMediaStorage::class)->get($asset['path']));
        } else {
            $product = $ad->visual_config['products'][$data['product_index']] ?? null;
            abort_unless($product, 422, 'Choisissez un produit du projet.');
            $image = $product['imageUrl'] ?? '';
        }
        abort_unless(str_starts_with($image, 'data:image/') && strlen($image) < 6500000, 422, 'Ajoutez une photo produit de moins de 5 Mo dans le catalogue.');
        $cost = (int) config('services.runway.credit_cost');
        [$job, $created] = DB::transaction(function () use ($ad, $data, $cost) {
            $shop = $ad->shop()->lockForUpdate()->firstOrFail();
            $existing = AiGeneration::where('shop_id', $shop->id)->where('step', 'creative_video_shot')
                ->where('output_data->request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless(($existing->output_data['ad_id'] ?? null) === $ad->id, 409);

                return [$existing, false];
            }
            abort_if(AiGeneration::where('shop_id', $shop->id)->where('step', 'creative_video_shot')->whereIn('status', ['pending', 'unknown'])->exists(), 409, 'Un plan vidéo est déjà en cours. Vérifiez son état avant d’en lancer un autre.');
            $shop->consumeAiCredits($cost);
            $job = AiGeneration::create([
                'shop_id' => $shop->id, 'type' => 'ai_video', 'step' => 'creative_video_shot',
                'model_used' => 'runway:gen4_turbo', 'status' => 'pending',
                'input_summary' => 'Plan vidéo produit de 5 secondes',
                'output_data' => ['ad_id' => $ad->id, 'request_id' => $data['request_id'], 'cost' => $cost, 'prompt' => $data['prompt']],
            ]);

            return [$job, true];
        });
        if (! $created) {
            return $this->summary($ad, $job);
        }
        try {
            $response = $this->client()->post('https://api.dev.runwayml.com/v1/image_to_video', [
                'model' => 'gen4_turbo', 'promptImage' => $image,
                'promptText' => $prompt,
                'ratio' => '720:1280', 'duration' => 5,
            ]);
            if ($response->clientError()) {
                $this->failAndRefund($job);
                abort(422, 'Le service vidéo a refusé cette demande. Les crédits réservés ont été restitués.');
            }
            if (! $response->successful() || ! is_string($response->json('id'))) {
                $job->update(['status' => 'unknown']);
                abort(502, 'La demande n’a pas pu être confirmée. Vérifiez son état avant de relancer.');
            }
            $job->update(['output_data' => $job->output_data + ['provider_id' => $response->json('id')]]);
        } catch (ConnectionException $e) {
            $job->update(['status' => 'unknown']);
            abort(502, 'Connexion interrompue. La demande doit être vérifiée avant toute nouvelle génération.');
        }

        return $this->summary($ad, $job->fresh());
    }

    public function poll(Advertisement $ad, int $jobId): array
    {
        $job = AiGeneration::whereKey($jobId)->where('shop_id', $ad->shop_id)->where('step', 'creative_video_shot')->firstOrFail();
        abort_unless(($job->output_data['ad_id'] ?? null) === $ad->id, 403);
        $providerId = $job->output_data['provider_id'] ?? null;
        if ($job->status !== 'pending' || ! $providerId) {
            return $this->summary($ad, $job);
        }
        abort_unless(preg_match('/^[a-zA-Z0-9-]{1,100}$/', $providerId), 422);
        $response = $this->client()->get('https://api.dev.runwayml.com/v1/tasks/'.$providerId);
        $response->throw();
        if (in_array($response->json('status'), ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
            $this->failAndRefund($job);
        } elseif ($response->json('status') === 'SUCCEEDED') {
            $url = $response->json('output.0');
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            $allowed = $host && collect(['cloudfront.net', 'runwayml.com', 'runwayml.cloud'])->contains(fn ($suffix) => $host === $suffix || str_ends_with($host, '.'.$suffix));
            abort_unless($allowed && parse_url($url, PHP_URL_SCHEME) === 'https' && ! parse_url($url, PHP_URL_USER) && in_array(parse_url($url, PHP_URL_PORT), [null, 443], true), 502, 'Le plan vidéo reçu ne peut pas être importé automatiquement.');
            $video = Http::timeout(40)->withOptions([
                'allow_redirects' => false,
                'progress' => function ($total, $received): void {
                    if ($total > 15 * 1024 * 1024 || $received > 15 * 1024 * 1024) {
                        throw new \RuntimeException('Vidéo supérieure à 15 Mo.');
                    }
                },
            ])->get($url)->throw()->body();
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($video);
            abort_unless(strlen($video) <= 15 * 1024 * 1024 && in_array($mime, ['video/mp4', 'video/webm'], true), 502, 'Le plan vidéo reçu est invalide.');
            DB::transaction(function () use ($ad, $job, $video, $mime) {
                $job = AiGeneration::whereKey($job->id)->lockForUpdate()->firstOrFail();
                if ($job->status !== 'pending') {
                    return;
                }
                $ad = Advertisement::whereKey($ad->id)->lockForUpdate()->firstOrFail();
                $vc = $ad->visual_config ?? [];
                abort_if(count($vc['media'] ?? []) >= 20, 422, 'Libérez un emplacement média avant d’importer le plan terminé.');
                $id = (string) Str::uuid();
                $path = "shops/{$ad->shop_id}/ads/{$ad->id}/{$id}.".($mime === 'video/webm' ? 'webm' : 'mp4');
                app(PublicMediaStorage::class)->put($path, $video);
                $asset = ['id' => $id, 'path' => $path, 'kind' => 'video', 'mime' => $mime, 'name' => 'Plan vidéo IA', 'url' => route('ads.studio.media', [$ad, $id]), 'source' => 'runway'];
                $vc['media'][] = $asset;
                $ad->update(['visual_config' => $vc]);
                $job->update(['status' => 'success', 'output_data' => $job->output_data + ['asset' => $asset]]);
            });
        }

        return $this->summary($ad, $job->fresh());
    }

    private function failAndRefund(AiGeneration $job): void
    {
        DB::transaction(function () use ($job) {
            $job = AiGeneration::whereKey($job->id)->lockForUpdate()->firstOrFail();
            if (in_array($job->status, ['pending', 'unknown'], true)) {
                $shop = $job->shop()->lockForUpdate()->firstOrFail();
                $shop->update(['ai_credits_used' => max(0, $shop->ai_credits_used - (int) $job->output_data['cost'])]);
                $job->update(['status' => 'failed']);
            }
        });
    }

    private function summary(Advertisement $ad, AiGeneration $job): array
    {
        return ['job' => $job->id, 'status' => $job->status, 'cost' => $job->output_data['cost'], 'asset' => $job->output_data['asset'] ?? null, 'status_url' => route('ads.studio.ai_status', [$ad, $job->id])];
    }
}
