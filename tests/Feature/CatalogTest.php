<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(string $email = 'merchant@example.test'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => 'Boutique de ' . $user->name,
            'status' => 'draft',
        ]);

        return [$user, $shop];
    }

    public function test_commercant_peut_creer_une_categorie(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        $response = $this->actingAs($merchant)->post('/categories', [
            'name' => 'Vêtements Homme',
            'display_order' => 1,
            'is_visible' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'shop_id' => $shop->id,
            'name' => 'Vêtements Homme',
        ]);
    }

    public function test_commercant_peut_creer_un_produit_avec_prix_en_fcfa_et_stock(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        $category = Category::create([
            'shop_id' => $shop->id,
            'name' => 'Mode',
            'slug' => 'mode',
        ]);

        $photo = UploadedFile::fake()->image('chemise.jpg', 600, 600);

        $response = $this->actingAs($merchant)->post('/produits', [
            'title' => 'Chemise Slim Fit',
            'description' => '100% Coton peigné',
            'price' => 12500, // FCFA
            'compare_at_price' => 15000,
            'stock' => 10,
            'track_stock' => '1',
            'low_stock_threshold' => 3,
            'status' => 'active',
            'sku' => 'CH-SLIM-01',
            'category_ids' => [$category->id],
            'photos' => [$photo],
        ]);

        $response->assertRedirect('/produits');

        $product = Product::where('title', 'Chemise Slim Fit')->first();
        $this->assertNotNull($product);
        $this->assertSame(12500, $product->price);
        $this->assertSame(10, $product->stock);
        $this->assertSame(1, $product->categories()->count());
        $this->assertSame(1, $product->images()->count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 10,
            'reason' => 'initial',
        ]);
    }

    public function test_isolation_multi_boutiques_rg02(): void
    {
        // Commerçant A et sa boutique
        [$merchantA, $shopA] = $this->createMerchant('a@example.test');
        $prodA = Product::create([
            'shop_id' => $shopA->id,
            'title' => 'Produit Exclusif A',
            'slug' => 'produit-a',
            'price' => 5000,
            'stock' => 5,
        ]);

        // Commerçant B et sa boutique
        [$merchantB, $shopB] = $this->createMerchant('b@example.test');

        // Le commerçant B consulte la liste : il ne doit PAS voir le produit de A
        $response = $this->actingAs($merchantB)->get('/produits');
        $response->assertOk();
        $response->assertDontSee('Produit Exclusif A');

        // Le commerçant B tente d'éditer ou modifier le produit de A : accès refusé ou 404
        $editResponse = $this->actingAs($merchantB)->get('/produits/' . $prodA->id . '/edit');
        $editResponse->assertNotFound();

        $updateResponse = $this->actingAs($merchantB)->put('/produits/' . $prodA->id, [
            'title' => 'Piratage par B',
            'price' => 1000,
            'status' => 'active',
        ]);
        $updateResponse->assertNotFound();

        // Le produit de A reste intact
        $this->assertSame('Produit Exclusif A', $prodA->fresh()->title);
    }

    public function test_ajustement_manuel_de_stock_avec_tracabilite_c04(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        $product = Product::create([
            'shop_id' => $shop->id,
            'title' => 'Montre Connectée',
            'slug' => 'montre',
            'price' => 25000,
            'stock' => 4,
            'track_stock' => true,
        ]);

        $this->actingAs($merchant)->put('/produits/' . $product->id, [
            'title' => 'Montre Connectée',
            'price' => 25000,
            'status' => 'active',
            'track_stock' => '1',
            'stock_adjustment' => '+6',
            'adjustment_reason' => 'Arrivage fournisseur',
        ]);

        $product->refresh();
        $this->assertSame(10, $product->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 6,
            'new_stock' => 10,
            'comment' => 'Arrivage fournisseur',
        ]);
    }

    public function test_detection_stock_bas_et_epuise(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        $prodLow = Product::create([
            'shop_id' => $shop->id,
            'title' => 'Casque Audio',
            'slug' => 'casque',
            'price' => 15000,
            'stock' => 2,
            'track_stock' => true,
            'low_stock_threshold' => 3,
        ]);

        $prodOut = Product::create([
            'shop_id' => $shop->id,
            'title' => 'Câble USB',
            'slug' => 'cable',
            'price' => 2000,
            'stock' => 0,
            'track_stock' => true,
            'low_stock_threshold' => 3,
        ]);

        $this->assertTrue($prodLow->isLowStock());
        $this->assertFalse($prodLow->isOutOfStock());

        $this->assertTrue($prodOut->isOutOfStock());
        $this->assertFalse($prodOut->isLowStock());
    }
}
