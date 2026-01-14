<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Buyer;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Buyer::factory()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();
    $this->actingAs($this->user);
});

describe('RequestItem Model', function (): void {
    it('can create a request item with required fields', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create([
            'description' => 'Test Item',
            'quantity' => 10,
            'unit' => 'pcs',
        ]);

        expect($item)->toBeInstanceOf(RequestItem::class)
            ->and($item->description)->toBe('Test Item')
            ->and($item->quantity)->toBe('10.0000')
            ->and($item->unit)->toBe('pcs');
    });

    it('defaults to not matched', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create();

        expect($item->is_matched)->toBeFalse();
    });

    it('belongs to a request', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create();

        expect($item->request)->toBeInstanceOf(Request::class)
            ->and($item->request->getKey())->toBe($this->request->getKey());
    });

    it('can belong to an article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        expect($item->article)->toBeInstanceOf(Article::class)
            ->and($item->article->getKey())->toBe($article->getKey());
    });

    it('can belong to a supplier', function (): void {
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create([
            'supplier_id' => $supplier->getKey(),
        ]);

        expect($item->supplier)->toBeInstanceOf(Company::class)
            ->and($item->supplier->getKey())->toBe($supplier->getKey());
    });
});

describe('RequestItem Matching', function (): void {
    it('can match to an article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create();

        $item->matchToArticle($article);

        expect($item->is_matched)->toBeTrue()
            ->and($item->article_id)->toBe($article->getKey());
    });

    it('can match to an article with supplier', function (): void {
        $article = Article::factory()->recycle($this->team)->create();
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create();

        $item->matchToArticle($article, $supplier);

        expect($item->is_matched)->toBeTrue()
            ->and($item->article_id)->toBe($article->getKey())
            ->and($item->supplier_id)->toBe($supplier->getKey());
    });

    it('can unmatch from an article', function (): void {
        $article = Article::factory()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        $item->unmatch();

        expect($item->is_matched)->toBeFalse()
            ->and($item->article_id)->toBeNull();
    });

    it('clears supplier when unmatched', function (): void {
        $article = Article::factory()->recycle($this->team)->create();
        $supplier = Company::factory()->supplier()->recycle($this->team)->create();
        $item = RequestItem::factory()->recycle($this->request)->create([
            'article_id' => $article->getKey(),
            'supplier_id' => $supplier->getKey(),
            'is_matched' => true,
        ]);

        $item->unmatch();

        expect($item->is_matched)->toBeFalse()
            ->and($item->article_id)->toBeNull()
            ->and($item->supplier_id)->toBeNull();
    });
});

describe('RequestItem Display', function (): void {
    it('shows article info when matched', function (): void {
        $article = Article::factory()->recycle($this->team)->create([
            'code' => 'ART-001',
            'name' => 'Test Article',
        ]);
        $item = RequestItem::factory()->recycle($this->request)->create([
            'article_id' => $article->getKey(),
            'is_matched' => true,
        ]);

        expect($item->display_text)->toBe('[ART-001] Test Article');
    });

    it('shows description when not matched', function (): void {
        $item = RequestItem::factory()->recycle($this->request)->create([
            'description' => 'Some vague description',
        ]);

        expect($item->display_text)->toBe('Some vague description');
    });
});

describe('RequestItem Sorting', function (): void {
    it('orders items by sort_order', function (): void {
        RequestItem::factory()->recycle($this->request)->create(['sort_order' => 2, 'description' => 'Second']);
        RequestItem::factory()->recycle($this->request)->create(['sort_order' => 1, 'description' => 'First']);
        RequestItem::factory()->recycle($this->request)->create(['sort_order' => 3, 'description' => 'Third']);

        $items = $this->request->items()->get();

        expect($items->first()->description)->toBe('First')
            ->and($items->last()->description)->toBe('Third');
    });
});
