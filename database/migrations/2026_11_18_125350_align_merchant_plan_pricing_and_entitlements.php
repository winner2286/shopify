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
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('studio_scene_credits')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('monthly_price')->nullable();
        });

        foreach (DB::table('plans')->whereNotNull('monthly_price')->get(['id', 'monthly_price']) as $plan) {
            DB::table('subscriptions')->where('plan_id', $plan->id)
                ->where(function ($subscriptions) {
                    $subscriptions->whereIn('status', ['active', 'grace', 'pending'])
                        ->orWhere(function ($oldSubscriptions) {
                            $oldSubscriptions->whereIn('status', ['suspended', 'cancelled'])->whereNull('trial_ends_at');
                        });
                })
                ->update(['monthly_price' => $plan->monthly_price]);
        }
        DB::table('plans')->where('slug', 'essentiel')->where('monthly_price', 10000)->update(['monthly_price' => 7500]);
        DB::table('plans')->where('slug', 'premium')->where('max_shops', 999)->update(['max_shops' => 5]);
        foreach (['essentiel' => 3, 'pro' => 20, 'premium' => 60] as $slug => $credits) {
            DB::table('plans')->where('slug', $slug)->update(['studio_scene_credits' => $credits]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->where('slug', 'essentiel')->where('monthly_price', 7500)->update(['monthly_price' => 10000]);
        DB::table('plans')->where('slug', 'premium')->where('max_shops', 5)->update(['max_shops' => 999]);
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('monthly_price');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('studio_scene_credits');
        });
    }
};
