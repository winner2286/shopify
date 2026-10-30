<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopPageController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reinitialisation/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reinitialisation', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/verification', [VerificationController::class, 'show'])->name('verification.show');
    Route::post('/verification', [VerificationController::class, 'verify'])->middleware('throttle:15,1')->name('verification.verify');
    Route::post('/verification/renvoyer', [VerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.resend');
});

Route::middleware('auth')->group(function () {
    Route::get('/tableau-de-bord', DashboardController::class)->name('dashboard');
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/deconnexion-totale', [LoginController::class, 'destroyAll'])->name('logout.all');

    // Module B - Création de boutique par l'IA (Onboarding)
    Route::get('/onboarding/activite', [OnboardingController::class, 'step1'])->name('onboarding.step1');
    Route::post('/onboarding/activite', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1.store');

    Route::get('/onboarding/style', [OnboardingController::class, 'step2'])->name('onboarding.step2');
    Route::post('/onboarding/style', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2.store');

    Route::get('/onboarding/photos', [OnboardingController::class, 'step3'])->name('onboarding.step3');
    Route::post('/onboarding/photos', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3.store');

    Route::get('/onboarding/generation', [OnboardingController::class, 'step4'])->name('onboarding.step4');
    Route::post('/onboarding/generation', [OnboardingController::class, 'runGeneration'])->name('onboarding.generate');

    Route::get('/onboarding/apercu', [OnboardingController::class, 'preview'])->name('onboarding.preview');
    Route::post('/onboarding/logo', [OnboardingController::class, 'selectLogo'])->name('onboarding.select_logo');

    // Module C - Catalogue & Stock
    Route::resource('produits', ProductController::class)
        ->parameters(['produits' => 'product'])
        ->names('products');
    Route::delete('produits/{product}/images/{image}', [ProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit'])->names('categories');

    // Module B - Paramètres boutique, publication & pages (B-07, B-08, B-09, RG-09, RG-13)
    Route::get('/boutique/parametres', [ShopSettingsController::class, 'edit'])->name('shop.settings');
    Route::post('/boutique/parametres', [ShopSettingsController::class, 'update'])->name('shop.settings.update');
    Route::post('/boutique/publier', [ShopSettingsController::class, 'publish'])->name('shop.publish');
    Route::post('/boutique/pause', [ShopSettingsController::class, 'togglePause'])->name('shop.toggle_pause');
    Route::delete('/boutique/supprimer', [ShopSettingsController::class, 'destroy'])->name('shop.destroy');
    Route::get('/boutique/verifier-sous-domaine', [ShopSettingsController::class, 'checkSubdomain'])->name('shop.check_subdomain');

    Route::get('/boutique/pages', [ShopPageController::class, 'index'])->name('shop.pages.index');
    Route::get('/boutique/pages/{page}/modifier', [ShopPageController::class, 'edit'])->name('shop.pages.edit');
    Route::put('/boutique/pages/{page}', [ShopPageController::class, 'update'])->name('shop.pages.update');

    // Module E - Commandes (E-01, E-02, E-05)
    Route::get('/commandes', [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/notifications/check', [\App\Http\Controllers\OrderController::class, 'checkNewOrders'])->name('orders.notifications.check');
    Route::get('/commandes/exporter', [\App\Http\Controllers\OrderController::class, 'exportCsv'])->name('orders.export_csv');
    Route::get('/commandes/{order}', [\App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');
    Route::post('/commandes/{order}/statut', [\App\Http\Controllers\OrderController::class, 'updateStatus'])->name('orders.update_status');
    Route::post('/commandes/{order}/encaisser', [\App\Http\Controllers\OrderController::class, 'markAsPaid'])->name('orders.mark_paid');
    Route::post('/commandes/{order}/annuler', [\App\Http\Controllers\OrderController::class, 'cancel'])->name('orders.cancel');

    // Module E - Fichier Clients (E-04)
    Route::get('/clients', [\App\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');

    // Module G - Zones & Frais de Livraison (G-01)
    Route::resource('livraison', \App\Http\Controllers\ShippingZoneController::class)
        ->except(['create', 'show', 'edit'])
        ->names('shipping');

    // Module H - Publicité par l'IA (H-01 à H-05)
    Route::get('/publicite', [\App\Http\Controllers\AdvertisingController::class, 'index'])->name('ads.index');
    Route::get('/publicite/creer', [\App\Http\Controllers\AdvertisingController::class, 'create'])->name('ads.create');
    Route::post('/publicite', [\App\Http\Controllers\AdvertisingController::class, 'store'])->name('ads.store');
    Route::get('/publicite/{ad}', [\App\Http\Controllers\AdvertisingController::class, 'show'])->name('ads.show');
    Route::post('/publicite/{ad}/log-video-export', [\App\Http\Controllers\AdvertisingController::class, 'logVideoExport'])->name('ads.log_video_export');
    Route::post('/publicite/{ad}/regenerate', [\App\Http\Controllers\AdvertisingController::class, 'regenerate'])->name('ads.regenerate');

    // Module I - Statistiques & Performance (I-01 à I-03)
    Route::get('/statistiques', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/statistiques/exporter', [\App\Http\Controllers\AnalyticsController::class, 'exportCsv'])->name('analytics.export_csv');

    // Module J - Super-Administration de la Plateforme (J-01 à J-08)
    Route::prefix('admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/boutiques', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'shops'])->name('admin.shops');
        Route::post('/boutiques/{shop}/suspendre', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'toggleSuspend'])->name('admin.shops.toggle_suspend');
        Route::post('/boutiques/{shop}/impersonate', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'impersonate'])->name('admin.shops.impersonate');
        Route::post('/impersonate/quitter', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'leaveImpersonate'])->name('admin.impersonate.leave');
        Route::get('/abonnements', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'subscriptions'])->name('admin.subscriptions');
        Route::post('/abonnements/{shop}', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'updateSubscription'])->name('admin.subscriptions.update');
        Route::get('/ia', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'aiUsage'])->name('admin.ai_usage');
        Route::get('/formules', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'plans'])->name('admin.plans');
        Route::post('/formules/{plan}/update', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'updatePlan'])->name('admin.plans.update');
        Route::post('/formules/{plan}/toggle', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'togglePlan'])->name('admin.plans.toggle');
        Route::get('/maintenance', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'maintenance'])->name('admin.maintenance');
        Route::post('/maintenance', [\App\Http\Controllers\Admin\PlatformAdminController::class, 'toggleMaintenance'])->name('admin.maintenance.toggle');
    });
});

