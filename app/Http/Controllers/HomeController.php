<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke()
    {
        // Auto-initialisation si les tables ne sont pas encore créées (déploiement frais)
        if (! Schema::hasTable('plans')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('db:seed', ['--class' => 'PlanSeeder', '--force' => true]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $plans = Schema::hasTable('plans')
            ? Plan::where('active', true)->orderBy('max_products')->get()
            : collect();

        return view('home', ['plans' => $plans]);
    }
}
