<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RequestStage;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Company;
use App\Models\Request;
use App\Models\Team;
use App\Services\BuyerPortal\BuyerInvoiceStatusPresenter;
use App\Services\BuyerPortal\BuyerRequestStagePresenter;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->presenter = app(BuyerRequestStagePresenter::class);
});

it('maps sent invoice status to received for the buyer portal', function (): void {
    $presenter = app(BuyerInvoiceStatusPresenter::class);

    expect($presenter->label(InvoiceStatus::SENT))->toBe('Received')
        ->and($presenter->label(InvoiceStatus::PAID))->toBe('Paid');
});

it('treats a stale awaiting confirmation stage as being processed once the buyer order is confirmed', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->accepted()
        ->create();

    BuyerOrder::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->confirmed()
        ->create();

    BuyerInvoice::factory()
        ->for($this->team)
        ->for($request)
        ->sent()
        ->create();

    expect($this->presenter->effectiveStage($request))->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
});

it('still shows awaiting confirmation when a sent quote has not been accepted', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->sent()
        ->create();

    expect($this->presenter->effectiveStage($request))->toBe(RequestStage::AWAITING_BUYER_CONFIRMATION);
});

it('promotes preparing buyer quote to awaiting confirmation when a quote was sent', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::PREPARING_BUYER_QUOTE,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->sent()
        ->create();

    expect($this->presenter->effectiveStage($request))->toBe(RequestStage::AWAITING_BUYER_CONFIRMATION);
});

it('treats accepted quote without a confirmed order as post-confirmation', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->state(['status' => BuyerQuoteStatus::ACCEPTED])
        ->create();

    expect($this->presenter->effectiveStage($request))->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
});

it('does not demote a request whose internal stage already moved past confirmation', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::GOODS_RECEIVE,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->accepted()
        ->create();

    expect($this->presenter->effectiveStage($request))->toBe(RequestStage::GOODS_RECEIVE);
});

it('applies the effective stage to the supplier timeline as well', function (): void {
    $request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create([
        'stage' => RequestStage::AWAITING_BUYER_CONFIRMATION,
    ]);

    BuyerQuote::factory()
        ->for($this->team)
        ->for($request)
        ->for($this->buyer, 'buyer')
        ->accepted()
        ->create();

    $timeline = app(\App\Services\SupplierPortal\SupplierRequestStagePresenter::class)->timeline($request);
    $current = collect($timeline)->firstWhere('current', true);

    expect($current['stage'])->toBe(RequestStage::PREPARING_SUPPLIER_ORDER);
});
