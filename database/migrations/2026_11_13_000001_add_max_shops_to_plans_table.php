<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajout de max_shops sur la table plans
        if (! Schema::hasColumn('plans', 'max_shops')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_shops')->default(1)->after('max_products');
            });

            // Forfaits officiels : Essentiel (1), Pro (3), Premium (999 = Illimité)
            DB::table('plans')->where('slug', 'essentiel')->update(['max_shops' => 1]);
            DB::table('plans')->where('slug', 'pro')->update(['max_shops' => 3]);
            DB::table('plans')->where('slug', 'premium')->update(['max_shops' => 999]);
        }

        // 2. Assouplissement de la contrainte unique user_id sur shops
        // pour autoriser les utilisateurs multi-boutiques (plans Pro et Premium)
        try {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropUnique('shops_user_id_unique');
            });
        } catch (\Throwable $e) {
            try {
                Schema::table('shops', function (Blueprint $table) {
                    $table->dropUnique(['user_id']);
                });
            } catch (\Throwable $e2) {
                // Ignore si l'index unique n'existe pas ou sur SQLite in-memory
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'max_shops')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('max_shops');
            });
        }

        try {
            Schema::table('shops', function (Blueprint $table) {
                $table->unique('user_id');
            });
        } catch (\Throwable $e) {}
    }
};
