<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiBenchmarkAndDatasetService;
use App\Services\Ai\ToolRegistry;
use App\Services\VideoAdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvertisingCreativeQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_records_that_no_cloud_model_was_trained(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $user = User::factory()->create(['role' => 'merchant']);
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Atelier Démo', 'subdomain' => 'creative-demo', 'currency' => 'XOF']);
        $result = app(AiBenchmarkAndDatasetService::class)->trainAllModels($shop, $user);

        $this->assertSame('not_started', $result['manifest']['training_status']);
        $this->assertFalse($result['manifest']['model_weights_updated']);
        $this->assertTrue($result['manifest']['requires_human_review']);
        $this->assertFalse($result['benchmark']['cloud_models_evaluated']);
        $file = Storage::disk('local')->get('ai/zanika_creative_examples.jsonl');
        $lines = explode("\n", trim($file));
        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $example = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['system', 'user', 'assistant'], array_column($example['messages'], 'role'));
            $this->assertStringNotContainsString('rejected', $example['messages'][2]['content']);
        }
        Http::assertNothingSent();
    }

    public function test_tool_examples_use_current_tools_and_never_embed_approval_tokens(): void
    {
        Storage::fake('local');
        $service = app(AiBenchmarkAndDatasetService::class);
        $report = $service->exportToolCallingDataset();
        $registry = app(ToolRegistry::class);
        $examples = explode("\n", trim(Storage::disk('local')->get($report['relative_path'])));
        foreach ($examples as $line) {
            $example = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $tool = $registry->get($example['expected_tool']);
            $this->assertNotNull($tool);
            $schema = $tool->parametersSchema();
            $this->assertSame([], array_values(array_diff(array_keys($example['arguments']), array_keys((array) ($schema['properties'] ?? [])))));
            $this->assertArrayNotHasKey('confirmed_by_user', $example['arguments']);
            $this->assertArrayNotHasKey('action_token', $example['arguments']);
            $this->assertSame($tool->requiresConfirmation(), $example['requires_confirmation']);
        }
    }

    public function test_artwork_payload_keeps_complete_product_names_without_false_bestseller_badges(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create(['user_id' => $user->id, 'name' => 'Atelier Démo', 'subdomain' => 'creative-demo', 'currency' => 'XOF']);
        $title = 'Sac de voyage Alba collection week-end édition bleu nuit';
        $product = $shop->products()->create(['title' => $title, 'slug' => 'sac-alba', 'price' => 12000, 'stock' => 3, 'status' => 'active']);
        $payload = app(VideoAdGeneratorService::class)->formatProductsPayload(collect([$product]), $shop);

        $this->assertSame($title, $payload[0]['title']);
        $this->assertSame('', $payload[0]['badge']);
        $this->assertNull($payload[0]['old_price']);
    }
}
