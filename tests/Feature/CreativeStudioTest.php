<?php

namespace Tests\Feature;

use App\Http\Controllers\AdvertisingController;
use App\Models\Advertisement;
use App\Models\Shop;
use App\Models\User;
use App\Services\AiAdvertisingService;
use App\Services\CreativeCampaignPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreativeStudioTest extends TestCase
{
    use RefreshDatabase;

    private function project(): array
    {
        $user = User::factory()->create(['role' => 'merchant']);
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Studio Test', 'subdomain' => 'studio-'.Str::random(10), 'currency' => 'XOF', 'status' => 'published']);
        $product = $shop->products()->create(['title' => 'Burger Signature', 'slug' => 'burger-signature', 'price' => 4500, 'stock' => 8, 'status' => 'active']);
        $image = 'data:image/webp;base64,'.base64_encode(file_get_contents(public_path('images/catalog/burger.webp')));
        $products = [['id' => $product->id, 'title' => $product->title, 'price' => '4 500 XOF', 'category' => 'La carte', 'imageUrl' => $image]];
        $ad = Advertisement::create(['shop_id' => $shop->id, 'product_id' => $product->id, 'channel' => 'whatsapp', 'objective' => 'nouveaute', 'tone' => 'dynamique', 'generated_texts' => [], 'generated_scripts' => app(CreativeCampaignPlanner::class)->plan($shop, $products, ['target_duration' => 20]), 'visual_config' => ['products' => $products, 'revision' => 0, 'studio_version' => 2, 'media' => []]]);

        return [$user, $shop, $ad];
    }

    private function editPayload(Advertisement $ad): array
    {
        return ['revision' => 0, 'active_concept' => 0, 'poster_layout' => 'editorial', 'scripts' => $ad->generated_scripts];
    }

    public function test_manual_project_uses_no_paid_provider_and_spends_no_ai_credits(): void
    {
        Http::preventStrayRequests();
        [$user, $shop, $old] = $this->project();
        $this->actingAs($user)->post(route('ads.studio.store'), ['use_ai' => false, 'channel' => 'whatsapp', 'initial_format' => 'poster', 'target_duration' => 15, 'selected_product_ids' => [$old->product_id]])->assertRedirect();
        $ad = $shop->advertisements()->latest('id')->first();
        $this->assertNotSame($old->id, $ad->id);
        $this->assertCount(3, $ad->generated_scripts);
        $this->assertSame('poster', $ad->visual_config['initial_format']);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
    }

    public function test_directions_have_different_shot_structures_and_preserve_target_duration(): void
    {
        [, , $ad] = $this->project();
        $layouts = array_map(fn ($script) => implode(',', array_column($script['scenes'], 'layout')), $ad->generated_scripts);
        $this->assertCount(3, array_unique($layouts));
        foreach ($ad->generated_scripts as $script) {
            $this->assertEqualsWithDelta(20, array_sum(array_column($script['scenes'], 'duration')), 0.1);
            $this->assertSame('cta', $script['scenes'][array_key_last($script['scenes'])]['layout']);
        }
    }

    public function test_ai_concepts_keep_original_titles_scene_direction_and_the_merchant_brief(): void
    {
        [$user, $shop] = $this->project();
        $this->actingAs($user);
        config(['services.groq.key' => 'test-key', 'services.openrouter.key' => null, 'services.gemini.key' => null]);
        Http::preventStrayRequests();
        $concepts = [];
        foreach (['L’heure de la pause', 'Le détail gourmand', 'Une carte à explorer'] as $title) {
            $concepts[] = ['title' => $title, 'concept' => 'Une histoire dédiée au lancement de la carte.', 'scenes' => [
                ['type' => 'hook', 'title' => $title, 'subtitle' => 'La carte', 'voiceover' => '', 'duration' => 2, 'layout' => 'typography'],
                ['type' => 'product', 'title' => 'Regardez la texture', 'subtitle' => 'Burger Signature', 'product_index' => 0, 'duration' => 3, 'layout' => 'detail', 'motion' => 'pan', 'transition' => 'wipe'],
                ['type' => 'cta', 'title' => 'Découvrez la carte', 'duration' => 3, 'layout' => 'cta'],
            ]];
        }
        Http::fake(['https://api.groq.com/openai/v1/chat/completions' => function ($request) use ($concepts) {
            $this->assertStringContainsString('Un lancement street-food, vif et contrasté', $request['messages'][1]['content']);

            return Http::response(['choices' => [['message' => ['content' => json_encode(['texts' => ['Découvrez la carte.'], 'scripts' => $concepts])]]]], 200);
        }]);
        $ad = app(AiAdvertisingService::class)->generate($shop, ['channel' => 'whatsapp', 'creative_brief' => 'Un lancement street-food, vif et contrasté']);
        $this->assertSame(array_column($concepts, 'title'), array_column($ad->generated_scripts, 'title'));
        $this->assertSame('Regardez la texture', $ad->generated_scripts[0]['scenes'][1]['title']);
        $this->assertSame('detail', $ad->generated_scripts[0]['scenes'][1]['layout']);
        $this->assertSame(1, $shop->fresh()->ai_credits_used);
        Http::assertSentCount(1);
    }

    public function test_retiming_preserves_target_without_creating_unsaveable_long_shots(): void
    {
        [, $shop, $ad] = $this->project();
        $suggestion = ['scenes' => array_map(fn ($seconds) => ['type' => 'product', 'duration' => $seconds], [15, 0.5, 0.5, 0.5, 0.5])];
        $scripts = app(CreativeCampaignPlanner::class)->plan($shop, $ad->visual_config['products'], ['target_duration' => 45], [$suggestion]);
        foreach ($scripts as $script) {
            $durations = array_column($script['scenes'], 'duration');
            $this->assertLessThanOrEqual(15, max($durations));
            $this->assertGreaterThanOrEqual(0.5, min($durations));
            $this->assertEqualsWithDelta(45, array_sum($durations), 0.01);
        }
    }

    public function test_all_three_workspace_pages_render_without_warming_external_tts(): void
    {
        Http::preventStrayRequests();
        [$user, , $ad] = $this->project();
        $library = $this->actingAs($user)->get(route('ads.index'))->assertOk()->assertSee('Vos projets');
        $brief = $this->get(route('ads.create'))->assertOk()->assertSee('Votre intention créative');
        $editor = $this->get(route('ads.show', $ad))->assertOk()->assertSee('Votre montage')->assertSee('studio-prepare-voice');
        if (getenv('CREATIVE_STUDIO_REVIEW') === '1') {
            foreach (['library' => $library, 'brief' => $brief, 'editor' => $editor] as $name => $response) {
                file_put_contents(storage_path('app/private/studio-review-'.$name.'.html'), str_replace(
                    ['http://localhost:8080', 'http://localhost', 'http:\/\/localhost:8080', 'http:\/\/localhost'],
                    ['http://127.0.0.1:8127', 'http://127.0.0.1:8127', 'http:\/\/127.0.0.1:8127', 'http:\/\/127.0.0.1:8127'],
                    $response->getContent()
                ));
            }
        }
        Http::assertNothingSent();
    }

    public function test_edit_saves_reordered_shots_without_charging_and_rejects_stale_revision(): void
    {
        [$user, $shop, $ad] = $this->project();
        $payload = $this->editPayload($ad);
        $payload['scripts'][0]['scenes'] = array_reverse($payload['scripts'][0]['scenes']);
        $payload['scripts'][0]['scenes'][0]['title'] = 'Une direction personnelle';
        $this->actingAs($user)->putJson(route('ads.studio.save', $ad), $payload)->assertOk()->assertJsonPath('revision', 1);
        $this->assertSame('Une direction personnelle', $ad->fresh()->generated_scripts[0]['scenes'][0]['title']);
        $this->putJson(route('ads.studio.save', $ad), $payload)->assertConflict();
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
    }

    public function test_foreign_media_cannot_be_injected_into_a_project(): void
    {
        [$user, , $ad] = $this->project();
        $payload = $this->editPayload($ad);
        $payload['scripts'][0]['scenes'][0]['media_id'] = (string) Str::uuid();
        $this->actingAs($user)->putJson(route('ads.studio.save', $ad), $payload)->assertUnprocessable();
        $this->assertSame(0, $ad->fresh()->visual_config['revision']);
    }

    public function test_upload_is_recoverable_after_disk_loss_and_supports_private_byte_ranges(): void
    {
        Storage::fake('public');
        [$user, , $ad] = $this->project();
        $result = $this->actingAs($user)->postJson(route('ads.studio.upload', $ad), ['file' => UploadedFile::fake()->image('produit.png', 120, 160)])->assertOk();
        $asset = $result->json('asset');
        Storage::disk('public')->delete($asset['path']);
        $this->get($asset['url'], ['Range' => 'bytes=0-7'])->assertStatus(206)->assertHeader('Content-Length', '8');
        $this->assertDatabaseHas('public_media', ['path' => $asset['path']]);
        $this->actingAs(User::factory()->create(['role' => 'merchant']))->get($asset['url'])->assertNotFound();
    }

    public function test_invalid_image_is_not_written(): void
    {
        Storage::fake('public');
        [$user, , $ad] = $this->project();
        $file = UploadedFile::fake()->createWithContent('invalid.png', 'not an image');
        $this->actingAs($user)->postJson(route('ads.studio.upload', $ad), ['file' => $file])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_deleting_an_import_removes_backups_and_detaches_every_shot(): void
    {
        Storage::fake('public');
        [$user, , $ad] = $this->project();
        $asset = $this->actingAs($user)->postJson(route('ads.studio.upload', $ad), ['file' => UploadedFile::fake()->image('photo.png', 120, 120)])->assertOk()->json('asset');
        $payload = $this->editPayload($ad);
        $payload['scripts'][0]['scenes'][0]['media_id'] = $asset['id'];
        $payload['scripts'][1]['scenes'][1]['media_id'] = $asset['id'];
        $this->putJson(route('ads.studio.save', $ad), $payload)->assertOk();
        $this->deleteJson(route('ads.studio.delete_media', [$ad, $asset['id']]), ['revision' => 1])->assertOk()->assertJsonPath('revision', 2);
        $this->assertSame([], $ad->fresh()->visual_config['media']);
        $this->assertNull($ad->fresh()->generated_scripts[1]['scenes'][1]['media_id']);
        $this->assertDatabaseMissing('public_media', ['path' => $asset['path']]);
        Storage::disk('public')->assertMissing($asset['path']);
    }

    public function test_replanning_does_not_overwrite_edits_saved_while_the_provider_was_running(): void
    {
        [$user, $shop, $ad] = $this->project();
        config(['services.groq.key' => 'test-key', 'services.openrouter.key' => null, 'services.gemini.key' => null]);
        Http::preventStrayRequests();
        Http::fake(['https://api.groq.com/openai/v1/chat/completions' => function () use ($ad) {
            $vc = $ad->visual_config;
            $vc['revision'] = 1;
            $scripts = $ad->generated_scripts;
            $scripts[0]['scenes'][0]['title'] = 'Mon montage validé';
            $ad->update(['visual_config' => $vc, 'generated_scripts' => $scripts]);

            return Http::response(['choices' => [['message' => ['content' => json_encode(['texts' => ['Une autre piste'], 'scripts' => [['title' => 'Piste', 'scenes' => [['type' => 'hook', 'title' => 'Une autre vue', 'subtitle' => '', 'voiceover' => 'Découvrez la sélection.']]]]])]]]], 200);
        }]);
        $this->actingAs($user)->postJson(route('ads.regenerate', $ad), ['revision' => 0, 'creative_brief' => 'Un autre point de vue'])->assertUnprocessable();
        $this->assertSame('Mon montage validé', $ad->fresh()->generated_scripts[0]['scenes'][0]['title']);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
    }

    public function test_another_shop_cannot_read_or_change_a_project(): void
    {
        [$user, , $ad] = $this->project();
        [$otherUser, , $otherAd] = $this->project();
        $this->actingAs($otherUser)->get(route('ads.show', $ad))->assertNotFound();
        $this->putJson(route('ads.studio.save', $ad), $this->editPayload($otherAd))->assertNotFound();
    }

    public function test_audio_upload_merges_with_current_edits_even_when_route_model_is_stale(): void
    {
        Storage::fake('public');
        [$user, , $ad] = $this->project();
        $this->actingAs($user);
        $fresh = $ad->fresh();
        $vc = $fresh->visual_config;
        $vc['revision'] = 3;
        $vc['creative_color'] = '#cb2753';
        $fresh->update(['visual_config' => $vc]);
        $request = Request::create('/audio', 'POST', ['type' => 'voice'], [], ['audio' => UploadedFile::fake()->create('voix.mp3', 10, 'audio/mpeg')]);
        $request->setUserResolver(fn () => $user);
        $result = app(AdvertisingController::class)->uploadCustomAudio($request, $ad);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(3, $ad->fresh()->visual_config['revision']);
        $this->assertSame('#cb2753', $ad->fresh()->visual_config['creative_color']);
        $this->assertNotEmpty($ad->fresh()->visual_config['custom_voice_url']);
    }

    public function test_export_log_uses_reported_duration_and_does_not_invent_social_shares(): void
    {
        [$user, , $ad] = $this->project();
        $this->actingAs($user)->postJson(route('ads.log_video_export', $ad), ['duration_seconds' => 12.5, 'format' => 'mp4', 'script_index' => 0])->assertOk();
        $this->assertSame(0, $ad->fresh()->shares_count);
        $this->assertDatabaseHas('ai_generations', ['shop_id' => $ad->shop_id, 'step' => 'video_rendering', 'duration_ms' => 12500]);
    }

    public function test_ai_generation_is_disabled_without_configuration_and_does_not_charge(): void
    {
        Http::preventStrayRequests();
        config(['services.runway.key' => null]);
        [$user, $shop, $ad] = $this->project();
        $this->actingAs($user)->postJson(route('ads.studio.ai', $ad), ['product_index' => 0, 'prompt' => 'Rotation douce', 'request_id' => (string) Str::uuid()])->assertStatus(503);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
    }

    public function test_ai_submission_is_idempotent_and_a_known_failure_refunds_only_once(): void
    {
        config(['services.runway.key' => 'test-key', 'services.runway.credit_cost' => 5]);
        Http::preventStrayRequests();
        Http::fake(['https://api.dev.runwayml.com/v1/image_to_video' => Http::response(['id' => 'job-test'], 200), 'https://api.dev.runwayml.com/v1/tasks/job-test' => Http::response(['status' => 'FAILED'], 200)]);
        [$user, $shop, $ad] = $this->project();
        $payload = ['product_index' => 0, 'prompt' => 'Rotation douce', 'request_id' => (string) Str::uuid()];
        $result = $this->actingAs($user)->postJson(route('ads.studio.ai', $ad), $payload)->assertOk();
        $this->postJson(route('ads.studio.ai', $ad), $payload)->assertOk()->assertJsonPath('job', $result->json('job'));
        $this->assertSame(5, $shop->fresh()->ai_credits_used);
        $this->getJson($result->json('status_url'))->assertOk()->assertJsonPath('status', 'failed');
        $this->getJson($result->json('status_url'))->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_provider_response_keeps_a_durable_reservation_and_blocks_duplicates(): void
    {
        config(['services.runway.key' => 'test-key', 'services.runway.credit_cost' => 5]);
        Http::preventStrayRequests();
        Http::fake(['https://api.dev.runwayml.com/v1/image_to_video' => Http::response([], 503)]);
        [$user, $shop, $ad] = $this->project();
        $payload = ['product_index' => 0, 'prompt' => 'Rotation douce', 'request_id' => (string) Str::uuid()];
        $this->actingAs($user)->postJson(route('ads.studio.ai', $ad), $payload)->assertStatus(502);
        $payload['request_id'] = (string) Str::uuid();
        $this->postJson(route('ads.studio.ai', $ad), $payload)->assertConflict();
        $this->assertDatabaseHas('ai_generations', ['shop_id' => $shop->id, 'status' => 'unknown', 'step' => 'creative_video_shot']);
        $this->assertSame(5, $shop->fresh()->ai_credits_used);
        Http::assertSentCount(1);
    }

    public function test_long_emoji_prompt_returns_422_before_reserving_credits_or_calling_runway(): void
    {
        config(['services.runway.key' => 'test-key', 'services.runway.credit_cost' => 5]);
        Http::preventStrayRequests();
        [$user, $shop, $ad] = $this->project();

        $this->actingAs($user)->postJson(route('ads.studio.ai', $ad), [
            'product_index' => 0, 'prompt' => str_repeat('✨🎬', 320), 'request_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('message', 'Raccourcissez les instructions du plan vidéo, notamment les émojis.');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertDatabaseMissing('ai_generations', ['shop_id' => $shop->id, 'step' => 'creative_video_shot']);
        Http::assertNothingSent();
    }

    public function test_completed_runway_shot_is_stored_once_and_remains_readable_without_provider_url(): void
    {
        config(['services.runway.key' => 'test-key', 'services.runway.credit_cost' => 5]);
        Storage::fake('public');
        Http::preventStrayRequests();
        $video = base64_decode('AAAAHGZ0eXBpc29tAAACAGlzb21pc28yYXZjMQ==');
        Http::fake([
            'https://api.dev.runwayml.com/v1/image_to_video' => Http::response(['id' => 'job-success']),
            'https://api.dev.runwayml.com/v1/tasks/job-success' => Http::response(['status' => 'SUCCEEDED', 'output' => ['https://media.runwayml.cloud/shot.mp4']]),
            'https://media.runwayml.cloud/shot.mp4' => Http::response($video, 200, ['Content-Type' => 'video/mp4']),
        ]);
        [$user, $shop, $ad] = $this->project();

        $job = $this->actingAs($user)->postJson(route('ads.studio.ai', $ad), [
            'product_index' => 0, 'prompt' => 'Rotation douce', 'request_id' => (string) Str::uuid(),
        ])->assertOk();
        $completed = $this->getJson($job->json('status_url'))->assertOk()->assertJsonPath('status', 'success');
        $this->getJson($job->json('status_url'))->assertOk()->assertJsonPath('asset.id', $completed->json('asset.id'));

        $this->assertCount(1, $ad->fresh()->visual_config['media']);
        $this->assertSame(5, $shop->fresh()->ai_credits_used);
        $this->assertDatabaseHas('public_media', ['path' => $completed->json('asset.path')]);
        Storage::disk('public')->delete($completed->json('asset.path'));
        $this->get($completed->json('asset.url'))->assertOk()->assertContent($video);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.dev.runwayml.com/v1/image_to_video'
            && $request->hasHeader('X-Runway-Version', '2024-11-06')
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'gen4_turbo' && $request['duration'] === 5 && $request['ratio'] === '720:1280');
        Http::assertSentCount(3);
    }
}
