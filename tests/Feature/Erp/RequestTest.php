<?php

declare(strict_types=1);

use App\Enums\RequestStage;
use App\Models\Article;
use App\Models\Company;
use App\Models\Project;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->actingAs($this->user);
});

describe('Request Model', function (): void {
    it('can create a request with required fields', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'title' => 'Test Request',
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request)->toBeInstanceOf(Request::class)
            ->and($request->title)->toBe('Test Request')
            ->and($request->stage)->toBe(RequestStage::DRAFT);
    });

    it('generates request number on creation', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

        expect($request->request_number)->toMatch('/^REQ-\d{4}-\d{4}$/');
    });

    it('increments request number past soft-deleted records', function (): void {
        $year = date('Y');
        $deletedNumber = sprintf('REQ-%s-0024', $year);
        $expectedNumber = sprintf('REQ-%s-0025', $year);

        $deletedRequest = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'request_number' => $deletedNumber,
        ]);
        $deletedRequest->delete();

        // The counter is a standalone row, never derived from the requests table, so
        // a hand-inserted (and now soft-deleted) 0024 is invisible to it until seeded.
        // Production seeds it once at cutover via `erp:backfill-document-sequences`,
        // which scans existing numbers — including soft-deleted rows, since it reads
        // the raw table — and advances the counter above them. Run the same command
        // here so the next allocation lands past the soft-deleted 0024 as intended.
        $this->artisan('erp:backfill-document-sequences')->assertSuccessful();

        $newRequest = Request::create([
            'team_id' => $this->team->getKey(),
            'buyer_id' => $this->buyer->getKey(),
            'title' => 'Next Request',
        ]);

        expect($newRequest->request_number)->toBe($expectedNumber);
    });

    it('defaults to draft stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

        expect($request->stage)->toBe(RequestStage::DRAFT);
    });

    it('belongs to a buyer', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

        expect($request->buyer)->toBeInstanceOf(Company::class)
            ->and($request->buyer->getKey())->toBe($this->buyer->getKey());
    });

    it('can belong to a project', function (): void {
        $project = Project::factory()->recycle($this->team)->create();
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'project_id' => $project->getKey(),
        ]);

        expect($request->project)->toBeInstanceOf(Project::class)
            ->and($request->project->getKey())->toBe($project->getKey());
    });

    it('has many items', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
        RequestItem::factory()->count(3)->recycle($request)->create();

        expect($request->items)->toHaveCount(3);
    });

    it('has many supplier invoices', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
        $otherRequest = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
        $invoices = SupplierInvoice::factory()->count(2)->recycle($this->team)->recycle($request)->create();
        SupplierInvoice::factory()->recycle($this->team)->recycle($otherRequest)->create();

        expect($request->supplierInvoices)->toHaveCount(2)
            ->and($request->supplierInvoices->pluck('id')->sort()->values()->all())
            ->toBe($invoices->pluck('id')->sort()->values()->all());
    });
});

describe('Request Stage Transitions', function (): void {
    it('can transition from draft to quoting_supplier', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request->canTransitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE))->toBeTrue();
    });

    it('cannot transition from draft to ordered directly', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request->canTransitionTo(RequestStage::PREPARING_SUPPLIER_ORDER))->toBeFalse();
    });

    it('transitions successfully when allowed', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        $request->transitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE);

        expect($request->stage)->toBe(RequestStage::AWAITING_SUPPLIER_RESPONSE);
    });

    it('throws exception for invalid transition', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        $request->transitionTo(RequestStage::COMPLETED);
    })->throws(InvalidArgumentException::class);

    it('can transition from cancelled back to draft', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::CANCELLED,
        ]);

        expect($request->canTransitionTo(RequestStage::DRAFT))->toBeTrue();
    });

    it('requires matched items for quoting_supplier transition', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        RequestItem::factory()->recycle($request)->create(['is_matched' => false]);

        $request->transitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE);
    })->throws(InvalidArgumentException::class);

    it('allows quoting_supplier transition when all items matched', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        $article = Article::factory()->recycle($this->team)->create();
        RequestItem::factory()->recycle($request)->create([
            'is_matched' => true,
            'article_id' => $article->getKey(),
        ]);

        $request->transitionTo(RequestStage::AWAITING_SUPPLIER_RESPONSE);

        expect($request->stage)->toBe(RequestStage::AWAITING_SUPPLIER_RESPONSE);
    });
});

