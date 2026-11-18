<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountCreditService
{
    public const AI = 'ai_credits_used';

    public const PHOTOROOM = 'photoroom_credits_used';

    public const STUDIO_SCENES = 'studio_scenes_used';

    private const COUNTERS = [self::AI, self::PHOTOROOM, self::STUDIO_SCENES];

    public function used(Shop $shop, string $counter): int
    {
        if (! $shop->exists || (! array_key_exists($counter, $shop->getAttributes()) && ! Schema::hasColumn('shops', $counter))) {
            return (int) $shop->{$counter};
        }

        $account = $shop->owner()->first();
        if ($account && ($account->credit_quota_state['month'] ?? null) !== now()->format('Y-m')) {
            return DB::transaction(function () use ($account, $counter): int {
                $account = $this->lockAccount((int) $account->id);

                return $this->total($account, $this->currentState($account), $counter);
            }, attempts: 3);
        }

        return $account ? $this->total($account, $account->credit_quota_state, $counter) : (int) $shop->{$counter};
    }

    public function lockAccount(int $accountId): User
    {
        return User::query()->lockForUpdate()->findOrFail($accountId);
    }

    public function consume(Shop $shop, string $counter, int $cost): int
    {
        $amount = max(1, $cost);

        return DB::transaction(function () use ($shop, $counter, $amount): int {
            $account = $this->lockAccount((int) $shop->user_id);
            $state = $this->currentState($account);
            $currentShop = $this->shops($account)->findOrFail($shop->id);
            $limit = match ($counter) {
                self::AI => $currentShop->aiCreditsMonthly(),
                self::PHOTOROOM => $currentShop->photoroomCreditsMonthly(),
                self::STUDIO_SCENES => $currentShop->studioScenesMonthly(),
            };
            if (! $account->isAdmin() && ($limit === 0 || $this->total($account, $state, $counter) + $amount > $limit)) {
                throw ValidationException::withMessages(['credits' => match ($counter) {
                    self::PHOTOROOM => 'Le quota Photoroom de votre compte est atteint.',
                    self::STUDIO_SCENES => 'Le quota de décors générés de votre compte est atteint.',
                    default => 'Crédits IA insuffisants pour cette opération.',
                }]);
            }
            $this->shops($account)->whereKey($shop->id)->increment($counter, $amount);
            $shop->refresh();

            return (int) ($state['versions'][$counter] ?? 0);
        }, attempts: 3);
    }

    public function refund(Shop $shop, string $counter, int $cost, int $version): void
    {
        DB::transaction(function () use ($shop, $counter, $cost, $version): void {
            $account = User::query()->lockForUpdate()->find($shop->user_id);
            if (! $account) {
                return;
            }
            $state = $this->currentState($account);
            if ((int) ($state['versions'][$counter] ?? 0) !== $version) {
                return;
            }
            $amount = max(1, $cost);
            if ($this->shops($account)->whereKey($shop->id)->decrement($counter, $amount) === 1) {
                $shop->refresh();

                return;
            }
            $state['previous_shops'][$counter] = ($state['previous_shops'][$counter] ?? 0) - $amount;
            $this->saveState($account, $state);
        }, attempts: 3);
    }

    public function grant(Shop $shop, string $counter, int $amount): void
    {
        DB::transaction(function () use ($shop, $counter, $amount): void {
            $account = $this->lockAccount((int) $shop->user_id);
            $this->currentState($account);
            $this->shops($account)->whereKey($shop->id)->decrement($counter, $amount);
        }, attempts: 3);
    }

    public function reset(Shop $shop, ?string $counter = null): void
    {
        DB::transaction(function () use ($shop, $counter): void {
            $account = $this->lockAccount((int) $shop->user_id);
            $this->resetCounters($account, $this->currentState($account), $counter === null ? self::COUNTERS : [$counter]);
            $shop->refresh();
        }, attempts: 3);
    }

    public function refreshMonth(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $account = User::query()->lockForUpdate()->find($user->id);
            if ($account) {
                $this->currentState($account);
            }
        }, attempts: 3);
    }

    public function deleteShop(Shop $shop): void
    {
        DB::transaction(function () use ($shop): void {
            $account = $this->lockAccount((int) $shop->user_id);
            $state = $this->currentState($account);
            $currentShop = $this->shops($account)->findOrFail($shop->id);
            foreach (self::COUNTERS as $counter) {
                $state['previous_shops'][$counter] = ($state['previous_shops'][$counter] ?? 0) + (int) $currentShop->{$counter};
            }
            $this->saveState($account, $state);
            $currentShop->delete();
        }, attempts: 3);
    }

    private function currentState(User $account): array
    {
        $state = $account->credit_quota_state ?? [];
        $month = now()->format('Y-m');
        if (! isset($state['month'])) {
            $state['month'] = $month;
            $this->saveState($account, $state);
        } elseif ($state['month'] !== $month) {
            $state['month'] = $month;
            $state = $this->resetCounters($account, $state, self::COUNTERS);
        }

        return $state;
    }

    private function resetCounters(User $account, array $state, array $counters): array
    {
        foreach ($counters as $counter) {
            $state['versions'][$counter] = ($state['versions'][$counter] ?? 0) + 1;
            $state['previous_shops'][$counter] = 0;
        }
        $this->shops($account)->update(array_fill_keys($counters, 0));
        $this->saveState($account, $state);

        return $state;
    }

    private function saveState(User $account, array $state): void
    {
        $account->forceFill(['credit_quota_state' => $state])->saveQuietly();
    }

    private function total(User $account, array $state, string $counter): int
    {
        return (int) $this->shops($account)->sum($counter) + (int) ($state['previous_shops'][$counter] ?? 0);
    }

    /** @return Builder<Shop> */
    private function shops(User $account): Builder
    {
        return Shop::withoutGlobalScopes()->where('user_id', $account->id);
    }
}
