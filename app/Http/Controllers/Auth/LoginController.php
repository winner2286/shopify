<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    private const MAX_FAILURES = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(private VerificationCodeService $codes) {}

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Message générique : ne révèle jamais si l'email existe (A-02).
        $generic = ['email' => 'Email ou mot de passe incorrect.'];

        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            Hash::check($credentials['password'], Hash::make('timing-protection'));

            return back()->withInput($request->only('email'))->withErrors($generic);
        }

        if ($user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until, false) / 60);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
            ]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            $this->registerFailure($user);

            return back()->withInput($request->only('email'))->withErrors($generic);
        }

        if (! $user->isVerified()) {
            $this->codes->send($user);
            $request->session()->put('pending_user_id', $user->id);

            return redirect()->route('verification.show')
                ->with('status', 'Veuillez d\'abord confirmer votre email : un nouveau code vous a été envoyé.');
        }

        $user->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Déconnexion de tous les appareils (A-02). */
    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill(['remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return $this->destroy($request)->with('status', 'Vous avez été déconnecté de tous vos appareils.');
    }

    private function registerFailure(User $user): void
    {
        $failures = $user->failed_logins + 1;

        if ($failures >= self::MAX_FAILURES) {
            $user->forceFill([
                'failed_logins' => 0,
                'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
            ])->save();

            Mail::to($user->email)->send(new AccountLockedMail($user->name, self::LOCK_MINUTES));

            return;
        }

        $user->forceFill(['failed_logins' => $failures])->save();
    }
}
