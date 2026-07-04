<?php

declare(strict_types=1);

use App\Enums\PNLStatus;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->actingAs($this->user);
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->currency = Currency::factory()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $this->requestItem = RequestItem::factory()->recycle($this->request)->create(['parent_id' => null]);

    $this->quote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->forRequest($this->request)
        ->withCurrency($this->currency)
        ->create();

    // Net sell 1,000, + Tax 10% => gross 1,100; cost 600 => net margin 400.
    $this->item = BuyerQuoteItem::factory()->forBuyerQuote($this->quote)->create([
        'request_item_id' => $this->requestItem->getKey(),
        'quantity' => '1', 'unit_price' => '1000', 'cost_price' => '600',
        'tax_rate' => '10', 'is_tax_inclusive' => true,
    ]);
    $this->quote->recalculateTotals();
});

it('has no snapshot before approval', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();

    expect($pnl->financial_snapshot)->toBeNull()
        ->and($pnl->financialSnapshotData())->toBeNull();
});

it('freezes the financial figures when the PNL is approved', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();

    $pnl->update(['status' => PNLStatus::APPROVED]);

    $snapshot = $pnl->fresh()->financialSnapshotData();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->subtotal)->toBe(1000.0)       // net revenue
        ->and($snapshot->costTotal)->toBe(600.0)
        ->and($snapshot->marginAmount)->toBe(400.0)     // net sell - cost (VAT excluded)
        ->and($snapshot->grandTotal)->toBe(1100.0)
        ->and($snapshot->buyerQuoteId)->toBe($this->quote->getKey());
});

it('does not change the approved value when the source quote is later revised', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();
    $pnl->update(['status' => PNLStatus::APPROVED]);

    // Revise the quote after approval.
    $this->item->update(['unit_price' => '5000']);
    $this->quote->recalculateTotals();

    $snapshot = $pnl->fresh()->financialSnapshotData();

    // The approved figures are frozen — unaffected by the revision.
    expect($snapshot->subtotal)->toBe(1000.0)
        ->and($snapshot->marginAmount)->toBe(400.0)
        ->and($snapshot->grandTotal)->toBe(1100.0);
});

it('freezes the line items in the snapshot at approval', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();
    $pnl->update(['status' => PNLStatus::APPROVED]);

    // Revise the quote after approval.
    $this->item->update(['unit_price' => '5000']);
    $this->quote->recalculateTotals();

    $snapshot = $pnl->fresh()->financialSnapshotData();

    expect($snapshot->supplierGroups)->toHaveCount(1);

    $group = $snapshot->supplierGroups[0];

    expect((float) $group['netSell'])->toBe(1000.0)
        ->and((float) $group['costTotal'])->toBe(600.0)
        ->and($group['lines'])->toHaveCount(1)
        ->and((float) $group['lines'][0]['sellPrice'])->toBe(1000.0)
        ->and((float) $group['lines'][0]['costPrice'])->toBe(600.0);
});

it('renders the approved PDF from the frozen snapshot, not the live quote', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();
    $pnl->update(['status' => PNLStatus::APPROVED]);

    // Revise the quote after approval.
    $this->item->update(['unit_price' => '5000']);
    $this->quote->recalculateTotals();

    $html = view('pdf.profit-and-loss', [
        'pnl' => $pnl->fresh()->load(['request', 'buyerQuote.currency', 'team']),
        'company' => ['name' => 'Test Co', 'address' => '', 'phone' => '', 'email' => ''],
    ])->render();

    // Line rows, supplier subtotals, and the grand total all show approved figures.
    expect($html)->toContain('1,000.00')
        ->and($html)->not->toContain('5,000.00');
});

it('clears the snapshot when a new quote version resets the PNL', function (): void {
    $pnl = ProfitAndLoss::factory()->forBuyerQuote($this->quote)->create();
    $pnl->update(['status' => PNLStatus::APPROVED]);
    expect($pnl->fresh()->financial_snapshot)->not->toBeNull();

    // A new quote version resets approved PNLs so they must be re-approved.
    $this->quote->createNewVersion();

    $fresh = $pnl->fresh();
    expect($fresh->status)->toBe(PNLStatus::NEED_APPROVAL)
        ->and($fresh->financial_snapshot)->toBeNull();
});
