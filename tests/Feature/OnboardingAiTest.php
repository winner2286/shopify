<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingAiTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(): array
    {
        $user = User::factory()->create([
            'email' => 'kofi@waxlome.test',
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        return [$user, $shop];
    }

    public function test_etape_1_description_valide_et_sauvegarde_brouillon_b01(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        // Texte trop court (< 20 caractères) : refusé
        $this->actingAs($merchant)->post('/onboarding/activite', [
            'business_category' => 'mode',
            'description' => 'Court',
        ])->assertSessionHasErrors('description');

        // Description valide
        $response = $this->actingAs($merchant)->post('/onboarding/activite', [
            'business_category' => 'mode',
            'description' => 'Boutique de confection de vêtements en pagne wax et lin de haute qualité à Lomé.',
        ]);

        $response->assertRedirect('/onboarding/style');
        $shop->refresh();
        $this->assertSame('mode', $shop->business_category);
        $this->assertSame(2, $shop->onboarding_step);
    }

    public function test_etape_2_questionnaire_style_visuel_et_nom_b02(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        $response = $this->actingAs($merchant)->post('/onboarding/style', [
            'suggested_name' => 'Kofi Wax Couture',
            'target_audience' => 'Femmes et hommes élégants',
            'visual_style' => 'colore',
            'brand_color' => '#ff007a',
        ]);

        $response->assertRedirect('/onboarding/photos');
        $shop->refresh();
        $this->assertSame('Kofi Wax Couture', $shop->name);
        $this->assertSame('colore', $shop->visual_style);
        $this->assertSame('#ff007a', $shop->brand_color);
        $this->assertSame(3, $shop->onboarding_step);
    }

    public function test_etape_3_envoi_des_photos_de_produits_b03(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        $photo1 = UploadedFile::fake()->image('robe-wax.jpg', 600, 600);
        $photo2 = UploadedFile::fake()->image('chemise-lin.png', 600, 600);

        $response = $this->actingAs($merchant)->post('/onboarding/photos', [
            'photos' => [$photo1, $photo2],
        ]);

        $response->assertRedirect('/onboarding/generation');
        $shop->refresh();
        $this->assertSame(4, $shop->onboarding_step);
        $this->assertCount(2, $shop->onboarding_data['uploaded_photos']);
    }

    public function test_chaine_ia_complete_genere_boutique_logos_produits_pages_et_logs_b04(): void
    {
        Storage::fake('public');
        [$merchant, $shop] = $this->createMerchant();

        // Préparation des données d'onboarding
        $shop->update([
            'name' => 'Élégance Wax',
            'business_category' => 'mode',
            'description' => 'Atelier de création artisanale de vêtements et accessoires africains modernes.',
            'onboarding_data' => [
                'description' => 'Atelier de création artisanale de vêtements et accessoires africains modernes.',
                'business_category' => 'mode',
                'visual_style' => 'moderne',
                'brand_color' => '#6d3df2',
                'suggested_name' => 'Élégance Wax',
            ],
        ]);

        $response = $this->actingAs($merchant)->post('/onboarding/generation');
        $response->assertRedirect('/onboarding/apercu');

        $shop->refresh();

        // 1. Catégories générées (au moins 3)
        $this->assertGreaterThanOrEqual(3, $shop->categories()->count());

        // 2. Fiches produits générées sans prix inventé (règle critique CDC 7.3)
        $products = $shop->products()->get();
        $this->assertNotEmpty($products);
        foreach ($products as $p) {
            $this->assertSame(0, $p->price, 'L\'IA ne doit jamais inventer de prix !');
            $this->assertSame('ai', $p->created_via);
            $this->assertNotNull($p->description);
        }

        // 3. Pages d'information types (À propos, contact, livraison, cgv)
        $this->assertSame(4, $shop->pages()->count());

        // 4. Propositions de logos
        $this->assertCount(3, $shop->onboarding_data['generated_logos']);

        // 5. Journalisation de l'IA (CDC 6.1 et J-04)
        $this->assertDatabaseHas('ai_generations', [
            'shop_id' => $shop->id,
            'type' => 'shop_init',
            'status' => 'success',
        ]);
    }

    public function test_selection_du_logo_prefere_b05(): void
    {
        [$merchant, $shop] = $this->createMerchant();

        $shop->update([
            'onboarding_data' => [
                'generated_logos' => [
                    ['id' => 1, 'name' => 'Logo 1', 'svg' => '<svg>1</svg>'],
                    ['id' => 2, 'name' => 'Logo 2', 'svg' => '<svg>2</svg>'],
                    ['id' => 3, 'name' => 'Logo 3', 'svg' => '<svg>3</svg>'],
                ],
            ],
        ]);

        $response = $this->actingAs($merchant)->post('/onboarding/logo', ['logo_id' => 2]);
        $response->assertRedirect();

        $shop->refresh();
        $this->assertSame(2, $shop->onboarding_data['chosen_logo']['id']);
    }

    public function test_generation_ia_attribue_un_sous_domaine_unique_meme_en_cas_de_doublon(): void
    {
        // 1ère boutique occupant déjà le sous-domaine "maison-elegance"
        [$merchant1, $shop1] = $this->createMerchant();
        $shop1->update([
            'name' => 'Maison Élégance',
            'subdomain' => 'maison-elegance',
        ]);

        // 2ème boutique qui tente d'utiliser le même nom "Maison Élégance"
        $user2 = User::factory()->create([
            'email' => 'merchant2@test.tg',
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
        ]);
        $shop2 = Shop::create([
            'user_id' => $user2->id,
            'name' => 'Maison Élégance',
            'status' => 'draft',
            'onboarding_data' => [
                'description' => 'Deuxième boutique proposant des vêtements et accessoires haut de gamme.',
                'business_category' => 'mode',
                'visual_style' => 'artisanal',
                'brand_color' => '#b45309',
                'suggested_name' => 'Maison Élégance',
            ],
        ]);

        $response = $this->actingAs($user2)->post('/onboarding/generation');
        $response->assertRedirect('/onboarding/apercu');

        $shop2->refresh();
        $this->assertNotNull($shop2->subdomain);
        $this->assertNotSame('maison-elegance', $shop2->subdomain);
        $this->assertSame('maison-elegance-2', $shop2->subdomain);
    }
}
