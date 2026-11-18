<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\RembgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductPhotoEnginesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.product_studio.scene_provider' => 'openai',
            'services.openai_images.key' => 'test-openai-secret',
            'services.openai_images.daily_limit' => 50,
            'services.rembg.enabled' => true,
            'services.rembg.python' => PHP_BINARY,
            'services.rembg.model_path' => __FILE__,
            'services.photoroom.key' => 'must-not-be-used',
        ]);
    }

    private function merchant(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MERCHANT, 'email_verified_at' => now()]);
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Studio test', 'status' => 'draft']);
        $shop->subscriptions()->create(['status' => 'active', 'plan_id' => Plan::where('slug', 'essentiel')->firstOrFail()->id]);

        return [$user, $shop];
    }

    private function image(int $size = 300, bool $mask = false, bool $complex = false): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 30, 90, 170, $mask ? 127 : 0));
        if ($complex) {
            imagefilledrectangle($image, 0, 0, $size / 2, $size, imagecolorallocate($image, 200, 100, 20));
        }
        imagefilledrectangle($image, $size * .3, $size * .3, $size * .7, $size * .7, imagecolorallocate($image, $mask ? 0 : 180, $mask ? 255 : 20, 30));
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    private function postPreview(User $merchant, array $fields = []): TestResponse
    {
        return $this->actingAs($merchant)->postJson(route('ai.products.verify_quality'), $fields + [
            'photo' => UploadedFile::fake()->createWithContent('article.png', $this->image(complex: true)),
            'style' => 'lifestyle', 'scene_prompt' => 'Chambre lumineuse avec parquet', 'category_hint' => 'Baskets bordeaux',
        ]);
    }

    public function test_generated_scene_uses_rembg_and_one_openai_call_preserving_original_product_pixels(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();
        $source = $this->image(complex: true);
        Http::fake(['https://api.openai.com/v1/images/edits' => Http::response([
            'data' => [['b64_json' => base64_encode($this->image(1024))]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 2000],
        ])]);

        $response = $this->postPreview($merchant)->assertOk()->assertJsonPath('engine', 'openai-image')->assertJsonPath('cutout_method', 'rembg_u2netp');
        $this->assertSame($source, Storage::disk('public')->get($response->json('source_path')));
        $cutout = imagecreatefromstring(Storage::disk('public')->get($response->json('cutout_path')));
        $this->assertSame(127, (imagecolorat($cutout, 0, 0) >> 24) & 127);
        $this->assertSame(0xB4141E, imagecolorat($cutout, 150, 150) & 0xFFFFFF, 'Only the inferred alpha may be applied to the product.');
        imagedestroy($cutout);
        $result = imagecreatefromstring(Storage::disk('public')->get($response->json('variations.0.path')));
        $center = imagecolorsforindex($result, imagecolorat($result, 500, 600));
        $this->assertGreaterThan(160, $center['red']);
        $this->assertLessThan(40, $center['green']);
        imagedestroy($result);
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(1, $shop->studioScenesUsedThisMonth());
        $response->assertJsonPath('studio_scenes_remaining', 2);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        $this->assertDatabaseHas('ai_generations', ['shop_id' => $shop->id, 'model_used' => 'gpt-image-2.5-flare', 'prompt_tokens' => 100, 'completion_tokens' => 2000, 'cost_estimated_fcfa' => 40]);
        $this->assertSame(2000, AiGeneration::first()->output_data['usage']['output_tokens']);
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name');

            return $request->url() === 'https://api.openai.com/v1/images/edits'
                && str_contains($fields['prompt'], 'Chambre lumineuse avec parquet')
                && str_contains($fields['prompt'], 'Baskets bordeaux')
                && $fields['n'] === '1' && $fields['quality'] === 'medium'
                && $fields['size'] === '1024x1024';
        });
        Http::assertSentCount(1);
        Process::assertRan(fn ($process) => $process->input === $source && $process->timeout === 45 && $process->command === [PHP_BINARY, base_path('docker/rembg/cutout.py'), __FILE__]);
    }

    public function test_hanging_scene_uses_the_cropped_manual_mask_and_scene_layout_without_running_rembg(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake();
        [$merchant, $shop] = $this->merchant();
        Http::fake(['https://api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($this->image(1024))]]])]);

        $response = $this->postPreview($merchant, [
            'scene_prompt' => '',
            'category_hint' => 'Pantalon',
            'presentation' => 'hanging',
            'crop_top' => 25,
            'crop_right' => 20,
            'subject_mask' => UploadedFile::fake()->createWithContent('mask.png', $this->image(mask: true)),
        ])->assertOk()
            ->assertJsonPath('cutout_method', 'manual_mask')
            ->assertJsonPath('crop_frame', ['x' => 0, 'y' => 75, 'width' => 240, 'height' => 225, 'source_width' => 300, 'source_height' => 300]);
        $this->assertSame($this->image(complex: true), Storage::disk('public')->get($response->json('source_path')));
        $cutout = imagecreatefromstring(Storage::disk('public')->get($response->json('cutout_path')));
        $this->assertSame(240, imagesx($cutout));
        $this->assertSame(225, imagesy($cutout));
        $this->assertSame(0xB4141E, imagecolorat($cutout, 150, 75) & 0xFFFFFF);
        $this->assertSame(127, (imagecolorat($cutout, 150, 5) >> 24) & 127);
        imagedestroy($cutout);
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name');
            $layout = imagecreatefromstring($fields['image']);
            $background = imagecolorat($layout, 500, 260) & 0xFFFFFF;
            $product = imagecolorat($layout, 500, 490) & 0xFFFFFF;
            imagedestroy($layout);

            return str_contains($fields['prompt'], 'rail') && str_contains($fields['prompt'], 'Pantalon')
                && str_contains($fields['prompt'], 'Invent a distinctive')
                && $background === 0xD6D5D3 && $product !== $background;
        });

        Process::assertDidntRun(fn () => true);
        Http::assertSentCount(1);
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
    }

    public function test_untitled_product_gets_a_scene_directed_from_the_photo(): void
    {
        Storage::fake('public');
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant] = $this->merchant();
        Http::fake(['https://api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($this->image(1024))]]])]);

        $this->postPreview($merchant, ['scene_prompt' => '', 'category_hint' => ''])->assertOk();

        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->pluck('contents', 'name');

            return str_contains($fields['prompt'], 'Infer the original camera angle and physical pose from the pictured article.');
        });
        Http::assertSentCount(1);
    }

    #[TestWith([401, null, 'vérifier l’accès'])]
    #[TestWith([402, null, 'motif de facturation'])]
    #[TestWith([429, null, 'a limité la génération'])]
    #[TestWith([429, 'credit_balance_exhausted', 'solde de crédits'])]
    #[TestWith([429, 'project_spend_limit_exceeded', 'plafond de dépenses'])]
    #[TestWith([429, 'organization_usage_limit_exceeded', 'quota de l’organisation'])]
    #[TestWith([429, 'slow_down', 'Réessayez dans au moins 42 s.', 42])]
    #[TestWith([503, 'server_is_overloaded', 'surchargé', 42])]
    public function test_openai_rejection_refunds_credits_and_offers_an_explicit_studio_alternative(int $status, ?string $code, string $message, ?int $retryAfter = null): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();
        Log::spy();
        Http::fake(['https://api.openai.com/v1/images/edits' => Http::response([
            'error' => ['code' => $code, 'message' => 'PRIVATE_KEY_AND_PROVIDER_DETAILS'],
        ], $status, ['Retry-After' => '42', 'x-request-id' => 'req_0123456789abcdef0123456789abcdef'])]);

        $response = $this->postPreview($merchant)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo')
            ->assertJsonPath('preview_alternative', 'scene_template')
            ->assertJsonPath('retry_after', $retryAfter)
            ->assertDontSee('PRIVATE_KEY_AND_PROVIDER_DETAILS')
            ->assertDontSee('test-openai-secret');
        $this->assertStringContainsString($message, $response->json('errors.photo.0'));
        Log::shouldHaveReceived('warning')->once()->with('OpenAI product background HTTP error', \Mockery::on(function (array $context) use ($code, $status, $retryAfter, $response): bool {
            return $context['status'] === $status && $context['provider_code'] === ($code ?? 'unknown')
                && $context['provider_request_id'] === 'req_0123456789abcdef0123456789abcdef'
                && $context['retry_after'] === $retryAfter && str_contains($response->json('errors.photo.0'), $context['reference']);
        }));

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(0, $shop->studioScenesUsedThisMonth());
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSentCount(1);
        Process::assertRan(fn () => true);
    }

    public function test_failed_cutout_does_not_start_a_paid_scene_generation(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake([Process::result(errorOutput: 'RuntimeError', exitCode: 1)]);
        [$merchant, $shop] = $this->merchant();

        $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
        Process::assertRan(fn () => true);
    }

    public function test_prepared_background_uses_rembg_without_openai_or_photoroom(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();

        $this->postPreview($merchant, ['style' => 'scene_template', 'scene_template' => 'podium'])->assertOk()->assertJsonPath('cutout_method', 'rembg_u2netp');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(0, $shop->studioScenesUsedThisMonth());
        $this->assertSame(0, $shop->fresh()->photoroom_credits_used);
        Http::assertNothingSent();
        Process::assertRan(fn () => true);
    }

    public function test_missing_key_is_rejected_before_running_rembg_or_spending_credits(): void
    {
        config(['services.openai_images.key' => '']);
        Http::preventStrayRequests();
        Process::fake();
        [$merchant, $shop] = $this->merchant();

        $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('style');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
        Process::assertDidntRun(fn () => true);
    }

    public function test_exhausted_scene_quota_stops_external_processing_but_local_retouch_remains_included(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake();
        [$merchant, $shop] = $this->merchant();
        for ($scene = 0; $scene < 3; $scene++) {
            $shop->withStudioScene(fn (): bool => true);
        }
        $shop->consumeAiCredits(80);

        $this->postPreview($merchant)->assertPaymentRequired()->assertJsonPath('preview_alternative', 'scene_template');
        $this->postPreview($merchant, ['style' => 'retouch'])->assertOk()->assertJsonPath('studio_scenes_remaining', 0);
        $this->assertSame(80, $shop->aiCreditsUsedThisMonth());
        Http::assertNothingSent();
        Process::assertDidntRun(fn () => true);
    }

    public function test_daily_limit_blocks_paid_requests_and_refunds_platform_credits(): void
    {
        config(['services.openai_images.daily_limit' => 0]);
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();

        $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
        Process::assertRan(fn () => true);
    }

    public function test_invalid_provider_image_is_rejected_without_saving_output(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();
        Http::fake(['https://api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('invalid image')]]])]);

        $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertSentCount(1);
        Process::assertRan(fn () => true);
    }

    public function test_openai_timeout_is_not_retried_and_refunds_platform_credits(): void
    {
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();
        $attempts = 0;
        Http::fake(['https://api.openai.com/v1/images/edits' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException;
        }]);

        $response = $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertStringContainsString('Un appel déjà traité peut rester facturé par OpenAI.', $response->json('errors.photo.0'));

        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        $this->assertSame(1, $attempts);
        Process::assertRan(fn () => true);
    }

    public function test_concurrent_generation_is_rejected_without_an_openai_request(): void
    {
        Http::preventStrayRequests();
        Process::fake([Process::result(output: $this->image(mask: true))]);
        [$merchant, $shop] = $this->merchant();
        $lock = Cache::lock('product-scene-openai', 150);
        $lock->get();
        try {
            $this->postPreview($merchant)->assertUnprocessable()->assertJsonValidationErrors('photo');
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $shop->fresh()->ai_credits_used);
        Http::assertNothingSent();
        Process::assertRan(fn () => true);
    }

    public function test_rembg_lock_prevents_two_cpu_processes_at_once(): void
    {
        Process::fake();
        $lock = fopen(storage_path('framework/cache/rembg.lock'), 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        try {
            app(RembgService::class)->removeBackground($this->image());
            $this->fail('The second cutout must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('photo', $exception->errors());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        Process::assertDidntRun(fn () => true);
    }

    public function test_product_form_exposes_openai_scene_when_configured_without_a_photoroom_plan(): void
    {
        Http::preventStrayRequests();
        Process::fake();
        [$merchant] = $this->merchant();

        $response = $this->actingAs($merchant)->get(route('products.create'))->assertOk()->assertSee('data-scene-provider="openai"', false)->assertSee('data-scene-available="true"', false)->assertSee('value="lifestyle" checked', false)->assertSee('Laissez vide pour proposer un décor selon votre article.');
        if (getenv('CREATIVE_STUDIO_REVIEW') === '1') {
            file_put_contents(storage_path('app/private/studio-review-openai-create.html'), str_replace(
                ['http://localhost:8080', 'http://localhost'], ['http://127.0.0.1:8127', 'http://127.0.0.1:8127'], $response->getContent()
            ));
        }
        Http::assertNothingSent();
        Process::assertDidntRun(fn () => true);
    }
}