// Module D - Vitrine Publique, Panier & Tunnel de Commande (D-01 à D-07, F-01)
Route::prefix('b/{subdomain}')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
    Route::get('/produits', [StorefrontController::class, 'products'])->name('storefront.products');
    Route::get('/produits/{slug}', [StorefrontController::class, 'product'])->name('storefront.product');
    Route::get('/p/{type}', [StorefrontController::class, 'page'])->name('storefront.page');

    Route::get('/panier', [StorefrontController::class, 'cart'])->name('storefront.cart');
    Route::post('/panier/ajouter', [StorefrontController::class, 'addToCart'])->name('storefront.cart.add');
    Route::post('/panier/maj', [StorefrontController::class, 'updateCart'])->name('storefront.cart.update');
    Route::post('/panier/supprimer/{productId}', [StorefrontController::class, 'removeFromCart'])->name('storefront.cart.remove');
    Route::post('/panier/vider', [StorefrontController::class, 'clearCart'])->name('storefront.cart.clear');

    // Tunnel de commande (Checkout) & Suivi
    Route::get('/commander', [\App\Http\Controllers\CheckoutController::class, 'show'])->name('storefront.checkout.show');
    Route::post('/commander', [\App\Http\Controllers\CheckoutController::class, 'process'])->name('storefront.checkout.process');
    Route::get('/commandes/{orderNumber}', [\App\Http\Controllers\CheckoutController::class, 'showOrder'])->name('storefront.order.show');
    Route::post('/commandes/{orderNumber}/simuler-paiement', [\App\Http\Controllers\CheckoutController::class, 'simulateMobileMoney'])->name('storefront.order.simulate_mobile_money');
    Route::get('/suivi', [\App\Http\Controllers\CheckoutController::class, 'tracking'])->name('storefront.tracking');
});

// Route d'initialisation et de vérification des migrations Render
Route::get('/migrate-now', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'PlanSeeder', '--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('media:restore-defaults');
        \App\Models\User::whereIn('email', ['kougnimag@gmail.com', 'kougnima@icloud.com', 'admin@example.test'])
            ->update(['role' => \App\Models\User::ROLE_ADMIN]);
        return response()->json([
            'status' => 'success',
            'message' => 'Migrations, plans, médias et comptes Super-Admin initialisés avec succès.',
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ], 500);
    }
});

// API Keep-Alive anti-mise en veille (Render / Uptime monitors)
Route::get('/api/keep-alive', function () {
    return response()->json([
        'status' => 'alive',
        'app' => config('platform.name', 'Zanika'),
        'environment' => app()->environment(),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.keep-alive');

// Restitution et secours persistant des médias (/storage/{path})
Route::get('/storage/{path}', [\App\Http\Controllers\MediaController::class, 'serve'])
    ->where('path', '.*')
    ->name('media.serve');

// API image de substitution dynamique
Route::get('/api/placeholder-image', [\App\Http\Controllers\MediaController::class, 'placeholder'])
    ->name('api.placeholder');

// API Synthèse vocale IA (Voix-off pour le studio vidéo)
Route::get('/api/tts', [\App\Http\Controllers\MediaController::class, 'tts'])
    ->name('api.tts');



// Route dev-login
if (app()->environment('local')) {
    Route::get('/dev-login', function () {
        $u = \App\Models\User::where('email', 'kougnima@icloud.com')->first() ?? \App\Models\User::first();
        if ($u) { \Illuminate\Support\Facades\Auth::login($u); return redirect()->route('admin.dashboard'); }
        return 'No user';
    });
}
