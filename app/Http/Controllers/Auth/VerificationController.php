<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.verify', ['email' => $user->email]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Saisissez le code reçu par email.',
            'code.digits' => 'Le code contient 6 chiffres.',
        ]);

        if (! $this->codes->verify($user, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'Code incorrect ou expiré. Vous pouvez demander un nouveau code.']);
        }

        DB::transaction(function () use ($user): void {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->isVerified()) {
                return;
            }

            $start = now();
            $account->forceFill(['email_verified_at' => $start])->save();
            $shop = $account->shops()->oldest('id')->first();
            $plan = Plan::where('slug', 'essentiel')->where('active', true)->first();
            if ($shop && $plan && ! $shop->subscriptions()->exists()) {
                $shop->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'starts_at' => $start,
                    'trial_ends_at' => $start->copy()->addDays(Plan::TRIAL_DAYS),
                ]);
            }
        });
        $user->refresh();

        // Notification immédiate du Super-Admin (Mobile Push + Email + Audit Alert)
        try {
            app(AdminNotificationService::class)->notifyMerchantRegistered($user, $user->shop);
        } catch (\Throwable $e) {
            Log::warning('[VerificationController] Notification admin échouée : '.$e->getMessage());
        }

        $request->session()->forget('pending_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.step1')->with('status', 'Bienvenue ! Commençons par créer votre boutique avec l\'IA.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user) {
            $this->codes->send($user);
        }

        return back()->with('status', 'Un nouveau code vient de vous être envoyé.');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('pending_user_id');

        return $id ? User::whereKey($id)->whereNull('email_verified_at')->first() : null;
    }
}
