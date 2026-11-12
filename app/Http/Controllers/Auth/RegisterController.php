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
        return view('auth.register', [
            'countries' => config('platform.countries'),
            'currencies' => config('platform.currencies', []),
            'countryProfiles' => config('platform.country_profiles', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validCurrencies = array_keys(config('platform.currencies', ['XOF' => []]));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'in:' . implode(',', $validCurrencies)],
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

        $countryCode = strtoupper($data['country']);
        $profileKey = $countryCode === 'XX' ? 'OTHER' : $countryCode;
        $profile = config("platform.country_profiles.{$profileKey}") ?? config('platform.country_profiles.TG');
        $resolvedCurrency = ! empty($data['currency'])
            ? strtoupper($data['currency'])
            : ($profile['currency'] ?? 'XOF');

        // Compte non vérifié : on réutilise la ligne (aucun doublon) et on renvoie un nouveau code.
        $user = DB::transaction(function () use ($data, $existing, $countryCode, $resolvedCurrency, $profile) {
            $user = $existing ?? new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'country' => $countryCode,
                'role' => User::ROLE_MERCHANT,
                'terms_accepted_at' => now(),
            ])->save();

            $shop = Shop::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => Shop::DRAFT,
                    'currency' => $resolvedCurrency,
                    'contact' => [
                        'country' => $countryCode,
                        'city' => $profile['city'] ?? 'Lomé',
                    ],
                ]
            );

            $contact = is_array($shop->contact) ? $shop->contact : [];
            $contact['country'] = $countryCode;
            if (empty($contact['city'])) {
                $contact['city'] = $profile['city'] ?? 'Lomé';
            }
            $shop->update([
                'currency' => $resolvedCurrency,
                'contact' => $contact,
            ]);

            $shop->ensureDefaultShippingZones();

            return $user;
        });

        $this->codes->send($user);
        $request->session()->put('pending_user_id', $user->id);

        return redirect()->route('verification.show');
    }
}
