<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke()
    {
        // Si l'utilisateur est déjà connecté, le rediriger directement vers son espace
        if (auth()->check() && ! request()->has('landing')) {
            if (auth()->user()->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('dashboard');
        }

        $plans = Schema::hasTable('plans')
            ? Plan::where('active', true)->orderBy('monthly_price')->get()
            : collect();

        return view('home', ['plans' => $plans]);
    }
}
