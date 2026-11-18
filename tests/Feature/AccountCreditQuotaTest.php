<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Services\AccountCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AccountCreditQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_usage_and_new_reservations_are_shared_across_shops(): void
    {
        [$shop, $otherShop] = $this->accountShops();
        $shop->update(['ai_credits_used' => 340, 'photoroom_credits_used' => 59]);
        $otherShop->update(['ai_credits_used' => 9]);

        $this->assertSame(349, $shop->aiCreditsUsedThisMonth());
        $this->assertSame(1, $otherShop->aiCreditsRemaining());
        $otherShop->consumeAiCredits();
        $this->assertSame(0, $shop->aiCreditsRemaining());
        $this->assertSame(10, $otherShop->fresh()->ai_credits_used);

        $version = $otherShop->consumePhotoroomCredits();
        $this->assertSame(60, $shop->photoroomCreditsUsedThisMonth());
        $this->assertSame(0, $shop->photoroomCreditsRemaining());
        $otherShop->refundPhotoroomCredits(1, $version);
        $this->assertSame(1, $shop->photoroomCreditsRemaining());

        $this->expectException(ValidationException::class);
        $shop->consumeAiCredits();
    }

    public function test_generated_scenes_use_their_own_shared_allowance_and_refund_provider_failure(): void
    {
        [$shop, $otherShop] = $this->accountShops();
        try {
            $shop->withStudioScene(fn () => throw new RuntimeException('provider failure'));
            $this->fail('The provider failure should still be reported.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider failure', $exception->getMessage());
        }
        $this->assertSame(2, $otherShop->studioScenesRemaining());
        $this->assertSame('preview', $shop->withStudioScene(fn () => 'preview'));
        $otherShop->withStudioScene(fn () => 'preview');
        $this->assertSame(2, $shop->studioScenesUsedThisMonth());
        $this->assertSame(0, $otherShop->studioScenesRemaining());
        $this->assertSame(350, $otherShop->aiCreditsRemaining());
        $this->assertSame(60, $otherShop->photoroomCreditsRemaining());

        $this->expectException(ValidationException::class);
        $shop->withStudioScene(fn () => $this->fail('The provider must not run when the quota is exhausted.'));
    }

    public function test_deleting_a_shop_does_not_renew_allowances_and_refunds_follow_its_usage(): void
    {
        [$shop, $otherShop] = $this->accountShops();
        $version = $otherShop->consumeAiCredits(5);
        $otherShop->withStudioScene(fn () => 'preview');
        app(AccountCreditService::class)->deleteShop($otherShop);

        $this->assertDatabaseMissing('shops', ['id' => $otherShop->id]);
        $this->assertSame(345, $shop->aiCreditsRemaining());
        $this->assertSame(1, $shop->studioScenesRemaining());
        $otherShop->refundAiCredits(5, $version);
        $this->assertSame(350, $shop->aiCreditsRemaining());
    }

    public function test_monthly_reset_cannot_refund_a_previous_reservation_into_the_new_month(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(8));
        [$shop, $otherShop] = $this->accountShops();
        $version = $shop->consumeAiCredits(5);
        $shop->consumePhotoroomCredits(4);
        $shop->withStudioScene(fn () => 'preview');

        $this->travelTo(now()->startOfMonth()->addMonth()->addDay());
        $this->assertSame(60, $otherShop->photoroomCreditsRemaining());
        $this->assertSame(2, $shop->studioScenesRemaining());
        $otherShop->consumeAiCredits(6);
        $shop->refundAiCredits(5, $version);
        $this->assertSame(344, $otherShop->aiCreditsRemaining());
        $this->assertSame(6, $shop->aiCreditsUsedThisMonth());
    }

    public function test_expired_account_cannot_spend_paid_credits_after_switching_store(): void
    {
        [$shop, $otherShop] = $this->accountShops();
        $shop->subscription()->update(['ends_at' => now()->subDay()]);

        $this->assertSame(0, $otherShop->aiCreditsRemaining());
        $this->assertSame(0, $otherShop->photoroomCreditsRemaining());
        $this->assertSame(0, $otherShop->studioScenesRemaining());

        $this->expectException(ValidationException::class);
        $otherShop->withStudioScene(fn () => $this->fail('An expired account must not call the provider.'));
    }

    /** @return array{Shop, Shop} */
    private function accountShops(): array
    {
        $merchant = User::factory()->create(['role' => User::ROLE_MERCHANT]);
        $shop = $merchant->shops()->create(['name' => 'Boutique principale']);
        $otherShop = $merchant->shops()->create(['name' => 'Boutique secondaire']);
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $plan->update(['studio_scene_credits' => 2]);
        $shop->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay()]);

        return [$shop, $otherShop];
    }
}