describe('Request Item Matching', function (): void {
    it('tracks all items matched status', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
        $article = Article::factory()->recycle($this->team)->create();

        RequestItem::factory()->recycle($request)->create([
            'is_matched' => true,
            'article_id' => $article->getKey(),
        ]);
        RequestItem::factory()->recycle($request)->create([
            'is_matched' => false,
        ]);

        $request->refresh();

        expect($request->all_items_matched)->toBeFalse()
            ->and($request->matched_items_count)->toBe(1)
            ->and($request->unmatched_items_count)->toBe(1)
            ->and($request->items_count)->toBe(2);
    });

    it('returns true for all_items_matched when all items are matched', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
        $article = Article::factory()->recycle($this->team)->create();

        RequestItem::factory()->count(2)->recycle($request)->create([
            'is_matched' => true,
            'article_id' => $article->getKey(),
        ]);

        $request->refresh();

        expect($request->all_items_matched)->toBeTrue();
    });
});

describe('Request Item Editing', function (): void {
    it('allows item editing in draft stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request->canEditItems())->toBeTrue();
    });

    it('allows item editing in quoting_supplier stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::AWAITING_SUPPLIER_RESPONSE,
        ]);

        expect($request->canEditItems())->toBeTrue();
    });

    it('disallows item editing in ordered stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::PREPARING_SUPPLIER_ORDER,
        ]);

        expect($request->canEditItems())->toBeFalse();
    });
});

describe('Supplier Quote Auto-Generation', function (): void {
    beforeEach(function (): void {
        // Create a default currency for supplier quote generation
        $this->defaultCurrency = \App\Models\Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_default' => true,
        ]);
    });

    it('generates supplier quotes when transitioning from draft to awaiting_supplier_response', function (): void {
        // Create suppliers with articles
        $supplier1 = Company::factory()->supplier()->recycle($this->team)->create();
        $supplier2 = Company::factory()->supplier()->recycle($this->team)->create();

        $article = Article::factory()->recycle($this->team)->create();

        // Link article to both suppliers via pivot table
        DB::table('supplier_articles')->insert([
            ['article_id' => $article->getKey(), 'supplier_id' => $supplier1->getKey(), 'is_active' => true, 'is_preferred' => false, 'created_at' => now(), 'updated_at' => now()],
            ['article_id' => $article->getKey(), 'supplier_id' => $supplier2->getKey(), 'is_active' => true, 'is_preferred' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create request with item linked to article
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        RequestItem::factory()->recycle($request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        // Transition to AWAITING_SUPPLIER_RESPONSE
        $request->stage = RequestStage::AWAITING_SUPPLIER_RESPONSE;
        $request->save();

        // Check that quotes were generated for both suppliers
        expect($request->supplierQuotes)->toHaveCount(2);
        expect($request->supplierQuotes->pluck('supplier_id')->sort()->values()->all())
            ->toBe(collect([$supplier1->getKey(), $supplier2->getKey()])->sort()->values()->all());
    });

    it('creates quote items for each request item with matching supplier', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $article = Article::factory()->recycle($this->team)->create();

        DB::table('supplier_articles')->insert([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'is_active' => true,
            'is_preferred' => false,
            'last_quoted_price' => '150.0000',
            'last_quoted_currency_id' => $this->defaultCurrency->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        RequestItem::factory()->recycle($request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
            'quantity' => '5.0000',
        ]);

        $request->stage = RequestStage::AWAITING_SUPPLIER_RESPONSE;
        $request->save();

        $quote = $request->supplierQuotes()->first();
        expect($quote)->toBeInstanceOf(SupplierQuote::class)
            ->and($quote->items)->toHaveCount(1)
            ->and((float) $quote->items->first()->quantity)->toBe(5.0)
            ->and((float) $quote->items->first()->unit_price)->toBe(150.0);
    });

    it('does not generate duplicate quotes when transitioning multiple times', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $article = Article::factory()->recycle($this->team)->create();

        DB::table('supplier_articles')->insert([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'is_active' => true,
            'is_preferred' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        RequestItem::factory()->recycle($request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        // First transition
        $request->stage = RequestStage::AWAITING_SUPPLIER_RESPONSE;
        $request->save();

        // Transition back and forth (simulating returning to draft and back)
        $request->stage = RequestStage::DRAFT;
        $request->save();

        $request->stage = RequestStage::AWAITING_SUPPLIER_RESPONSE;
        $request->save();

        // Should still only have 1 quote (not 2)
        expect($request->supplierQuotes)->toHaveCount(1);
    });

    it('does not generate quotes for inactive supplier-article relationships', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $article = Article::factory()->recycle($this->team)->create();

        // Insert as inactive
        DB::table('supplier_articles')->insert([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'is_active' => false,
            'is_preferred' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);
        RequestItem::factory()->recycle($request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        $request->stage = RequestStage::AWAITING_SUPPLIER_RESPONSE;
        $request->save();

        expect($request->supplierQuotes)->toHaveCount(0);
    });
});
