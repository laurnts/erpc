<?php

declare(strict_types=1);

use App\Actions\Catalog\SuggestArticleListPrice;
use App\Actions\SupplierArticles\SetPreferredSupplier;
use App\Actions\SupplierPortal\UpdateSupplierArticleOffer;
use App\Data\TeamErpSettings;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\SupplierArticle;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($this->user);
    Filament::setTenant($this->user->personalTeam());
    $this->team = $this->user->personalTeam();

    $this->team->forceFill([
        'erp_settings' => new TeamErpSettings(default_currency: 'USD', default_margin_percent: 20.0),
    ])->save();

    $this->usd = Currency::factory()->usd()->create();
    $this->eur = Currency::factory()->eur()->create();

    $this->article = Article::factory()->for($this->team)->create();
    $this->supplier = Company::factory()->supplier()->for($this->team)->create();
    $this->link = SupplierArticle::factory()->create([
        'article_id' => $this->article->getKey(),
        'supplier_id' => $this->supplier->getKey(),
    ]);
});

describe('Suggest price cost rungs', function (): void {
    it('uses the preferred supplier standing price over the last quoted price', function (): void {
        $this->link->update([
            'is_preferred' => true,
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
            'last_quoted_price' => '50.00',
            'last_quoted_currency_id' => $this->usd->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBe(125.0)
            ->and($result['notices'])->toBe([]);
    });

    it('falls back to the preferred supplier last quoted price', function (): void {
        $this->link->update([
            'is_preferred' => true,
            'last_quoted_price' => '80.00',
            'last_quoted_currency_id' => $this->usd->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBe(100.0);
    });

    it('converts a preferred supplier cost to the team default currency', function (): void {
        ExchangeRate::factory()->forDate(now()->subDay()->toDateString())->create([
            'team_id' => $this->team->getKey(),
            'from_currency_id' => $this->eur->getKey(),
            'to_currency_id' => $this->usd->getKey(),
            'rate' => 2.0,
        ]);

        $this->link->update([
            'is_preferred' => true,
            'supplier_price' => '40.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBe(100.0);
    });

    it('makes no suggestion and names the currency when the preferred rate is missing', function (): void {
        $this->link->update([
            'is_preferred' => true,
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBeNull()
            ->and($result['notices'][0])->toContain('EUR');
    });

    it('uses the lowest converted active supplier price when there is no preferred supplier', function (): void {
        ExchangeRate::factory()->forDate(now()->subDay()->toDateString())->create([
            'team_id' => $this->team->getKey(),
            'from_currency_id' => $this->eur->getKey(),
            'to_currency_id' => $this->usd->getKey(),
            'rate' => 1.5,
        ]);

        $this->link->update([
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $otherSupplier = Company::factory()->supplier()->for($this->team)->create();
        SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $otherSupplier->getKey(),
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBe(93.75)
            ->and($result['notices'])->toBe([]);
    });

    it('skips unconvertible candidates with a notice listing the skipped suppliers', function (): void {
        $this->link->update([
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $skippedSupplier = Company::factory()->supplier()->for($this->team)->create(['name' => 'Unconvertible GmbH']);
        SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $skippedSupplier->getKey(),
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBe(125.0)
            ->and($result['notices'][0])->toContain('Unconvertible GmbH')
            ->and($result['notices'][0])->toContain('EUR');
    });

    it('aborts when no candidate on the lowest-price rung converts', function (): void {
        $this->link->update([
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBeNull()
            ->and($result['notices'][0])->toContain('EUR');
    });

    it('makes no suggestion when no supplier cost exists', function (): void {
        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBeNull()
            ->and($result['notices'][0])->toContain('No supplier cost');
    });

    it('ignores inactive supplier links', function (): void {
        $this->link->update([
            'is_active' => false,
            'supplier_price' => '100.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $result = app(SuggestArticleListPrice::class)->execute($this->article, $this->team);

        expect($result['price'])->toBeNull()
            ->and($result['notices'][0])->toContain('No supplier cost');
    });
});

describe('List price publish', function (): void {
    it('stamps list_price_updated_at and clears the review flag on save', function (): void {
        $this->article->forceFill(['price_review_needed' => true])->saveQuietly();

        $this->article->refresh()->update(['list_price' => '150.0000']);

        $this->article->refresh();

        expect($this->article->list_price_updated_at)->not->toBeNull()
            ->and($this->article->price_review_needed)->toBeFalse();
    });

    it('does not stamp the publish timestamp on unrelated saves', function (): void {
        $this->article->update(['name' => 'Renamed article']);

        expect($this->article->refresh()->list_price_updated_at)->toBeNull();
    });
});

describe('Review flag recompute', function (): void {
    it('flags the article when a supplier price write pushes margin below the default', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update(['is_preferred' => true]);

        app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '90.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        expect($this->article->refresh()->price_review_needed)->toBeTrue();
    });

    it('leaves the flag unset when the margin stays adequate', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update(['is_preferred' => true]);

        app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        expect($this->article->refresh()->price_review_needed)->toBeFalse();
    });

    it('clears a stale flag when the margin becomes adequate again', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->article->forceFill(['price_review_needed' => true])->saveQuietly();
        $this->link->update(['is_preferred' => true]);

        app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        expect($this->article->refresh()->price_review_needed)->toBeFalse();
    });

    it('flags the article when the best cost becomes unconvertible', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update(['is_preferred' => true]);

        app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '40.0000',
            'supplier_price_currency_id' => $this->eur->getKey(),
        ]);

        expect($this->article->refresh()->price_review_needed)->toBeTrue();
    });

    it('does not flag an article without a published list price', function (): void {
        $this->link->update(['is_preferred' => true]);

        app(UpdateSupplierArticleOffer::class)->execute($this->link, [
            'supplier_price' => '90.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        expect($this->article->refresh()->price_review_needed)->toBeFalse();
    });

    it('recomputes the flag when the preferred supplier changes', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update([
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $expensiveSupplier = Company::factory()->supplier()->for($this->team)->create();
        SupplierArticle::factory()->create([
            'article_id' => $this->article->getKey(),
            'supplier_id' => $expensiveSupplier->getKey(),
            'supplier_price' => '95.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        app(SetPreferredSupplier::class)->execute($this->article->getKey(), $this->supplier->getKey());
        expect($this->article->refresh()->price_review_needed)->toBeFalse();

        app(SetPreferredSupplier::class)->execute($this->article->getKey(), $expensiveSupplier->getKey());
        expect($this->article->refresh()->price_review_needed)->toBeTrue();
    });
});

describe('Daily refresh command', function (): void {
    it('flags published articles whose margin drifted below the default', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update([
            'is_preferred' => true,
            'supplier_price' => '90.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $this->article->forceFill(['price_review_needed' => false])->saveQuietly();

        $this->artisan('articles:refresh-price-review')->assertSuccessful();

        expect($this->article->refresh()->price_review_needed)->toBeTrue();
    });

    it('clears stale flags for articles with an adequate margin', function (): void {
        $this->article->update(['list_price' => '100.0000']);
        $this->link->update([
            'is_preferred' => true,
            'supplier_price' => '50.0000',
            'supplier_price_currency_id' => $this->usd->getKey(),
        ]);

        $this->article->forceFill(['price_review_needed' => true])->saveQuietly();

        $this->artisan('articles:refresh-price-review')->assertSuccessful();

        expect($this->article->refresh()->price_review_needed)->toBeFalse();
    });
});
