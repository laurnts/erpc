<?php

declare(strict_types=1);

use App\Models\Company;

use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\TaxCalculationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
});

// Model Tests
test('tax code can be created via factory', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'code' => 'VAT20',
        'name' => 'VAT 20%',
        'rate' => 20.00,
        'creator_id' => $this->user->id,
    ]);

    expect($taxCode)->toBeInstanceOf(TaxCode::class)
        ->and($taxCode->code)->toBe('VAT20')
        ->and($taxCode->name)->toBe('VAT 20%')
        ->and((float) $taxCode->rate)->toBe(20.00)
        ->and($taxCode->team_id)->toBe($this->user->personalTeam()->id)
        ->and($taxCode->creator_id)->toBe($this->user->id);
});

test('tax code belongs to team', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create();

    expect($taxCode->team)->toBeInstanceOf(Team::class)
        ->and($taxCode->team->id)->toBe($this->user->personalTeam()->id);
});

test('tax code belongs to creator', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'creator_id' => $this->user->id,
    ]);

    expect($taxCode->creator)->toBeInstanceOf(User::class)
        ->and($taxCode->creator->id)->toBe($this->user->id);
});

test('tax code has default values', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'is_inclusive_default' => false,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    expect($taxCode->is_inclusive_default)->toBeFalse()
        ->and($taxCode->is_active)->toBeTrue()
        ->and($taxCode->is_default)->toBeFalse()
        ->and($taxCode->sort_order)->toBe(0);
});

test('tax code code is unique per team', function () {
    TaxCode::factory()->for($this->user->personalTeam())->create(['code' => 'UNIQUE']);

    expect(fn () => TaxCode::factory()->for($this->user->personalTeam())->create(['code' => 'UNIQUE']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same tax code code can exist in different teams', function () {
    $user2 = User::factory()->withPersonalTeam()->create();

    $taxCode1 = TaxCode::factory()->for($this->user->personalTeam())->create(['code' => 'SHARED']);
    $taxCode2 = TaxCode::factory()->for($user2->personalTeam())->create(['code' => 'SHARED']);

    expect($taxCode1->id)->not->toBe($taxCode2->id)
        ->and($taxCode1->code)->toBe($taxCode2->code)
        ->and($taxCode1->team_id)->not->toBe($taxCode2->team_id);
});

test('tax code can be deactivated', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create(['is_active' => true]);

    $taxCode->update(['is_active' => false]);

    expect($taxCode->fresh()->is_active)->toBeFalse();
});

test('tax code factory creates valid tax code', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create();

    expect($taxCode->code)->not->toBeEmpty()
        ->and($taxCode->name)->not->toBeEmpty()
        ->and($taxCode->team_id)->not->toBeNull()
        ->and($taxCode->team_id)->toBe($this->user->personalTeam()->id);
});

test('inactive factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->inactive()->create();

    expect($taxCode->is_active)->toBeFalse();
});

test('default factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->default()->create();

    expect($taxCode->is_default)->toBeTrue();
});

test('inclusive factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->inclusive()->create();

    expect($taxCode->is_inclusive_default)->toBeTrue();
});

test('ppn11 factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->ppn11()->create();

    expect($taxCode->code)->toBe('PPN11')
        ->and($taxCode->name)->toBe('PPN 11%')
        ->and((float) $taxCode->rate)->toBe(11.00)
        ->and($taxCode->is_default)->toBeTrue();
});

test('ppn0 factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->ppn0()->create();

    expect($taxCode->code)->toBe('PPN0')
        ->and($taxCode->name)->toBe('PPN 0%')
        ->and((float) $taxCode->rate)->toBe(0.00);
});

test('tax exempt factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->taxExempt()->create();

    expect($taxCode->code)->toBe('EXEMPT')
        ->and($taxCode->name)->toBe('Tax Exempt')
        ->and((float) $taxCode->rate)->toBe(0.00);
});

test('no tax factory state works', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->noTax()->create();

    expect($taxCode->code)->toBe('NOTAX')
        ->and($taxCode->name)->toBe('No Tax')
        ->and((float) $taxCode->rate)->toBe(0.00);
});

test('tax code observer sets team and creator on create', function () {
    $taxCode = TaxCode::create([
        'code' => 'OBSERVER_TEST',
        'name' => 'Observer Test',
        'rate' => 5.00,
        'team_id' => $this->user->personalTeam()->id,
        'creator_id' => $this->user->id,
    ]);

    expect($taxCode->team_id)->toBe($this->user->personalTeam()->id)
        ->and($taxCode->creator_id)->toBe($this->user->id);
});

test('tax code display name includes rate', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'name' => 'VAT',
        'rate' => 20.00,
    ]);

    expect($taxCode->display_name)->toBe('VAT (20.00%)');
});

