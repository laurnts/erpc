<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\User;
use App\Services\Currency\CurrencyService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    Cache::flush(); // Clear cache to avoid stale exchange rate cache
});

// Currency Model Tests
test('currency can be created via factory', function () {
    $currency = Currency::factory()->create([
        'code' => 'TST',
        'name' => 'Test Currency',
        'symbol' => 'T$',
        'decimal_places' => 2,
    ]);

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->code)->toBe('TST')
        ->and($currency->name)->toBe('Test Currency')
        ->and($currency->symbol)->toBe('T$')
        ->and($currency->decimal_places)->toBe(2);
});

test('currency has default values', function () {
    $currency = Currency::factory()->create([
        'code' => 'DEF',
    ]);

    expect($currency->decimal_places)->toBe(2)
        ->and($currency->is_active)->toBeTrue()
        ->and($currency->is_default)->toBeFalse();
});

test('currency code must be unique', function () {
    Currency::factory()->create(['code' => 'UNQ']);

    expect(fn () => Currency::factory()->create(['code' => 'UNQ']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('currency can format amount', function () {
    $currency = Currency::factory()->create([
        'code' => 'USD',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);

    expect($currency->format(1234.567))->toBe('$1,234.57');
});

test('currency factory creates usd currency', function () {
    $currency = Currency::factory()->usd()->create();

    expect($currency->code)->toBe('USD')
        ->and($currency->name)->toBe('US Dollar')
        ->and($currency->symbol)->toBe('$');
});

test('currency factory creates idr currency', function () {
    $currency = Currency::factory()->idr()->create();

    expect($currency->code)->toBe('IDR')
        ->and($currency->name)->toBe('Indonesian Rupiah')
        ->and($currency->decimal_places)->toBe(0);
});

test('currency can be inactive', function () {
    $currency = Currency::factory()->inactive()->create();

    expect($currency->is_active)->toBeFalse();
});

test('currency can be set as default', function () {
    $currency = Currency::factory()->default()->create();

    expect($currency->is_default)->toBeTrue();
});

// Exchange Rate Model Tests
test('exchange rate can be created via factory', function () {
    $usd = Currency::factory()->usd()->create();
    $idr = Currency::factory()->idr()->create();

    $exchangeRate = ExchangeRate::factory()
        ->for($this->user->personalTeam())
        ->create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $idr->id,
            'rate' => 15500.00,
            'effective_date' => now()->toDateString(),
            'creator_id' => $this->user->id,
        ]);

    expect($exchangeRate)->toBeInstanceOf(ExchangeRate::class)
        ->and($exchangeRate->from_currency_id)->toBe($usd->id)
        ->and($exchangeRate->to_currency_id)->toBe($idr->id)
        ->and((float) $exchangeRate->rate)->toBe(15500.00);
});

test('exchange rate belongs to team', function () {
    $exchangeRate = ExchangeRate::factory()
        ->for($this->user->personalTeam())
        ->create();

    expect($exchangeRate->team)->toBeInstanceOf(Team::class)
        ->and($exchangeRate->team->id)->toBe($this->user->personalTeam()->id);
});

test('exchange rate belongs to creator', function () {
    $exchangeRate = ExchangeRate::factory()
        ->for($this->user->personalTeam())
        ->create([
            'creator_id' => $this->user->id,
        ]);

    expect($exchangeRate->creator)->toBeInstanceOf(User::class)
        ->and($exchangeRate->creator->id)->toBe($this->user->id);
});

test('exchange rate has from and to currency relationships', function () {
    $usd = Currency::factory()->usd()->create();
    $idr = Currency::factory()->idr()->create();

    $exchangeRate = ExchangeRate::factory()
        ->for($this->user->personalTeam())
        ->create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $idr->id,
        ]);

    expect($exchangeRate->fromCurrency)->toBeInstanceOf(Currency::class)
        ->and($exchangeRate->fromCurrency->code)->toBe('USD')
        ->and($exchangeRate->toCurrency)->toBeInstanceOf(Currency::class)
        ->and($exchangeRate->toCurrency->code)->toBe('IDR');
});

test('exchange rate can convert amount', function () {
    $exchangeRate = ExchangeRate::factory()
        ->for($this->user->personalTeam())
        ->create(['rate' => 15500.00]);

    expect($exchangeRate->convert(100))->toBe(1550000.00);
});

test('exchange rate observer sets team and creator on create', function () {
    $usd = Currency::factory()->usd()->create();
    $idr = Currency::factory()->idr()->create();

    $exchangeRate = ExchangeRate::create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $idr->id,
        'rate' => 15500.00,
        'effective_date' => now()->toDateString(),
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($exchangeRate->team_id)->toBe($this->user->personalTeam()->id)
        ->and($exchangeRate->creator_id)->toBe($this->user->id);
});

// Currency Service Tests
test('currency service can convert amount', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $idr = Currency::query()->firstOrCreate(['code' => 'IDR'], [
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'decimal_places' => 0,
    ]);

    // Use yesterday's date to avoid any timezone issues
    $effectiveDate = now()->subDay()->toDateString();

    $rate = ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $idr->id,
        'rate' => 15500.00,
        'effective_date' => $effectiveDate,
    ]);

    // Debug: Check exchange rate was created
    expect($rate->exists)->toBeTrue()
        ->and($rate->team_id)->toBe($this->user->personalTeam()->id)
        ->and($rate->from_currency_id)->toBe($usd->id)
        ->and($rate->to_currency_id)->toBe($idr->id);

    $converted = $service->convert(100, $usd, $idr);

    expect($converted)->toBe(1550000.00);
});

