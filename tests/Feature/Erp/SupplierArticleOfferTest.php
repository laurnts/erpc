<?php

declare(strict_types=1);

use App\Actions\Erp\GenerateSupplierQuotesForRequest;
use App\Actions\SupplierArticles\SetPreferredSupplier;
use App\Actions\SupplierPortal\UpdateSupplierArticleOffer;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierArticle;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->article = Article::factory()->for($this->team)->create();
    $this->supplier = Company::factory()->supplier()->for($this->team)->create();
    $this->link = SupplierArticle::factory()->create([
        'article_id' => $this->article->getKey(),
        'supplier_id' => $this->supplier->getKey(),
    ]);
});

describe('Offer timestamp stamping', function (): void {
    it('stamps supplier_price_updated_at when the price changes', function (): void {
        expect($this->link->supplier_price_updated_at)->toBeNull();

        $this->link->update(['supplier_price' => '150.5000']);

        expect($this->link->refresh()->supplier_price_updated_at)->not->toBeNull()
            ->and($this->link->quantity_updated_at)->toBeNull();
    });

    it('stamps quantity_updated_at when the quantity changes', function (): void {
        $this->link->update(['available_quantity' => '25.0000']);

        expect($this->link->refresh()->quantity_updated_at)->not->toBeNull()
            ->and($this->link->supplier_price_updated_at)->toBeNull();
    });

    it('does not stamp offer timestamps for unrelated writes', function (): void {
        $this->link->update(['notes' => 'unrelated']);

        expect($this->link->refresh()->supplier_price_updated_at)->toBeNull()
            ->and($this->link->quantity_updated_at)->toBeNull();
    });
});

describe('UpdateSupplierArticleOffer whitelist', function (): void {
    it('updates only supplier-writable fields and ignores the rest', function (): void {
        $currency = Currency::factory()->create();

        $updated = app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '99.9900',
            'supplier_price_currency_id' => $currency->getKey(),
            'available_quantity' => '10.0000',
            'lead_time_days' => 14,
            'is_preferred' => true,
            'is_active' => false,
            'last_quoted_price' => '1.00',
            'supplier_sku' => 'TAMPERED',
            'supplier_id' => 999999,
        ]);

        expect($updated->supplier_price)->toBe('99.9900')
            ->and($updated->supplier_price_currency_id)->toBe($currency->getKey())
            ->and($updated->available_quantity)->toBe('10.0000')
            ->and($updated->lead_time_days)->toBe(14)
            ->and($updated->is_preferred)->toBeFalse()
            ->and($updated->is_active)->toBeTrue()
            ->and($updated->last_quoted_price)->toBeNull()
            ->and($updated->supplier_sku)->not->toBe('TAMPERED')
            ->and($updated->supplier_id)->toBe($this->supplier->getKey());
    });
});

describe('Preferred supplier enforcement', function (): void {
    it('demotes the previous preferred supplier when a new one is set', function (): void {
        $secondSupplier = Company::factory()->supplier()->for($this->team)->create();
        $secondLink = SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $secondSupplier->getKey(),
        ]);

        app(SetPreferredSupplier::class)->execute($this->article->getKey(), $this->supplier->getKey());
        app(SetPreferredSupplier::class)->execute($this->article->getKey(), $secondSupplier->getKey());

        expect($this->link->refresh()->is_preferred)->toBeFalse()
            ->and($secondLink->refresh()->is_preferred)->toBeTrue();
    });

    it('rejects a second preferred supplier at the database level', function (): void {
        $this->link->update(['is_preferred' => true]);

        $secondSupplier = Company::factory()->supplier()->for($this->team)->create();

        expect(fn () => SupplierArticle::factory()->preferred()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $secondSupplier->getKey(),
        ]))->toThrow(QueryException::class);
    });

    it('allows one preferred supplier per article across articles', function (): void {
        $otherArticle = Article::factory()->for($this->team)->create();

        $this->link->update(['is_preferred' => true]);

        $otherLink = SupplierArticle::factory()->preferred()->create([
            'article_id' => $otherArticle->getKey(),
            'supplier_id' => $this->supplier->getKey(),
        ]);

        expect($otherLink->is_preferred)->toBeTrue();
    });
});

describe('RFQ prefill from supplier offer', function (): void {
    function makeMatchedRequest(object $context): Request
    {
        $request = Request::factory()->for($context->team)->create([
            'buyer_id' => Company::factory()->buyer()->for($context->team)->create()->getKey(),
        ]);

        RequestItem::factory()->recycle($request)->create([
            'article_id' => $context->article->getKey(),
            'is_matched' => true,
        ]);

        return $request;
    }

    it('prefills the unit price from supplier_price when its currency matches the quote currency', function (): void {
        $currency = Currency::factory()->create();
        $this->supplier->update(['default_currency_id' => $currency->getKey()]);
        $this->link->update([
            'supplier_price' => '123.4500',
            'supplier_price_currency_id' => $currency->getKey(),
            'last_quoted_price' => '99.99',
            'last_quoted_currency_id' => $currency->getKey(),
        ]);

        $quotes = app(GenerateSupplierQuotesForRequest::class)->execute(makeMatchedRequest($this));

        expect($quotes)->toHaveCount(1)
            ->and($quotes->first()->items()->first()->unit_price)->toBe('123.4500');
    });

    it('falls back to last_quoted_price when no standing price exists for the quote currency', function (): void {
        $currency = Currency::factory()->create();
        $this->supplier->update(['default_currency_id' => $currency->getKey()]);
        $this->link->update([
            'last_quoted_price' => '55.50',
            'last_quoted_currency_id' => $currency->getKey(),
        ]);

        $quotes = app(GenerateSupplierQuotesForRequest::class)->execute(makeMatchedRequest($this));

        expect($quotes->first()->items()->first()->unit_price)->toBe('55.5000');
    });

    it('never copies a price across currencies', function (): void {
        $quoteCurrency = Currency::factory()->create();
        $otherCurrency = Currency::factory()->create();
        $this->supplier->update(['default_currency_id' => $quoteCurrency->getKey()]);
        $this->link->update([
            'supplier_price' => '123.4500',
            'supplier_price_currency_id' => $otherCurrency->getKey(),
            'last_quoted_price' => '99.99',
            'last_quoted_currency_id' => $otherCurrency->getKey(),
        ]);

        $quotes = app(GenerateSupplierQuotesForRequest::class)->execute(makeMatchedRequest($this));

        expect($quotes->first()->items()->first()->unit_price)->toBe('0.0000');
    });
});
