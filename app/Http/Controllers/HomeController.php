<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

    public function manifest(Request $request): JsonResponse
    {
        $manifest = File::json(public_path('manifest.json'), JSON_THROW_ON_ERROR);
        $manifest['related_applications'] = [[
            'platform' => 'webapp',
            'url' => route('pwa.manifest', absolute: false),
            'id' => $request->getSchemeAndHttpHost().'/',
        ]];

        return response()->json($manifest, headers: ['Content-Type' => 'application/manifest+json']);
    }
}
