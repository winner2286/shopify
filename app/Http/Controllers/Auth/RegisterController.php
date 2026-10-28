<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(private VerificationCodeService $codes) {}

    public function create()
    {
        return view('auth.register', ['countries' => config('platform.countries')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'country' => ['required', 'string', 'size:2'],
            'terms' => ['accepted'],
            'website' => ['prohibited'], // pot de miel anti-robot (en attendant un CAPTCHA)
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $existing = User::where('email', $data['email'])->first();

        if ($existing && $existing->isVerified()) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Cet email est déjà utilisé.'])
                ->with('login_hint', true);
        }

        // Compte non vérifié : on réutilise la ligne (aucun doublon) et on renvoie un nouveau code.
        $user = DB::transaction(function () use ($data, $existing) {
            $user = $existing ?? new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'country' => strtoupper($data['country']),
                'role' => User::ROLE_MERCHANT,
                'terms_accepted_at' => now(),
            ])->save();

            Shop::firstOrCreate(['user_id' => $user->id], ['status' => Shop::DRAFT]);

            return $user;
        });

        $this->codes->send($user);
        $request->session()->put('pending_user_id', $user->id);

        return redirect()->route('verification.show');
    }
}