test('currency service returns 1 for same currency conversion', function () {
    $service = app(CurrencyService::class);
    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);

    $rate = $service->getRate($usd, $usd);

    expect($rate)->toBe(1.0);
});

test('currency service can get rate by currency code', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $eur = Currency::query()->firstOrCreate(['code' => 'EUR'], [
        'name' => 'Euro',
        'symbol' => "\u{20AC}",
        'decimal_places' => 2,
    ]);

    // Use yesterday's date to avoid timezone issues
    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $eur->id,
        'rate' => 0.92,
        'effective_date' => now()->subDay()->toDateString(),
    ]);

    $rate = $service->getRate('USD', 'EUR');

    expect($rate)->toBe(0.92);
});

test('currency service returns null when no rate exists', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $gbp = Currency::query()->firstOrCreate(['code' => 'GBP'], [
        'name' => 'British Pound',
        'symbol' => "\u{00A3}",
        'decimal_places' => 2,
    ]);

    $rate = $service->getRate($usd, $gbp);

    expect($rate)->toBeNull();
});

test('currency service uses inverse rate when direct rate not available', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $eur = Currency::query()->firstOrCreate(['code' => 'EUR'], [
        'name' => 'Euro',
        'symbol' => "\u{20AC}",
        'decimal_places' => 2,
    ]);

    // Only create USD to EUR rate (use yesterday's date to avoid timezone issues)
    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $eur->id,
        'rate' => 0.92,
        'effective_date' => now()->subDay()->toDateString(),
    ]);

    // Request EUR to USD rate (inverse)
    $rate = $service->getRate($eur, $usd);

    expect($rate)->toBeGreaterThan(1.08)
        ->and($rate)->toBeLessThan(1.10);
});

test('currency service gets latest rate on or before date', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $jpy = Currency::query()->firstOrCreate(['code' => 'JPY'], [
        'name' => 'Japanese Yen',
        'symbol' => "\u{00A5}",
        'decimal_places' => 0,
    ]);

    // Create an old rate
    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $jpy->id,
        'rate' => 140.00,
        'effective_date' => now()->subDays(10)->toDateString(),
    ]);

    // Create a newer rate
    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $jpy->id,
        'rate' => 155.00,
        'effective_date' => now()->subDays(5)->toDateString(),
    ]);

    // Get rate for 7 days ago (should use the 10-day-old rate)
    $rate = $service->getRate($usd, $jpy, now()->subDays(7)->toDateString());

    expect($rate)->toBe(140.00);
});

test('currency service can get rates for date', function () {
    $service = app(CurrencyService::class);

    $usd = Currency::query()->firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
    ]);
    $aud = Currency::query()->firstOrCreate(['code' => 'AUD'], [
        'name' => 'Australian Dollar',
        'symbol' => 'A$',
        'decimal_places' => 2,
    ]);
    $cad = Currency::query()->firstOrCreate(['code' => 'CAD'], [
        'name' => 'Canadian Dollar',
        'symbol' => 'C$',
        'decimal_places' => 2,
    ]);

    // Use yesterday's date to avoid timezone issues
    $effectiveDate = now()->subDay()->toDateString();

    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $aud->id,
        'rate' => 1.54,
        'effective_date' => $effectiveDate,
    ]);

    ExchangeRate::query()->create([
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
        'from_currency_id' => $usd->id,
        'to_currency_id' => $cad->id,
        'rate' => 1.35,
        'effective_date' => $effectiveDate,
    ]);

    $rates = $service->getRatesForDate();

    expect($rates)->toHaveCount(2);
});

// Seeder Test
test('currency seeder creates default currencies', function () {
    $seeder = new \Database\Seeders\CurrencySeeder;
    $seeder->run();

    expect(Currency::query()->where('code', 'USD')->exists())->toBeTrue()
        ->and(Currency::query()->where('code', 'IDR')->exists())->toBeTrue()
        ->and(Currency::query()->where('code', 'EUR')->exists())->toBeTrue()
        ->and(Currency::query()->where('code', 'SGD')->exists())->toBeTrue()
        ->and(Currency::query()->where('code', 'CNY')->exists())->toBeTrue();
});

test('currency seeder sets usd as default', function () {
    $seeder = new \Database\Seeders\CurrencySeeder;
    $seeder->run();

    $usd = Currency::query()->where('code', 'USD')->first();

    expect($usd->is_default)->toBeTrue();
});
