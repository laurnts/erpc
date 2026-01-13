<?php

declare(strict_types=1);

use App\Enums\RequestStage;
use App\Models\Article;
use App\Models\Buyer;
use App\Models\Project;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Buyer::factory()->recycle($this->team)->create();
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

    it('defaults to draft stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

        expect($request->stage)->toBe(RequestStage::DRAFT);
    });

    it('belongs to a buyer', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

        expect($request->buyer)->toBeInstanceOf(Buyer::class)
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
});

describe('Request Stage Transitions', function (): void {
    it('can transition from draft to quoting_supplier', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request->canTransitionTo(RequestStage::QUOTING_SUPPLIER))->toBeTrue();
    });

    it('cannot transition from draft to ordered directly', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        expect($request->canTransitionTo(RequestStage::ORDERED))->toBeFalse();
    });

    it('transitions successfully when allowed', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::DRAFT,
        ]);

        $request->transitionTo(RequestStage::QUOTING_SUPPLIER);

        expect($request->stage)->toBe(RequestStage::QUOTING_SUPPLIER);
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

        $request->transitionTo(RequestStage::QUOTING_SUPPLIER);
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

        $request->transitionTo(RequestStage::QUOTING_SUPPLIER);

        expect($request->stage)->toBe(RequestStage::QUOTING_SUPPLIER);
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
            'stage' => RequestStage::QUOTING_SUPPLIER,
        ]);

        expect($request->canEditItems())->toBeTrue();
    });

    it('disallows item editing in ordered stage', function (): void {
        $request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create([
            'stage' => RequestStage::ORDERED,
        ]);

        expect($request->canEditItems())->toBeFalse();
    });
});
