<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $user->forceFill(['email_verified_at' => now()])->save();

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
