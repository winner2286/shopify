<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'photoroom_credits')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedInteger('photoroom_credits')->default(15)->after('ai_credits');
            });

            DB::table('plans')->where('slug', 'essentiel')->update(['photoroom_credits' => 15]);
            DB::table('plans')->where('slug', 'pro')->update(['photoroom_credits' => 50]);
            DB::table('plans')->where('slug', 'premium')->update(['photoroom_credits' => 150]);
        }

        if (! Schema::hasColumn('shops', 'photoroom_credits_used')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->integer('photoroom_credits_used')->default(0)->after('ai_credits_used');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'photoroom_credits')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('photoroom_credits');
            });
        }

        if (Schema::hasColumn('shops', 'photoroom_credits_used')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('photoroom_credits_used');
            });
        }
    }
};

