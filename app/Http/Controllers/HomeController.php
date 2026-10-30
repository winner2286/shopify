<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke()
    {
        // Auto-initialisation et mise à niveau des formules officielles
        if (Schema::hasTable('plans')) {
            $needsUpdate = Plan::where('slug', 'essentiel')
                ->where(fn ($q) => $q->whereNull('monthly_price')->orWhere('monthly_price', '<=', 0))
                ->exists();

            if ($needsUpdate || Plan::count() === 0) {
                try {
                    Artisan::call('migrate', ['--force' => true]);
                    Artisan::call('db:seed', ['--class' => 'PlanSeeder', '--force' => true]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $plans = Schema::hasTable('plans')
            ? Plan::where('active', true)->orderBy('monthly_price')->get()
            : collect();

        // Sécurité absolue : garantir en mémoire les valeurs officielles
        foreach ($plans as $p) {
            if ($p->slug === 'essentiel') {
                $p->monthly_price = $p->monthly_price ?: 10000;
                $p->max_products = $p->max_products ?: 50;
                $p->storage_mb = $p->storage_mb ?: 2048;
                $p->ai_credits = $p->ai_credits ?: 80;
            } elseif ($p->slug === 'pro') {
                $p->monthly_price = $p->monthly_price ?: 15000;
                $p->max_products = $p->max_products ?: 300;
                $p->storage_mb = $p->storage_mb ?: 8192;
                $p->ai_credits = $p->ai_credits ?: 350;
            } elseif ($p->slug === 'premium') {
                $p->monthly_price = $p->monthly_price ?: 30000;
                $p->max_products = $p->max_products ?: 2500;
                $p->storage_mb = $p->storage_mb ?: 25600;
                $p->ai_credits = $p->ai_credits ?: 1200;
            }
        }

        return view('home', ['plans' => $plans]);
    }
}
