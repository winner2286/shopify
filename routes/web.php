<?php

use App\Http\Controllers\Admin\PlatformAdminController;
use App\Http\Controllers\AdvertisingController;
use App\Http\Controllers\AiAdvisorController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AiMarketingController;
use App\Http\Controllers\AiOrderController;
use App\Http\Controllers\AiProductController;
use App\Http\Controllers\AiShopMemoryController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CreativeStudioController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountCodeController;
use App\Http\Controllers\FeexPayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\ShippingZoneController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopPageController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\RequirePlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/zanika.webmanifest', [HomeController::class, 'manifest'])->name('pwa.manifest');
Route::get('/recu/{orderNumber}', [OrderController::class, 'publicReceipt'])->name('orders.public_receipt');

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

    // Création rapide de boutique & Gestion multi-boutiques (selo.ci style)
    Route::get('/boutiques/creer', [ShopController::class, 'create'])->name('shops.create');
    Route::post('/boutiques', [ShopController::class, 'store'])->name('shops.store');
    Route::post('/boutiques/{shop}/basculer', [ShopController::class, 'switchShop'])->name('shops.switch');

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
    Route::post('/onboarding/logo/upload', [OnboardingController::class, 'uploadCustomLogo'])->name('onboarding.upload_logo');

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
    Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/notifications/check', [OrderController::class, 'checkNewOrders'])->name('orders.notifications.check');
    Route::get('/commandes/exporter', [OrderController::class, 'exportCsv'])->middleware(RequirePlanFeature::class.':export_accounting')->name('orders.export_csv');
    Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/commandes/{order}/recu', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::post('/commandes/{order}/livreur', [OrderController::class, 'dispatchToDriver'])->name('orders.dispatch_driver');
    Route::post('/commandes/{order}/statut', [OrderController::class, 'updateStatus'])->name('orders.update_status');
    Route::post('/commandes/{order}/encaisser', [OrderController::class, 'markAsPaid'])->name('orders.mark_paid');
    Route::post('/commandes/{order}/annuler', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Module E - Fichier Clients (E-04)
    Route::get('/clients', [CustomerController::class, 'index'])->name('customers.index');

    // Module G - Zones & Frais de Livraison (G-01)
    Route::resource('livraison', ShippingZoneController::class)
        ->except(['create', 'show', 'edit'])
        ->parameters(['livraison' => 'shippingZone'])
        ->names('shipping');

    // Codes promo & réductions
    Route::middleware(RequirePlanFeature::class.':promo_codes')->group(function () {
        Route::get('/codes-promo', [DiscountCodeController::class, 'index'])->name('discounts.index');
        Route::post('/codes-promo', [DiscountCodeController::class, 'store'])->name('discounts.store');
        Route::post('/codes-promo/{discountCode}/toggle', [DiscountCodeController::class, 'toggle'])->name('discounts.toggle');
        Route::delete('/codes-promo/{discountCode}', [DiscountCodeController::class, 'destroy'])->name('discounts.destroy');
    });

    // Module H - Publicité & Studio Affiches Promo (H-01 à H-05)
    Route::middleware(RequirePlanFeature::class.':ai_visuals')->group(function () {
        Route::get('/publicite', [AdvertisingController::class, 'index'])->name('ads.index');
        Route::get('/publicite/affiches', [CreativeStudioController::class, 'create'])->name('ads.flyers');
        Route::get('/publicite/creer', [CreativeStudioController::class, 'create'])->name('ads.create');
        Route::post('/publicite/projets', [CreativeStudioController::class, 'store'])->name('ads.studio.store');
        Route::post('/publicite', [AdvertisingController::class, 'store'])->name('ads.store');
        Route::get('/publicite/{ad}', [CreativeStudioController::class, 'show'])->name('ads.show');
        Route::put('/publicite/{ad}/montage', [CreativeStudioController::class, 'save'])->name('ads.studio.save');
        Route::post('/publicite/{ad}/medias', [CreativeStudioController::class, 'upload'])->name('ads.studio.upload');
        Route::get('/publicite/{ad}/medias/{media}', [CreativeStudioController::class, 'media'])->name('ads.studio.media');
        Route::delete('/publicite/{ad}/medias/{media}', [CreativeStudioController::class, 'deleteMedia'])->name('ads.studio.delete_media');
        Route::post('/publicite/{ad}/plans-ia', [CreativeStudioController::class, 'generateShot'])->middleware('throttle:6,1')->name('ads.studio.ai');
        Route::get('/publicite/{ad}/plans-ia/{job}', [CreativeStudioController::class, 'shotStatus'])->middleware('throttle:20,1')->name('ads.studio.ai_status');
        Route::post('/publicite/{ad}/log-video-export', [AdvertisingController::class, 'logVideoExport'])->name('ads.log_video_export');
        Route::post('/publicite/{ad}/regenerate', [AdvertisingController::class, 'regenerate'])->name('ads.regenerate');
        Route::post('/publicite/{ad}/update-products', [AdvertisingController::class, 'updateProducts'])->name('ads.update_products');
        Route::post('/publicite/{ad}/platform', [AdvertisingController::class, 'updatePlatform'])->name('ads.update_platform');
        Route::post('/publicite/{ad}/upload-audio', [AdvertisingController::class, 'uploadCustomAudio'])->name('ads.upload_custom_audio');
        Route::post('/publicite/{ad}/reset-audio', [AdvertisingController::class, 'resetCustomAudio'])->name('ads.reset_custom_audio');
    });

    // Module I - Statistiques & Performance (I-01 à I-03)
    Route::middleware(RequirePlanFeature::class.':advanced_stats')->group(function () {
        Route::get('/statistiques', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/statistiques/conseiller-ia', [AiAdvisorController::class, 'index'])->name('analytics.advisor');
        Route::post('/statistiques/conseiller-ia/briefing', [AiAdvisorController::class, 'generateBriefing'])->name('analytics.advisor.briefing');
    });
    Route::get('/statistiques/exporter', [AnalyticsController::class, 'exportCsv'])->middleware(RequirePlanFeature::class.':export_accounting')->name('analytics.export_csv');

    // ECOM AI ASSISTANT - Copilote Commercial & Opérationnel
    Route::get('/assistant-ia', [AiAssistantController::class, 'index'])->name('ai.assistant');
    Route::get('/assistant-ia/conversation-active', [AiAssistantController::class, 'getActiveConversation'])->name('ai.conversations.active');
    Route::get('/assistant-ia/conversations/{conversation}', [AiAssistantController::class, 'showConversation'])->name('ai.conversations.show');
    Route::post('/assistant-ia/message', [AiAssistantController::class, 'sendMessage'])->name('ai.message.send');
    Route::post('/assistant-ia/action/approve', [AiAssistantController::class, 'approveAction'])->name('ai.action.approve');
    Route::post('/assistant-ia/feedback', [AiFeedbackController::class, 'store'])->name('ai.feedback.store');
    Route::delete('/assistant-ia/conversations/{conversation}', [AiAssistantController::class, 'destroyConversation'])->name('ai.conversations.destroy');

    // Gestion souveraine de la mémoire IA par le commerçant
    Route::get('/assistant-ia/memoire', [AiShopMemoryController::class, 'index'])->name('ai.memory.index');
    Route::post('/assistant-ia/memoire', [AiShopMemoryController::class, 'store'])->name('ai.memory.store');
    Route::put('/assistant-ia/memoire/{memory}', [AiShopMemoryController::class, 'update'])->name('ai.memory.update');
    Route::delete('/assistant-ia/memoire/{memory}', [AiShopMemoryController::class, 'destroy'])->name('ai.memory.destroy');

    // AI Products & Studio Visuel IA (Module 4 & 5)
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/assistant-ia/produits/analyser-photo', [AiProductController::class, 'analyzePhoto'])->name('ai.products.analyze_photo');
        Route::post('/assistant-ia/produits/verifier-qualite', [AiProductController::class, 'verifyQuality'])->name('ai.products.verify_quality');
        Route::post('/assistant-ia/produits/generer-variante', [AiProductController::class, 'generateVariant'])->name('ai.products.generate_variant');
        Route::post('/assistant-ia/produits/{product}/optimiser', [AiProductController::class, 'optimizeProduct'])->name('ai.products.optimize');
        Route::post('/assistant-ia/produits/detourer-photoroom', [AiProductController::class, 'cutoutForAd'])->middleware(RequirePlanFeature::class.':ai_visuals')->name('ai.products.photoroom_cutout');
        Route::post('/assistant-ia/studio/generer', [AiProductController::class, 'generateVisual'])->middleware(RequirePlanFeature::class.':ai_visuals')->name('ai.studio.generate');
    });
    Route::get('/assistant-ia/studio', [AiProductController::class, 'studio'])->middleware(RequirePlanFeature::class.':ai_visuals')->name('ai.studio');

    // AI Orders & AI Customer (Module 6 & 7)
    Route::get('/assistant-ia/commandes/{order}/analyse-risque', [AiOrderController::class, 'analyzeRisk'])->name('ai.orders.analyze_risk');
    Route::get('/assistant-ia/commandes/revue-cod', [AiOrderController::class, 'codReview'])->name('ai.orders.cod_review');
    Route::post('/assistant-ia/relation-client/repondre', [AiOrderController::class, 'answerCustomer'])->name('ai.customer.answer');

    // AI Marketing & Automations (Module 9 & 10)
    Route::get('/assistant-ia/marketing', [AiMarketingController::class, 'index'])->name('ai.marketing');
    Route::post('/assistant-ia/marketing/generer', [AiMarketingController::class, 'generate'])->name('ai.marketing.generate');
    Route::middleware(RequirePlanFeature::class.':stock_alerts')->group(function () {
        Route::get('/assistant-ia/automatisations', [AiMarketingController::class, 'automations'])->name('ai.automations');
        Route::post('/assistant-ia/automatisations/{automation}/basculer', [AiMarketingController::class, 'toggleAutomation'])->name('ai.automations.toggle');
        Route::post('/assistant-ia/automatisations/executer-maintenant', [AiMarketingController::class, 'runAutomationsNow'])->name('ai.automations.run_now');
    });

    // Web Push Notifications PWA (Commerçants & Super-Admins)
    Route::get('/push/vapid-key', [PushNotificationController::class, 'vapidPublicKey'])->name('push.vapid_key');
    Route::post('/push/subscribe', [PushNotificationController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushNotificationController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [PushNotificationController::class, 'test'])->name('push.test');

    // Module J - Super-Administration de la Plateforme (J-01 à J-08)
    Route::prefix('admin')->group(function () {
        Route::get('/', [PlatformAdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/boutiques', [PlatformAdminController::class, 'shops'])->name('admin.shops');
        Route::post('/boutiques/{shop}/suspendre', [PlatformAdminController::class, 'toggleSuspend'])->name('admin.shops.toggle_suspend');
        Route::post('/boutiques/{shop}/impersonate', [PlatformAdminController::class, 'impersonate'])->name('admin.shops.impersonate');
        Route::post('/impersonate/quitter', [PlatformAdminController::class, 'leaveImpersonate'])->name('admin.impersonate.leave');
        Route::get('/abonnements', [PlatformAdminController::class, 'subscriptions'])->name('admin.subscriptions');
        Route::post('/abonnements/{shop}', [PlatformAdminController::class, 'updateSubscription'])->name('admin.subscriptions.update');
        Route::post('/abonnements/{shop}/rappel-essai', [PlatformAdminController::class, 'sendTrialReminder'])->name('admin.subscriptions.send_reminder');
        Route::get('/ia', [PlatformAdminController::class, 'aiUsage'])->name('admin.ai_usage');
        Route::get('/ia/feedbacks', [PlatformAdminController::class, 'aiFeedbacks'])->name('admin.ai_feedbacks');
        Route::post('/ia/feedbacks/{feedback}/convertir-rag', [PlatformAdminController::class, 'resolveFeedbackToKnowledge'])->name('admin.ai_feedbacks.resolve');
        Route::post('/ia/boutiques/{shop}/recharger', [PlatformAdminController::class, 'rechargeShopAiCredits'])->name('admin.ai.recharge');
        Route::get('/formules', [PlatformAdminController::class, 'plans'])->name('admin.plans');
        Route::post('/formules/{plan}/update', [PlatformAdminController::class, 'updatePlan'])->name('admin.plans.update');
        Route::post('/formules/{plan}/toggle', [PlatformAdminController::class, 'togglePlan'])->name('admin.plans.toggle');
        Route::get('/maintenance', [PlatformAdminController::class, 'maintenance'])->name('admin.maintenance');
        Route::post('/maintenance', [PlatformAdminController::class, 'toggleMaintenance'])->name('admin.maintenance.toggle');

        // Audit, Contrôle & Alertes Système Super-Admin
        Route::get('/audit', [PlatformAdminController::class, 'audit'])->name('admin.audit');
        Route::post('/audit/alertes/{alert}/lue', [PlatformAdminController::class, 'markAlertRead'])->name('admin.audit.read');
        Route::post('/audit/alertes/toutes-lues', [PlatformAdminController::class, 'markAllAlertsRead'])->name('admin.audit.read_all');
        Route::post('/audit/test-sante', [PlatformAdminController::class, 'runHealthCheck'])->name('admin.audit.health_check');
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
    Route::get('/commander', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
    Route::post('/commander', [CheckoutController::class, 'process'])->middleware('throttle:30,1')->name('storefront.checkout.process');
    Route::post('/commander/code-promo', [CheckoutController::class, 'applyPromoCode'])->middleware('throttle:30,1')->name('storefront.checkout.apply_promo');
    Route::post('/commander/code-promo/retirer', [CheckoutController::class, 'removePromoCode'])->name('storefront.checkout.remove_promo');
    Route::get('/commandes/{orderNumber}', [CheckoutController::class, 'showOrder'])->name('storefront.order.show');
    Route::post('/commandes/{orderNumber}/simuler-paiement', [CheckoutController::class, 'simulateMobileMoney'])->middleware('throttle:15,1')->name('storefront.order.simulate_mobile_money');
    Route::get('/suivi', [CheckoutController::class, 'tracking'])->middleware('throttle:20,1')->name('storefront.tracking');
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
Route::get('/storage/{path}', [MediaController::class, 'serve'])
    ->where('path', '.*')
    ->name('media.serve');

// API image de substitution dynamique
Route::get('/api/placeholder-image', [MediaController::class, 'placeholder'])
    ->name('api.placeholder');

// API Synthèse vocale IA (Voix-off pour le studio vidéo)
Route::get('/api/tts', [MediaController::class, 'tts'])
    ->middleware(['auth', RequirePlanFeature::class.':ai_visuals', 'throttle:60,1'])
    ->name('api.tts');

// Passerelle de paiement GeniusPay (Mobile Money & Cartes Bancaires) + rétrocompatibilité FeexPay
Route::match(['get', 'post'], '/geniuspay/callback', [FeexPayController::class, 'callback'])->middleware('throttle:60,1')->name('geniuspay.callback');
Route::post('/api/geniuspay/webhook', [FeexPayController::class, 'webhook'])->middleware('throttle:60,1')->name('geniuspay.webhook');
Route::match(['get', 'post'], '/feexpay/callback', [FeexPayController::class, 'callback'])->middleware('throttle:60,1')->name('feexpay.callback');
Route::post('/api/feexpay/webhook', [FeexPayController::class, 'webhook'])->middleware('throttle:60,1')->name('feexpay.webhook');

// Paiements et abonnements SaaS commerçant via FeexPay
Route::middleware('auth')->group(function () {
    Route::get('/abonnement/paiement/{plan:slug}', [FeexPayController::class, 'subscriptionCheckout'])->name('subscription.checkout');
    Route::post('/abonnement/simulate/{subscription}/{plan}', [FeexPayController::class, 'simulateSubscriptionPayment'])->name('subscription.simulate');
});

if (app()->environment('local')) {
    Route::get('/dev-login', function () {
        $u = User::where('email', 'kougnima@icloud.com')->first() ?? User::first();
        if ($u) {
            Auth::login($u);

            return redirect()->route('admin.dashboard');
        }

        return 'No user';
    });
}
