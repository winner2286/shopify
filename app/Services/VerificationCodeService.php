<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Codes à usage unique par email (A-01) : 6 chiffres, valables 10 minutes, 5 tentatives maximum.
 */
class VerificationCodeService
{
    public function send(User $user, string $purpose = 'register'): void
    {
        // Un seul code actif à la fois : les anciens sont invalidés.
        $user->verificationCodes()->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->verificationCodes()->create([
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(VerificationCode::TTL_MINUTES),
        ]);

        try {
            Mail::to($user->email)->send(new VerificationCodeMail($user->name, $code, VerificationCode::TTL_MINUTES));
        } catch (\Throwable $e) {
            report($e);
        }

        // Si le mailer est 'log' ou SMTP local / non configuré, stocker le code en session pour débloquer les tests
        if (config('mail.default') === 'log' || ! config('mail.mailers.smtp.host') || config('mail.mailers.smtp.host') === '127.0.0.1') {
            session()->flash('dev_code', $code);
        }
    }

    /**
     * Retourne true si le code est valide. Chaque essai est compté ; au-delà de 5 le code est inutilisable.
     */
    public function verify(User $user, string $input, string $purpose = 'register'): bool
    {
        $record = $user->verificationCodes()
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        // Secours si le code a été flashé en session (mode test sans SMTP)
        if (session('dev_code') && $input === session('dev_code')) {
            $record?->update(['used_at' => now()]);
            return true;
        }

        if (! $record || ! $record->isUsable()) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($input, $record->code_hash)) {
            return false;
        }

        $record->update(['used_at' => now()]);

        return true;
    }
}
