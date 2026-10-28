<?php

namespace Tests\Feature;

use App\Mail\AccountLockedMail;
use App\Mail\PasswordResetMail;
use App\Mail\VerificationCodeMail;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Kofi Mensah',
            'email' => 'kofi@example.test',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            'country' => 'TG',
            'terms' => '1',
        ], $override);
    }

    private function capturedCode(): string
    {
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function verifiedUser(string $password = 'motdepasse1'): User
    {
        return User::factory()->create([
            'email' => 'kofi@example.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }

    public function test_pages_publiques_accessibles(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('Essentiel');
        $this->get('/inscription')->assertOk();
        $this->get('/connexion')->assertOk();
        $this->get('/mot-de-passe-oublie')->assertOk();
    }

    public function test_inscription_cree_compte_non_verifie_et_envoie_un_code(): void
    {
        Mail::fake();

        $this->post('/inscription', $this->payload())->assertRedirect('/verification');

        $user = User::where('email', 'kofi@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('merchant', $user->role);
        $this->assertSame('draft', $user->shop->status);
        $this->assertGuest();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->capturedCode());
    }

    public function test_inscription_exige_cgu_et_mot_de_passe_de_8_caracteres(): void
    {
        $this->post('/inscription', $this->payload(['terms' => null]))->assertSessionHasErrors('terms');
        $this->post('/inscription', $this->payload(['password' => 'court', 'password_confirmation' => 'court']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_pot_de_miel_bloque_les_robots(): void
    {
        $this->post('/inscription', $this->payload(['website' => 'http://spam']))->assertSessionHasErrors('website');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_deja_utilise_ne_cree_pas_de_doublon(): void
    {
        $this->verifiedUser();

        $this->post('/inscription', $this->payload())->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'kofi@example.test')->count());
    }

    public function test_code_valide_active_le_compte_et_connecte(): void
    {
        Mail::fake();
        $this->post('/inscription', $this->payload());
        $code = $this->capturedCode();

        $this->post('/verification', ['code' => $code])->assertRedirect('/onboarding/activite');

        $this->assertAuthenticated();
        $this->assertNotNull(User::first()->email_verified_at);
    }

    public function test_code_incorrect_est_refuse_et_limite_a_5_tentatives(): void
    {
        Mail::fake();
        $this->post('/inscription', $this->payload());
        $code = $this->capturedCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/verification', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Après 5 essais, même le bon code est refusé.
        $this->post('/verification', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_code_expire_apres_10_minutes(): void
    {
        Mail::fake();
        $this->post('/inscription', $this->payload());
        $code = $this->capturedCode();

        $this->travel(11)->minutes();

        $this->post('/verification', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_connexion_reussie(): void
    {
        $this->verifiedUser();

        $this->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'motdepasse1'])
            ->assertRedirect('/tableau-de-bord');
        $this->assertAuthenticated();
    }

    public function test_message_d_erreur_identique_que_l_email_existe_ou_non(): void
    {
        $this->verifiedUser();

        $this->from('/connexion')->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'mauvais'])
            ->assertSessionHasErrors('email');
        $existing = session('errors')->get('email');

        $this->from('/connexion')->post('/connexion', ['email' => 'inconnu@example.test', 'password' => 'mauvais'])
            ->assertSessionHasErrors('email');
        $unknown = session('errors')->get('email');

        $this->assertSame($existing, $unknown);
        $this->assertGuest();
    }

    public function test_blocage_15_minutes_apres_5_echecs_avec_alerte_email(): void
    {
        Mail::fake();
        $this->verifiedUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'mauvais']);
        }

        Mail::assertSent(AccountLockedMail::class);

        // Même avec le bon mot de passe, le compte est bloqué.
        $this->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'motdepasse1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(16)->minutes();

        $this->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'motdepasse1'])->assertRedirect('/tableau-de-bord');
        $this->assertAuthenticated();
    }

    public function test_compte_non_verifie_est_renvoye_vers_la_verification(): void
    {
        Mail::fake();
        User::factory()->unverified()->create(['email' => 'kofi@example.test', 'password' => 'motdepasse1']);

        $this->post('/connexion', ['email' => 'kofi@example.test', 'password' => 'motdepasse1'])->assertRedirect('/verification');

        $this->assertGuest();
        Mail::assertSent(VerificationCodeMail::class);
    }

    public function test_deconnexion(): void
    {
        $this->actingAs($this->verifiedUser());

        $this->post('/deconnexion')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_tableau_de_bord_reserve_aux_connectes(): void
    {
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
    }

    public function test_mot_de_passe_oublie_reponse_identique(): void
    {
        Mail::fake();
        $this->verifiedUser();

        $this->post('/mot-de-passe-oublie', ['email' => 'kofi@example.test'])->assertSessionHas('status');
        $existing = session('status');

        $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@example.test'])->assertSessionHas('status');
        $unknown = session('status');

        $this->assertSame($existing, $unknown);
        Mail::assertSent(PasswordResetMail::class, 1);
    }

    public function test_reinitialisation_change_le_mot_de_passe_et_ferme_les_sessions(): void
    {
        Mail::fake();
        $user = $this->verifiedUser();

        $this->post('/mot-de-passe-oublie', ['email' => $user->email]);

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->get($url)->assertOk();

        $this->post('/reinitialisation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ])->assertRedirect('/connexion');

        $this->assertTrue(Hash::check('nouveaumotdepasse', $user->fresh()->password));

        // Lien à usage unique : un second usage échoue.
        $this->post('/reinitialisation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'autremotdepasse1',
            'password_confirmation' => 'autremotdepasse1',
        ])->assertSessionHasErrors('email');
    }

    public function test_lien_de_reinitialisation_expire_apres_30_minutes(): void
    {
        Mail::fake();
        $user = $this->verifiedUser();
        $this->post('/mot-de-passe-oublie', ['email' => $user->email]);

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->travel(31)->minutes();

        $this->post('/reinitialisation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ])->assertSessionHasErrors('email');
    }

    public function test_deconnexion_de_tous_les_appareils(): void
    {
        $user = $this->verifiedUser();
        $this->actingAs($user);

        $this->post('/deconnexion-totale')->assertRedirect('/');
        $this->assertGuest();
    }
}
