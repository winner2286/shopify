<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\FeexPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MerchantPlanConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_and_new_checkout_use_the_same_monthly_plan_price(): void
    {
        $plan = Plan::where('slug', 'essentiel')->firstOrFail();
        $this->assertSame(7500, (int) $plan->monthly_price);
        $this->assertSame(5, Plan::where('slug', 'premium')->firstOrFail()->maxShops());

        $this->get(route('home'))->assertOk()->assertSee('7 500')->assertDontSee('Facturation Annuelle');

        [$user, $shop] = $this->merchant();
        $this->actingAs($user)->get(route('subscription.checkout', $plan->slug))->assertOk()
            ->assertViewHas('monthlyPrice', 7500);
        $this->assertDatabaseHas('subscriptions', ['shop_id' => $shop->id, 'plan_id' => $plan->id, 'status' => 'pending', 'monthly_price' => 7500]);
        $this->assertSame(80, $shop->aiCreditsRemaining());
    }

    public function test_verification_starts_one_essential_trial_with_a_real_expiry(): void
    {
        Mail::fake();
        $this->freezeTime();
        [$user, $shop] = $this->merchant(verified: false);
        $user->verificationCodes()->create(['purpose' => 'register', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);

        $this->withSession(['pending_user_id' => $user->id])->post(route('verification.verify'), ['code' => '123456'])
            ->assertRedirect(route('onboarding.step1'));

        $subscription = $shop->accountSubscription();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isTrial());
        $this->assertSame('essentiel', $subscription->plan->slug);
        $this->assertTrue($subscription->trial_ends_at->equalTo(now()->addDays(14)->startOfSecond()));
        $this->assertSame(3, $shop->studioScenesRemaining());
        $this->assertDatabaseCount('subscriptions', 1);

        $this->travel(15)->days();
        $this->assertNull($shop->accountSubscription());
        $this->assertSame(0, $shop->studioScenesRemaining());
    }

    public function test_grandfathered_renewal_and_plan_changes_take_effect_on_all_shops(): void
    {
        [$user, $shop] = $this->merchant();
        $other = $user->shops()->create(['name' => 'Seconde boutique', 'status' => Shop::DRAFT]);
        $essentiel = Plan::where('slug', 'essentiel')->firstOrFail();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $legacy = $shop->subscriptions()->create(['plan_id' => $essentiel->id, 'status' => 'active', 'ends_at' => now()->addWeek(), 'monthly_price' => 10000]);

        $this->actingAs($user)->withSession(['active_shop_id' => $other->id])->get(route('subscription.checkout', $essentiel->slug))
            ->assertOk()->assertViewHas('monthlyPrice', 10000);
        $this->get(route('subscription.checkout', $pro->slug))->assertOk()->assertViewHas('monthlyPrice', 15000);

        $shop->consumeAiCredits(10);
        app(FeexPayService::class)->handleSubscriptionPaymentSuccess($legacy, 'PLAN-CONSISTENCY-UPGRADE', $pro);
        $this->assertSame('pro', $shop->currentPlan()->slug);
        $this->assertSame('pro', $other->currentPlan()->slug);
        $this->assertSame(340, $other->aiCreditsRemaining());
        $this->assertSame(15000, $other->subscription()->firstOrFail()->monthlyPriceFor($pro));
    }

    public function test_payment_verification_uses_the_amount_shown_to_a_new_subscriber(): void
    {
        config(['feexpay.shop_id' => 'plan-shop', 'feexpay.token' => 'plan-test-token']);
        Http::preventStrayRequests();
        [$user, $shop] = $this->merchant();
        $plan = Plan::where('slug', 'essentiel')->firstOrFail();
        $this->actingAs($user)->get(route('subscription.checkout', $plan->slug))->assertOk()->assertViewHas('monthlyPrice', 7500);
        $subscription = $shop->subscription()->firstOrFail();
        $customId = "SUB:{$subscription->id}:{$plan->id}";
        $providerResult = ['status' => 'SUCCESSFUL', 'custom_id' => $customId, 'currency' => 'XOF', 'shop_id' => 'plan-shop'];
        Http::fake([
            '*/PLAN-WRONG' => Http::response($providerResult + ['amount' => 10000, 'reference' => 'PLAN-WRONG']),
            '*/PLAN-RIGHT' => Http::response($providerResult + ['amount' => 7500, 'reference' => 'PLAN-RIGHT']),
        ]);

        $this->postJson(route('feexpay.webhook'), ['custom_id' => $customId, 'reference' => 'PLAN-WRONG'])->assertUnprocessable();
        $this->assertSame('pending', $subscription->fresh()->status);
        $this->postJson(route('feexpay.webhook'), ['custom_id' => $customId, 'reference' => 'PLAN-RIGHT'])->assertOk();
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(7500, $subscription->fresh()->monthlyPriceFor($plan));
    }

    public function test_premium_can_create_five_shops_and_is_blocked_from_creating_a_sixth(): void
    {
        [$user, $shop] = $this->merchant();
        $shop->subscriptions()->create(['plan_id' => Plan::where('slug', 'premium')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($user);
        foreach (range(2, 5) as $index) {
            $this->post(route('shops.store'), ['name' => "Boutique {$index}", 'country' => 'TG', 'currency' => 'XOF'])->assertRedirect(route('dashboard'));
        }

        $this->postJson(route('shops.store'), ['name' => 'Sixième boutique', 'country' => 'TG', 'currency' => 'XOF'])->assertForbidden();
        $this->assertSame(5, $user->shops()->count());
        $this->assertSame('premium', $user->activeShop()->currentPlan()->slug);
    }

    private function merchant(bool $verified = true): array
    {
        $user = User::factory()->create(['role' => User::ROLE_MERCHANT, 'email_verified_at' => $verified ? now() : null]);
        $shop = $user->shops()->create(['name' => 'Boutique essai', 'status' => Shop::DRAFT]);

        return [$user, $shop];
    }
}
