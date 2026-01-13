<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Team;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

final readonly class CurrencyService
{
    private const int CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * Convert an amount from one currency to another.
     *
     * @param  float|int  $amount  The amount to convert
     * @param  Currency|int|string  $fromCurrency  The source currency (model, ID, or code)
     * @param  Currency|int|string  $toCurrency  The target currency (model, ID, or code)
     * @param  DateTimeInterface|string|null  $date  The date for the exchange rate (null = latest)
     * @param  Team|int|null  $team  The team context (null = current team)
     * @return float|null The converted amount, or null if no rate is found
     */
    public function convert(
        float|int $amount,
        Currency|int|string $fromCurrency,
        Currency|int|string $toCurrency,
        DateTimeInterface|string|null $date = null,
        Team|int|null $team = null
    ): ?float {
        $rate = $this->getRate($fromCurrency, $toCurrency, $date, $team);

        if ($rate === null) {
            return null;
        }

        return (float) $amount * $rate;
    }

    /**
     * Get the exchange rate between two currencies.
     *
     * @param  Currency|int|string  $fromCurrency  The source currency (model, ID, or code)
     * @param  Currency|int|string  $toCurrency  The target currency (model, ID, or code)
     * @param  DateTimeInterface|string|null  $date  The date for the exchange rate (null = latest on or before today)
     * @param  Team|int|null  $team  The team context (null = current team)
     * @return float|null The exchange rate, or null if not found
     */
    public function getRate(
        Currency|int|string $fromCurrency,
        Currency|int|string $toCurrency,
        DateTimeInterface|string|null $date = null,
        Team|int|null $team = null
    ): ?float {
        $fromCurrencyId = $this->resolveCurrencyId($fromCurrency);
        $toCurrencyId = $this->resolveCurrencyId($toCurrency);

        if ($fromCurrencyId === null || $toCurrencyId === null) {
            return null;
        }

        // Same currency, rate is 1
        if ($fromCurrencyId === $toCurrencyId) {
            return 1.0;
        }

        $teamId = $this->resolveTeamId($team);

        if ($teamId === null) {
            return null;
        }

        $effectiveDate = $this->resolveDate($date);
        $cacheKey = "exchange_rate:{$teamId}:{$fromCurrencyId}:{$toCurrencyId}:{$effectiveDate}";

        $cachedRate = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($teamId, $fromCurrencyId, $toCurrencyId, $effectiveDate): ?float {
            // Try to find direct rate
            $directRate = $this->findDirectRate($teamId, $fromCurrencyId, $toCurrencyId, $effectiveDate);

            if ($directRate !== null) {
                return $directRate;
            }

            // Try to find inverse rate
            $inverseRate = $this->findDirectRate($teamId, $toCurrencyId, $fromCurrencyId, $effectiveDate);

            if ($inverseRate !== null && $inverseRate > 0) {
                return 1.0 / $inverseRate;
            }

            return null;
        });

        // Ensure we return a float (cache may return string)
        return $cachedRate !== null ? (float) $cachedRate : null;
    }

    /**
     * Get all available rates for a given date.
     *
     * @param  DateTimeInterface|string|null  $date  The date for the exchange rate (null = today)
     * @param  Team|int|null  $team  The team context (null = current team)
     * @return array<int, ExchangeRate>
     */
    public function getRatesForDate(
        DateTimeInterface|string|null $date = null,
        Team|int|null $team = null
    ): array {
        $teamId = $this->resolveTeamId($team);

        if ($teamId === null) {
            return [];
        }

        $effectiveDate = $this->resolveDate($date);

        return ExchangeRate::query()
            ->where('team_id', $teamId)
            ->where('effective_date', '<=', $effectiveDate)
            ->with(['fromCurrency', 'toCurrency'])
            ->orderBy('effective_date', 'desc')
            ->get()
            ->unique(fn (ExchangeRate $rate): string => "{$rate->from_currency_id}:{$rate->to_currency_id}")
            ->values()
            ->all();
    }

    /**
     * Clear the cache for a specific team's exchange rates.
     */
    public function clearCache(Team|int|null $team = null): void
    {
        $teamId = $this->resolveTeamId($team);

        if ($teamId !== null) {
            Cache::forget("exchange_rates_team:{$teamId}");
        }
    }

    /**
     * Find the direct exchange rate.
     */
    private function findDirectRate(int $teamId, int $fromCurrencyId, int $toCurrencyId, string $effectiveDate): ?float
    {
        $rate = ExchangeRate::query()
            ->where('team_id', $teamId)
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', '<=', $effectiveDate)
            ->orderBy('effective_date', 'desc')
            ->first();

        return $rate !== null ? (float) $rate->rate : null;
    }

    /**
     * Resolve a currency to its ID.
     */
    private function resolveCurrencyId(Currency|int|string $currency): ?int
    {
        if ($currency instanceof Currency) {
            return $currency->id;
        }

        if (is_int($currency)) {
            return $currency;
        }

        // Assume it's a currency code
        $currencyModel = Currency::query()->where('code', strtoupper($currency))->first();

        return $currencyModel?->id;
    }

    /**
     * Resolve a team to its ID.
     */
    private function resolveTeamId(Team|int|null $team): ?int
    {
        if ($team instanceof Team) {
            return $team->id;
        }

        if (is_int($team)) {
            return $team;
        }

        // Try to get from current user
        $user = auth()->user();

        /** @var \App\Models\User|null $user */
        return $user?->currentTeam?->id;
    }

    /**
     * Resolve a date to a string format.
     */
    private function resolveDate(DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return now()->toDateString();
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date;
    }
}
