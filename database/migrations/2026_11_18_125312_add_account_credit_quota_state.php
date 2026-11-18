<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('credit_quota_state')->nullable();
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->unsignedInteger('studio_scenes_used')->default(0);
        });
        DB::table('users')->whereIn('id', DB::table('shops')->select('user_id'))->update([
            'credit_quota_state' => json_encode(['month' => now()->format('Y-m')], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('studio_scenes_used');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('credit_quota_state');
        });
    }
};
