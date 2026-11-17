<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\AiGeneration;
use App\Services\AiAdvertisingService;
use App\Services\CreativeCampaignPlanner;
use App\Services\PublicMediaStorage;
use App\Services\RunwayVideoService;
use App\Services\VideoAdGeneratorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreativeStudioController extends Controller
{
    public function create(Request $request): View
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);
        $products = $shop->products()->where('status', 'active')->with('primaryImage')->get();
        $initialFormat = $request->routeIs('ads.flyers') ? 'poster' : 'video';

        return view('advertising.brief', compact('shop', 'products', 'initialFormat'));
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);
        $data = $request->validate([
            'creative_brief' => ['nullable', 'string', 'max:1200'],
            'audience' => ['nullable', 'string', 'max:180'],
            'selected_product_ids' => ['nullable', 'array', 'max:5'],
            'selected_product_ids.*' => ['integer', Rule::exists('products', 'id')->where('shop_id', $shop->id)->where('status', 'active')],
            'channel' => ['required', Rule::in(['tiktok', 'instagram', 'whatsapp', 'facebook', 'general'])],
            'target_duration' => ['required', 'integer', Rule::in([15, 20, 25, 30, 45])],
            'initial_format' => ['required', Rule::in(['poster', 'video'])],
            'use_ai' => ['required', 'boolean'],
            'enhance_photoroom' => ['nullable', 'boolean'],
        ]);
        $data += ['angle' => 'nouveautes', 'tone' => 'dynamique'];
        if ($request->boolean('use_ai')) {
            $ad = app(AiAdvertisingService::class)->generate($shop, $data);
        } else {
            $data['enhance_photoroom'] = false;
            $campaign = app(VideoAdGeneratorService::class)->generateVideoCampaign($shop, $data + ['skip_ai' => true]);
            $ad = Advertisement::create([
                'shop_id' => $shop->id, 'product_id' => $campaign['products'][0]['id'] ?? null,
                'channel' => $data['channel'], 'objective' => 'nouveaute', 'tone' => 'dynamique',
                'generated_scripts' => $campaign['scripts'], 'generated_texts' => [],
                'visual_config' => $campaign['visual_config'],
            ]);
        }

        return redirect()->route('ads.show', $ad);
    }

    public function show(Request $request, Advertisement $ad): View
    {
        $shop = $request->user()->shop;
        abort_unless($shop && $ad->shop_id === $shop->id, 403);
        $vc = $ad->visual_config ?? [];
        if (empty($vc['products'])) {
            $generator = app(VideoAdGeneratorService::class);
            $vc['products'] = $generator->formatProductsPayload($generator->selectProductsForAd($shop, 'nouveautes', $ad->product_id), $shop);
            $ad->update(['visual_config' => $vc]);
        }
        $scripts = $ad->generated_scripts ?? [];
        if (empty($vc['studio_version']) || empty($scripts[0]['scenes'])) {
            $scripts = app(CreativeCampaignPlanner::class)->plan($shop, $vc['products'], ['target_duration' => 20]);
        }
        $studio = [
            'id' => $ad->id, 'revision' => $vc['revision'] ?? 0, 'scripts' => $scripts,
            'products' => $vc['products'], 'media' => $vc['media'] ?? [],
            'shopName' => $shop->name, 'shopColor' => $vc['creative_color'] ?? ($shop->brand_color ?: '#a91638'),
            'directedByAi' => ! in_array($vc['generation_source'] ?? 'composed', ['composed', 'zanika-local-template'], true),
            'shopUrl' => $shop->public_url, 'shopPhone' => $shop->whatsapp ?: $shop->phone,
            'platform' => $vc['platform'] ?? $ad->channel,
            'activeConcept' => $vc['active_concept'] ?? 0, 'initialFormat' => $vc['initial_format'] ?? 'video',
            'posterLayout' => $vc['poster_layout'] ?? 'editorial',
            'saveUrl' => route('ads.studio.save', $ad), 'uploadUrl' => route('ads.studio.upload', $ad),
            'aiUrl' => route('ads.studio.ai', $ad), 'csrf' => csrf_token(),
            'aiAvailable' => app(RunwayVideoService::class)->isConfigured(),
            'aiCost' => (int) config('services.runway.credit_cost'),
            'voiceUrl' => $vc['custom_voice_url'] ?? '', 'musicUrl' => $vc['custom_music_url'] ?? '',
            'audioUrl' => route('ads.upload_custom_audio', $ad),
            'exportLogUrl' => route('ads.log_video_export', $ad),
            'texts' => $ad->generated_texts ?? [],
            'creativeBrief' => $vc['creative_brief'] ?? '',
            'regenerateUrl' => route('ads.regenerate', $ad),
            'deleteMediaUrl' => route('ads.studio.delete_media', [$ad, 'MEDIA_ID']),
        ];
        $job = AiGeneration::where('shop_id', $shop->id)->where('step', 'creative_video_shot')
            ->where('output_data->ad_id', $ad->id)->whereIn('status', ['pending', 'unknown'])->latest('id')->first();
        if ($job) {
            $studio['pendingJob'] = ['status_url' => route('ads.studio.ai_status', [$ad, $job->id])];
        }

        return view('advertising.editor', compact('shop', 'ad', 'studio'));
    }

    public function save(Request $request, Advertisement $ad): JsonResponse
    {
        $shop = $request->user()->shop;
        abort_unless($shop && $ad->shop_id === $shop->id, 403);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'active_concept' => ['required', 'integer', 'between:0,2'],
            'poster_layout' => ['required', Rule::in(['editorial', 'bold', 'split', 'grid'])],
            'texts' => ['nullable', 'array', 'max:3'],
            'texts.*' => ['nullable', 'string', 'max:3000'],
            'creative_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'scripts' => ['required', 'array', 'min:1', 'max:3'],
            'scripts.*.id' => ['required', 'string', 'max:100'],
            'scripts.*.title' => ['required', 'string', 'max:120'],
            'scripts.*.concept' => ['nullable', 'string', 'max:400'],
            'scripts.*.seed' => ['nullable', 'integer', 'between:1,9999999'],
            'scripts.*.scenes' => ['required', 'array', 'min:1', 'max:20'],
            'scripts.*.scenes.*.id' => ['required', 'string', 'max:100'],
            'scripts.*.scenes.*.type' => ['required', Rule::in(['hook', 'product', 'feature', 'cta'])],
            'scripts.*.scenes.*.title' => ['nullable', 'string', 'max:160'],
            'scripts.*.scenes.*.subtitle' => ['nullable', 'string', 'max:240'],
            'scripts.*.scenes.*.voiceover' => ['nullable', 'string', 'max:700'],
            'scripts.*.scenes.*.duration' => ['required', 'numeric', 'between:0.5,15'],
            'scripts.*.scenes.*.product_index' => ['nullable', 'integer', 'min:0', 'max:4'],
            'scripts.*.scenes.*.image_index' => ['nullable', 'integer', 'between:0,4'],
            'scripts.*.scenes.*.layout' => ['required', Rule::in(CreativeCampaignPlanner::LAYOUTS)],
            'scripts.*.scenes.*.motion' => ['required', Rule::in(['push', 'pan', 'rise', 'none'])],
            'scripts.*.scenes.*.transition' => ['required', Rule::in(['cut', 'dissolve', 'wipe'])],
            'scripts.*.scenes.*.fit' => ['required', Rule::in(['cover', 'contain'])],
            'scripts.*.scenes.*.media_id' => ['nullable', 'string', 'max:100'],
            'scripts.*.scenes.*.media_start' => ['nullable', 'numeric', 'between:0,300'],
            'scripts.*.scenes.*.keep_audio' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($ad, $data) {
            $ad = Advertisement::whereKey($ad->id)->lockForUpdate()->firstOrFail();
            $vc = $ad->visual_config ?? [];
            abort_if(($vc['revision'] ?? 0) !== $data['revision'], 409, 'Ce projet a changé dans une autre fenêtre. Rechargez avant d’enregistrer.');
            $mediaIds = array_column($vc['media'] ?? [], 'id');
            foreach ($data['scripts'] as $script) {
                abort_if(array_sum(array_column($script['scenes'], 'duration')) > 90, 422, 'Le montage ne peut pas dépasser 90 secondes.');
                foreach ($script['scenes'] as $scene) {
                    abort_if(! empty($scene['media_id']) && ! in_array($scene['media_id'], $mediaIds, true), 422, 'Média non rattaché au projet.');
                    abort_if(isset($scene['product_index']) && ! isset($vc['products'][$scene['product_index']]), 422, 'Produit non rattaché au projet.');
                }
            }
            $vc['revision'] = $data['revision'] + 1;
            $vc['studio_version'] = 2;
            $vc['active_concept'] = $data['active_concept'];
            $vc['poster_layout'] = $data['poster_layout'];
            $vc['creative_color'] = $data['creative_color'] ?? ($vc['creative_color'] ?? null);
            $scripts = array_map(function ($script) {
                $clean = array_intersect_key($script, array_flip(['id', 'title', 'concept', 'seed']));
                $clean['scenes'] = array_map(fn ($scene) => array_intersect_key($scene, array_flip([
                    'id', 'type', 'title', 'subtitle', 'voiceover', 'duration', 'product_index', 'image_index',
                    'layout', 'motion', 'transition', 'fit', 'media_id', 'media_start', 'keep_audio',
                ])), $script['scenes']);

                return $clean;
            }, $data['scripts']);
            abort_if($data['active_concept'] >= count($scripts), 422, 'Choisissez une direction existante.');
            $ad->update(['generated_scripts' => $scripts, 'visual_config' => $vc, 'generated_texts' => $data['texts'] ?? $ad->generated_texts]);

            return response()->json(['revision' => $vc['revision']]);
        });
    }

    public function upload(Request $request, Advertisement $ad): JsonResponse
    {
        $shop = $request->user()->shop;
        abort_unless($shop && $ad->shop_id === $shop->id, 403);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm', 'max:15360']]);
        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
        if ($isImage) {
            $size = @getimagesize($file->getRealPath());
            if (! $size || max($size[0], $size[1]) > 6000 || $size[0] * $size[1] > 16000000) {
                throw ValidationException::withMessages(['file' => 'Choisissez une image de moins de 16 mégapixels.']);
            }
        }
        $id = (string) Str::uuid();
        $path = "shops/{$shop->id}/ads/{$ad->id}/{$id}.".$file->extension();
        app(PublicMediaStorage::class)->put($path, $file->getContent());
        try {
            $asset = DB::transaction(function () use ($ad, $path, $id, $file, $isImage) {
                $ad = Advertisement::whereKey($ad->id)->lockForUpdate()->firstOrFail();
                $vc = $ad->visual_config ?? [];
                abort_if(count($vc['media'] ?? []) >= 20, 422, 'Limite de 20 imports par projet atteinte.');
                $asset = ['id' => $id, 'path' => $path, 'kind' => $isImage ? 'image' : 'video', 'name' => Str::limit(basename($file->getClientOriginalName()), 80), 'mime' => $file->getMimeType(), 'url' => route('ads.studio.media', [$ad, $id])];
                $vc['media'][] = $asset;
                $ad->update(['visual_config' => $vc]);

                return $asset;
            });
        } catch (\Throwable $e) {
            app(PublicMediaStorage::class)->delete($path);
            throw $e;
        }

        return response()->json(['asset' => $asset]);
    }

    public function media(Request $request, Advertisement $ad, string $media): Response
    {
        $shop = $request->user()->shop;
        abort_unless($shop && $ad->shop_id === $shop->id, 403);
        $asset = collect($ad->visual_config['media'] ?? [])->firstWhere('id', $media);
        abort_unless($asset, 404);
        $binary = app(PublicMediaStorage::class)->get($asset['path']);
        $length = strlen($binary);
        $headers = ['Content-Type' => $asset['mime'], 'Cache-Control' => 'private, no-store', 'Accept-Ranges' => 'bytes', 'X-Content-Type-Options' => 'nosniff'];
        if (preg_match('/^bytes=(\d+)-(\d*)$/', (string) $request->header('Range'), $range)) {
            $start = (int) $range[1];
            $end = $range[2] !== '' ? min((int) $range[2], $length - 1) : $length - 1;
            if ($start > $end || $start >= $length) {
                return response('', 416, ['Content-Range' => "bytes */{$length}"]);
            }

            return response(substr($binary, $start, $end - $start + 1), 206, $headers + ['Content-Range' => "bytes {$start}-{$end}/{$length}", 'Content-Length' => $end - $start + 1]);
        }

        return response($binary, 200, $headers + ['Content-Length' => $length]);
    }

    public function generateShot(Request $request, Advertisement $ad, RunwayVideoService $runway): JsonResponse
    {
        abort_unless($request->user()->shop?->id === $ad->shop_id, 403);
        $data = $request->validate(['product_index' => ['nullable', 'required_without:media_id', 'integer', 'between:0,4'], 'media_id' => ['nullable', 'uuid'], 'prompt' => ['required', 'string', 'max:700'], 'request_id' => ['required', 'uuid']]);

        return response()->json($runway->submit($ad, $data));
    }

    public function deleteMedia(Request $request, Advertisement $ad, string $media): JsonResponse
    {
        abort_unless($request->user()->shop?->id === $ad->shop_id, 403);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($ad, $media, $data) {
            $ad = Advertisement::whereKey($ad->id)->lockForUpdate()->firstOrFail();
            $vc = $ad->visual_config ?? [];
            abort_if(($vc['revision'] ?? 0) !== $data['revision'], 409, 'Rechargez ce projet avant de retirer ce média.');
            $asset = collect($vc['media'] ?? [])->firstWhere('id', $media);
            abort_unless($asset, 404);
            app(PublicMediaStorage::class)->delete($asset['path']);
            $vc['media'] = array_values(array_filter($vc['media'], fn ($item) => $item['id'] !== $media));
            $vc['revision'] = $data['revision'] + 1;
            $scripts = $ad->generated_scripts ?? [];
            foreach ($scripts as &$script) {
                foreach ($script['scenes'] as &$scene) {
                    if (($scene['media_id'] ?? null) === $media) {
                        $scene['media_id'] = null;
                    }
                }
                unset($scene);
            }
            unset($script);
            $ad->update(['visual_config' => $vc, 'generated_scripts' => $scripts]);

            return response()->json(['revision' => $vc['revision']]);
        });
    }

    public function shotStatus(Request $request, Advertisement $ad, int $job, RunwayVideoService $runway): JsonResponse
    {
        abort_unless($request->user()->shop?->id === $ad->shop_id, 403);

        return response()->json($runway->poll($ad, $job));
    }
}
