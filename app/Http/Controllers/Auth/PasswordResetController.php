<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'), function (User $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
            Mail::to($user->email)->send(new PasswordResetMail($user->name, $url, (int) config('auth.passwords.users.expire')));
        });

        // Réponse identique que l'email existe ou non (A-03).
        return back()->with('status', 'Si un compte existe avec cet email, un lien de réinitialisation vient d\'être envoyé.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'failed_logins' => 0,
                    'locked_until' => null,
                ])->save();

                // Toutes les sessions sont fermées après le changement (A-03).
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Ce lien est invalide ou a expiré. Faites une nouvelle demande.']);
        }

        return redirect()->route('login')->with('status', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }
}
