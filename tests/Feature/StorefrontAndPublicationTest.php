<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAndPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function createMerchant(string $email = 'merchant@test.com'): User
    {
        return User::create([
            'name' => 'Marchand Test',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => User::ROLE_MERCHANT,
            'country' => 'TG',
            'phone' => '+228 90 12 34 56',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);
    }

    private function createShop(User $user, string $subdomain = 'elegance-lome', string $status = Shop::DRAFT): Shop
    {
        return Shop::create([
            'user_id' => $user->id,
            'name' => 'Élégance Lomé',
            'subdomain' => $subdomain,
            'status' => $status,
            'business_category' => 'mode',
            'brand_color' => '#6d3df2',
            'contact' => [
                'phone' => '+228 90 12 34 56',
                'whatsapp' => '+228 90 12 34 56',
                'email' => 'contact@elegance.tg',
                'city' => 'Lomé',
            ],
        ]);
    }

    public function test_shop_cannot_be_published_without_active_product_with_price(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        // Produit avec prix à 0 (non fixé par le commerçant)
        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Robe Soirée',
            'slug' => 'robe-soiree',
            'price' => 0,
            'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->post(route('shop.publish'));

        $response->assertSessionHas('error');
        $this->assertEquals(Shop::DRAFT, $shop->fresh()->status);
    }

    public function test_shop_can_be_published_when_checklist_is_complete(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user);

        // Produit valide avec prix > 0
        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Robe Soirée',
            'slug' => 'robe-soiree',
            'price' => 15000,
            'stock' => 5,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->post(route('shop.publish'));

        $response->assertRedirect(route('shop.settings'));
        $response->assertSessionHas('published_success', true);
        $this->assertEquals(Shop::PUBLISHED, $shop->fresh()->status);
    }

    public function test_merchant_can_toggle_pause_on_published_shop(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user, 'mode-tg', Shop::PUBLISHED);

        Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Chemise Slim',
            'slug' => 'chemise-slim',
            'price' => 8500,
            'stock' => 10,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // Mettre en pause
        $response = $this->actingAs($user)->post(route('shop.toggle_pause'));
        $response->assertRedirect(route('shop.settings'));
        $this->assertEquals(Shop::PAUSED, $shop->fresh()->status);

        // Réactiver
        $response = $this->actingAs($user)->post(route('shop.toggle_pause'));
        $response->assertRedirect(route('shop.settings'));
        $this->assertEquals(Shop::PUBLISHED, $shop->fresh()->status);
    }

    public function test_public_can_view_storefront_when_published(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user, 'ma-boutique', Shop::PUBLISHED);

        $category = Category::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'name' => 'Chaussures',
            'slug' => 'chaussures',
            'is_visible' => true,
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Baskets Running',
            'slug' => 'baskets-running',
            'price' => 22000,
            'compare_at_price' => 25000,
            'stock' => 8,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $product->categories()->attach($category->id);

        ShopPage::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'type' => 'about',
            'title' => 'À propos de notre boutique',
            'content' => 'Nous sommes spécialisés dans les articles de sport à Lomé.',
            'is_published' => true,
        ]);

        // Accueil vitrine
        $resHome = $this->get('/b/' . $shop->subdomain);
        $resHome->assertStatus(200);
        $resHome->assertSee('Élégance Lomé');
        $resHome->assertSee('Baskets Running');
        $resHome->assertSee('22 000 FCFA');

        // Catalogue
        $resCat = $this->get('/b/' . $shop->subdomain . '/produits');
        $resCat->assertStatus(200);
        $resCat->assertSee('Baskets Running');

        // Fiche produit
        $resProd = $this->get('/b/' . $shop->subdomain . '/produits/baskets-running');
        $resProd->assertStatus(200);
        $resProd->assertSee('Baskets Running');
        $resProd->assertSee('22 000 FCFA');
        $resProd->assertSee('Ajouter au panier');
        $resProd->assertSee('Commander directement sur WhatsApp');

        // Page À propos
        $resPage = $this->get('/b/' . $shop->subdomain . '/p/about');
        $resPage->assertStatus(200);
        $resPage->assertSee('À propos de notre boutique');
    }

    public function test_storefront_shows_maintenance_to_public_when_paused(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user, 'mode-pause', Shop::PAUSED);

        // Visiteur anonyme -> page 503 de maintenance
        $response = $this->get('/b/' . $shop->subdomain);
        $response->assertStatus(503);
        $response->assertSee('Notre boutique prend une courte pause !');

        // Propriétaire connecté -> aperçu autorisé
        $resOwner = $this->actingAs($user)->get('/b/' . $shop->subdomain);
        $resOwner->assertStatus(200);
        $resOwner->assertSee('Mode Prévisualisation');
    }

    public function test_cart_operations_and_stock_validation(): void
    {
        $user = $this->createMerchant();
        $shop = $this->createShop($user, 'boutique-panier', Shop::PUBLISHED);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => 'Montre Connectée',
            'slug' => 'montre-connectee',
            'price' => 18000,
            'stock' => 3,
            'track_stock' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        // 1. Ajouter 2 articles au panier
        $resAdd = $this->post('/b/' . $shop->subdomain . '/panier/ajouter', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $resAdd->assertRedirect();

        // 2. Vérifier page panier
        $resCart = $this->get('/b/' . $shop->subdomain . '/panier');
        $resCart->assertStatus(200);
        $resCart->assertSee('Montre Connectée');
        $resCart->assertSee('36 000 FCFA'); // 18000 * 2

        // 3. Tenter d'ajouter au-delà du stock (total souhaité = 2 + 2 = 4 > stock 3)
        $resExceed = $this->post('/b/' . $shop->subdomain . '/panier/ajouter', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $resExceed->assertSessionHas('error');

        // 4. Mettre à jour quantité
        $resUpdate = $this->post('/b/' . $shop->subdomain . '/panier/maj', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $resUpdate->assertRedirect();

        // 5. Supprimer du panier
        $resRemove = $this->post('/b/' . $shop->subdomain . '/panier/supprimer/' . $product->id);
        $resRemove->assertRedirect();

        $resCartEmpty = $this->get('/b/' . $shop->subdomain . '/panier');
        $resCartEmpty->assertSee('Votre panier est actuellement vide');
    }

    public function test_merchant_can_edit_shop_pages_with_isolation(): void
    {
        $user1 = $this->createMerchant('m1@test.com');
        $shop1 = $this->createShop($user1, 'shop-un');

        $user2 = $this->createMerchant('m2@test.com');
        $shop2 = $this->createShop($user2, 'shop-deux');

        $page1 = ShopPage::withoutGlobalScopes()->create([
            'shop_id' => $shop1->id,
            'type' => 'about',
            'title' => 'Boutique 1 Histoire',
            'content' => 'Texte original boutique 1',
            'is_published' => true,
        ]);

        // Marchand 1 met à jour sa page
        $resEdit = $this->actingAs($user1)->put(route('shop.pages.update', $page1), [
            'title' => 'Notre Nouvelle Histoire',
            'content' => 'Nouveau texte boutique 1',
            'is_published' => true,
        ]);
        $resEdit->assertRedirect(route('shop.pages.index'));
        $this->assertEquals('Notre Nouvelle Histoire', $page1->fresh()->title);

        // Marchand 2 tente d'éditer la page du Marchand 1 (interdit RG-02 - scope global masque la ressource)
        $resUnauthorized = $this->actingAs($user2)->put(route('shop.pages.update', $page1), [
            'title' => 'Piratage',
            'content' => 'Texte piraté',
        ]);
        $resUnauthorized->assertNotFound();
    }
}
