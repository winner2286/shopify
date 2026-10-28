<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        // Compte administrateur de développement
        User::updateOrCreate(['email' => 'admin@example.test'], [
            'name' => 'Administrateur',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        // Compte commerçant de test
        $merchant = User::updateOrCreate(['email' => 'commercant@test.com'], [
            'name' => 'Ablam Testeur',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_MERCHANT,
            'email_verified_at' => now(),
            'country' => 'TG',
            'terms_accepted_at' => now(),
        ]);

        $shop = \App\Models\Shop::updateOrCreate(['user_id' => $merchant->id], [
            'name' => 'Boutique Lomé Chic',
            'status' => 'draft',
        ]);

        // Catégories et produits de test
        $catMode = \App\Models\Category::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->id, 'slug' => 'mode-homme'],
            ['name' => 'Mode Homme', 'display_order' => 1, 'is_visible' => true]
        );

        $catAcc = \App\Models\Category::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->id, 'slug' => 'accessoires'],
            ['name' => 'Accessoires', 'display_order' => 2, 'is_visible' => true]
        );

        $prod1 = \App\Models\Product::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->id, 'slug' => 'chemise-en-lin-blanc'],
            [
                'title' => 'Chemise en lin blanc',
                'description' => 'Chemise légère et élégante, confectionnée en lin de qualité.',
                'price' => 15000,
                'compare_at_price' => 18000,
                'stock' => 12,
                'track_stock' => true,
                'low_stock_threshold' => 3,
                'status' => 'active',
                'sku' => 'CH-LIN-01',
            ]
        );
        $prod1->categories()->sync([$catMode->id]);

        $prod2 = \App\Models\Product::withoutGlobalScopes()->updateOrCreate(
            ['shop_id' => $shop->id, 'slug' => 'montre-elegance-cuir'],
            [
                'title' => 'Montre Élégance Cuir',
                'description' => 'Montre à quartz avec bracelet en cuir véritable marron.',
                'price' => 25000,
                'stock' => 2, // Stock bas !
                'track_stock' => true,
                'low_stock_threshold' => 3,
                'status' => 'active',
                'sku' => 'MTR-CUIR-02',
            ]
        );
        $prod2->categories()->sync([$catAcc->id]);
    }
}
