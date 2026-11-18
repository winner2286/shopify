<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Plan::STANDARD_PLANS as $slug => $attributes) {
            Plan::firstOrCreate(['slug' => $slug], $attributes + ['active' => true]);
        }
    }
}