test('only one default tax code per team observer logic', function () {
    // Create first tax code with is_default = true
    $taxCode1 = TaxCode::factory()->for($this->user->personalTeam())->create([
        'code' => 'TAX1',
        'is_default' => true,
    ]);

    // Create second tax code with is_default = true
    $taxCode2 = TaxCode::factory()->for($this->user->personalTeam())->create([
        'code' => 'TAX2',
        'is_default' => true,
    ]);

    // Manually trigger the observer's saved event for taxCode2
    $observer = new \App\Observers\TaxCodeObserver;
    $observer->saved($taxCode2);

    // After the observer runs, taxCode1 should no longer be default
    expect($taxCode1->fresh()->is_default)->toBeFalse()
        ->and($taxCode2->fresh()->is_default)->toBeTrue();
});

// TaxCalculationService Tests
test('calculate tax amount exclusive', function () {
    $service = new TaxCalculationService;

    // 100 * 11% = 11
    expect($service->calculateTaxAmount(100, 11, false))->toBe(11.0);

    // 200 * 20% = 40
    expect($service->calculateTaxAmount(200, 20, false))->toBe(40.0);

    // 0% tax should return 0
    expect($service->calculateTaxAmount(100, 0, false))->toBe(0.0);
});

test('calculate tax amount inclusive', function () {
    $service = new TaxCalculationService;

    // 111 inclusive at 11% => tax = 111 - (111 / 1.11) = 111 - 100 = 11
    $tax = $service->calculateTaxAmount(111, 11, true);
    expect(round($tax, 2))->toBe(11.0);

    // 120 inclusive at 20% => tax = 120 - (120 / 1.20) = 120 - 100 = 20
    $tax = $service->calculateTaxAmount(120, 20, true);
    expect(round($tax, 2))->toBe(20.0);
});

test('calculate price with tax', function () {
    $service = new TaxCalculationService;

    // 100 + 11% = 111
    expect(round($service->calculatePriceWithTax(100, 11), 2))->toBe(111.0);

    // 100 + 20% = 120
    expect(round($service->calculatePriceWithTax(100, 20), 2))->toBe(120.0);

    // 100 + 0% = 100
    expect(round($service->calculatePriceWithTax(100, 0), 2))->toBe(100.0);
});

test('calculate price without tax', function () {
    $service = new TaxCalculationService;

    // 111 / 1.11 = 100
    expect(round($service->calculatePriceWithoutTax(111, 11), 2))->toBe(100.0);

    // 120 / 1.20 = 100
    expect(round($service->calculatePriceWithoutTax(120, 20), 2))->toBe(100.0);

    // 100 with 0% = 100
    expect(round($service->calculatePriceWithoutTax(100, 0), 2))->toBe(100.0);
});

test('calculate tax amount from tax code model', function () {
    $taxCode = TaxCode::factory()->for($this->user->personalTeam())->create([
        'rate' => 11.00,
        'is_inclusive_default' => false,
    ]);

    $service = new TaxCalculationService;

    // Should use tax code's default inclusivity (false)
    expect($service->calculateTaxAmountFromCode(100, $taxCode))->toBe(11.0);

    // Can override inclusivity
    $tax = $service->calculateTaxAmountFromCode(111, $taxCode, true);
    expect(round($tax, 2))->toBe(11.0);
});

test('calculate line total exclusive', function () {
    $service = new TaxCalculationService;

    // 10 units * 100 each = 1000 subtotal, 11% tax = 110, total = 1110
    $result = $service->calculateLineTotal(10, 100, 11, false);

    expect($result['subtotal'])->toBe(1000.0)
        ->and($result['tax_amount'])->toBe(110.0)
        ->and($result['total'])->toBe(1110.0);
});

test('calculate line total inclusive', function () {
    $service = new TaxCalculationService;

    // 10 units * 111 each (inclusive) = 1110 total
    // Subtotal = 1110 / 1.11 = 1000, tax = 110
    $result = $service->calculateLineTotal(10, 111, 11, true);

    expect($result['subtotal'])->toBe(1000.0)
        ->and($result['tax_amount'])->toBe(110.0)
        ->and($result['total'])->toBe(1110.0);
});

test('round money correctly', function () {
    $service = new TaxCalculationService;

    expect($service->roundMoney(100.555))->toBe(100.56)
        ->and($service->roundMoney(100.554))->toBe(100.55)
        ->and($service->roundMoney(100.5555, 3))->toBe(100.556);
});

test('negative tax rate returns zero', function () {
    $service = new TaxCalculationService;

    expect($service->calculateTaxAmount(100, -5, false))->toBe(0.0)
        ->and($service->calculatePriceWithTax(100, -5))->toBe(100.0)
        ->and($service->calculatePriceWithoutTax(100, -5))->toBe(100.0);
});
