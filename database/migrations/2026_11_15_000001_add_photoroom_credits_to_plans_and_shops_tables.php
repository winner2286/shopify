<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'photoroom_credits')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->unsignedInteger('photoroom_credits')->default(25)->after('ai_credits');
            });

            DB::table('plans')->where('slug', 'essentiel')->update(['photoroom_credits' => 25]);
            DB::table('plans')->where('slug', 'pro')->update(['photoroom_credits' => 60]);
            DB::table('plans')->where('slug', 'premium')->update(['photoroom_credits' => 180]);
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

