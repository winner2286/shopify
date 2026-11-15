<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        if (Schema::hasColumn('plans', 'photoroom_credits')) {
            DB::table('plans')->where('slug', 'essentiel')->update(['photoroom_credits' => 25]);
            DB::table('plans')->where('slug', 'pro')->update(['photoroom_credits' => 60]);
            DB::table('plans')->where('slug', 'premium')->update(['photoroom_credits' => 180]);
        }

        if (Schema::hasColumn('plans', 'features')) {
            DB::table('plans')->where('slug', 'essentiel')->update([
                'features' => json_encode([
                    '1 boutique e-commerce incluse',
                    'Détourage automatique & Packshot studio blanc (Photoroom HD)',
                    'Publicité IA : textes & accroches simples',
                    'Tableau de bord de vente simple',
                    'Support standard par email',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            DB::table('plans')->where('slug', 'pro')->update([
                'features' => json_encode([
                    'Jusqu\'à 3 boutiques distinctes gérées',
                    'Studio Photo IA Pro : décors générés, ombres réalistes & PNG HD',
                    'Publicité IA : textes, visuels & scripts TikTok',
                    'Codes promo & soldes programmées',
                    'Alertes rupture de stock (WhatsApp/SMS)',
                    'Statistiques complètes & top ventes',
                    'Support prioritaire par WhatsApp',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            DB::table('plans')->where('slug', 'premium')->update([
                'features' => json_encode([
                    'Boutiques illimitées sans restriction',
                    'Suite Studio Photoroom Max : showrooms luxe, lifestyle & podiums IA',
                    'Retouche catalogue haute capacité & détourage pub en 1 clic',
                    'Campagnes IA sponsorisées complètes',
                    'Codes promo illimités & ventes privées',
                    'Gestion multi-collaborateurs (jusqu\'à 3 accès)',
                    'Export comptable des commandes (Excel / PDF)',
                    'Connexion de nom de domaine personnalisé',
                    'Chargé de compte dédié & Support VIP 7j/7',
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'photoroom_credits')) {
            DB::table('plans')->where('slug', 'essentiel')->update(['photoroom_credits' => 15]);
            DB::table('plans')->where('slug', 'pro')->update(['photoroom_credits' => 50]);
            DB::table('plans')->where('slug', 'premium')->update(['photoroom_credits' => 150]);
        }
    }
};
