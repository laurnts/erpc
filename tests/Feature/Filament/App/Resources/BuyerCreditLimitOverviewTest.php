<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages\ListBuyerCreditLimits;
use App\Models\BuyerOrder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    actingAs($this->user);
    $this->team = $this->user->personalTeam();
    Filament::setTenant($this->team);

    $this->currency = Currency::factory()->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create([
        'credit_status' => true,
        'credit_limit' => 100000,
        'credit_used' => 999999,
    ]);
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
});

it('shows derived exposure, not the stale stored counter', function (): void {
    BuyerOrder::factory()
        ->recycle($this->team)->recycle($this->currency)
        ->for($this->buyer, 'buyer')->for($this->request)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'total' => 7500,
            'credit_released' => 0,
            'credit_reserved_at' => now(),
        ]);

    livewire(ListBuyerCreditLimits::class)
        ->assertOk()
        ->assertTableColumnStateSet('credit_exposure', 7500.0, $this->buyer->fresh());
});

it('sorts by exposure without a SQL error', function (): void {
    livewire(ListBuyerCreditLimits::class)
        ->sortTable('credit_exposure')
        ->assertOk();
});

it('shows derived available credit, not the stale stored counter', function (): void {
    BuyerOrder::factory()
        ->recycle($this->team)->recycle($this->currency)
        ->for($this->buyer, 'buyer')->for($this->request)
        ->create([
            'status' => OrderStatus::CONFIRMED,
            'total' => 7500,
            'credit_released' => 0,
            'credit_reserved_at' => now(),
        ]);

    livewire(ListBuyerCreditLimits::class)
        ->assertOk()
        ->assertTableColumnStateSet('derived_available_credit', 92500.0, $this->buyer->fresh());
});

it('sorts by available credit without a SQL error', function (): void {
    livewire(ListBuyerCreditLimits::class)
        ->sortTable('derived_available_credit')
        ->assertOk();
});
