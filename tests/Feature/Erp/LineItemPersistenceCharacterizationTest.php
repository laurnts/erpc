<?php

declare(strict_types=1);

/**
 * Characterization tests for the line-item save flows that persist request/quote
 * children via query-builder MASS DELETE + recreate (design D2 of
 * add-line-item-activity-logging).
 *
 * These pin the CURRENT resulting item set of each edit flow so the planned
 * persistence refactor (mass-delete → in-place reconciliation) can be proven
 * behavior-preserving. Assertions target the invariants the refactor MUST keep:
 * the final field set (quantity / description / item_type / unit), the row
 * count, and — for RequestItem — the parent_id hierarchy plus the item_type
 * cascade. Where a test documents behavior the refactor is expected to CHANGE
 * (e.g. today every surviving line gets a brand-new id), that is called out
 * explicitly as "current behavior".
 *
 * Covered surfaces:
 *   1. Buyer request edit  — EditBuyerRequest::afterSave (items()->delete())
 *   2. Request items RM edit  — ItemsRelationManager edit action (children()->delete())
 *   3. Quote cart submit      — SubmitQuoteCart (create-only items()->create())
 *
 * NOT covered here (see final report): BuyerQuotesRelationManager /
 * SupplierQuotesRelationManager edit actions. Their save closures are request
 * auto-generation + child-item-via-request-attributes pipelines that are not
 * reliably drivable at the Livewire level in a safety-net test; their
 * mass-delete pattern (items()->whereNull('request_item_id')->delete()) is
 * structurally the same reconciliation problem pinned by surfaces 1 and 2.
 */

use App\Enums\ItemType;
use App\Enums\PortalType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\EditBuyerRequest;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Team;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Portal\BuyerPortalContext;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->uom = UnitOfMeasure::factory()->for($this->team)->create(['code' => 'pcs']);
});

/**
 * Surface 1 — Buyer request edit (EditBuyerRequest::afterSave).
 *
 * Current persistence: $record->items()->delete() (query builder, bypasses
 * Eloquent events) then RequestItem::create() for every submitted row.
 */
describe('Buyer request edit (items mass delete + recreate)', function (): void {
    beforeEach(function (): void {
        config(['app.buyer_portal_enabled' => true]);

        $this->portalUser = User::factory()->create(['email' => 'char.portal@buyer.test']);

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'user_id' => $this->portalUser->getKey(),
            'portal' => PortalType::Buyer,
            'is_active' => true,
        ]);

        $this->request = Request::factory()
            ->for($this->team)
            ->for($this->buyer, 'buyer')
            ->create([
                'submission_method' => RequestSubmissionMethod::MANUAL,
                'submitted_at' => now(),
                'submitted_by_user_id' => $this->portalUser->getKey(),
                'stage' => RequestStage::DRAFT,
            ]);

        $this->itemA = RequestItem::factory()->for($this->request)->create([
            'description' => 'Line A',
            'item_type' => ItemType::GOODS,
            'quantity' => '10.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 0,
        ]);
        $this->itemB = RequestItem::factory()->for($this->request)->create([
            'description' => 'Line B',
            'item_type' => ItemType::GOODS,
            'quantity' => '5.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 1,
        ]);
        $this->itemC = RequestItem::factory()->for($this->request)->create([
            'description' => 'Line C',
            'item_type' => ItemType::GOODS,
            'quantity' => '2.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 2,
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());
    });

    it('pins the resulting item set after a change / remove / add edit', function (): void {
        $component = livewire(EditBuyerRequest::class, ['record' => $this->request->getKey()]);

        /** @var array<string, array<string, mixed>> $items */
        $items = $component->get('data.items');

        $keyByDescription = [];
        foreach ($items as $rowKey => $row) {
            $keyByDescription[$row['description']] = $rowKey;
        }

        // Edit: bump Line A quantity, drop Line C, add a brand-new Line D.
        $items[$keyByDescription['Line A']]['quantity'] = 25;
        unset($items[$keyByDescription['Line C']]);
        $items[(string) \Illuminate\Support\Str::uuid()] = [
            'description' => 'Line D',
            'item_type' => ItemType::GOODS->value,
            'quantity' => 7,
            'unit_of_measure_id' => $this->uom->getKey(),
        ];

        // set() (not fillForm) replaces the repeater array wholesale so the
        // removal of Line C actually takes effect (fillForm merges by row key).
        $component
            ->set('data.submission_method_choice', RequestSubmissionMethod::MANUAL->value)
            ->set('data.items', $items)
            ->call('save')
            ->assertHasNoFormErrors();

        $result = $this->request->refresh()->items()->orderBy('sort_order')->get();

        // Invariant: final field set — descriptions present, and their quantities.
        expect($result)->toHaveCount(3);

        $byDescription = $result->keyBy('description');
        expect($byDescription->keys()->sort()->values()->all())->toBe(['Line A', 'Line B', 'Line D'])
            ->and((float) $byDescription['Line A']->quantity)->toBe(25.0)
            ->and((float) $byDescription['Line B']->quantity)->toBe(5.0)
            ->and((float) $byDescription['Line D']->quantity)->toBe(7.0)
            ->and($byDescription['Line D']->item_type)->toBe(ItemType::GOODS)
            ->and($byDescription['Line A']->unit->value)->toBe('pcs');

        // Post-D2 behavior: in-place reconciliation (match-by-id) now PRESERVES
        // the primary keys of surviving rows — Line A and Line B keep their
        // original ids instead of being destroyed and recreated. (Pre-refactor
        // this pinned id churn; the reconciler intentionally flips it so a
        // genuine edit fires `updated`, not delete+create.)
        $survivingOriginalIds = $result->pluck('id')
            ->intersect([$this->itemA->getKey(), $this->itemB->getKey()])
            ->sort()
            ->values();
        expect($survivingOriginalIds->all())
            ->toBe(collect([$this->itemA->getKey(), $this->itemB->getKey()])->sort()->values()->all());

        // The removed line is gone; the surviving pre-edit ids still exist.
        expect(RequestItem::query()->whereKey($this->itemC->getKey())->exists())->toBeFalse()
            ->and(RequestItem::query()->whereKey($this->itemA->getKey())->exists())->toBeTrue()
            ->and(RequestItem::query()->whereKey($this->itemB->getKey())->exists())->toBeTrue();
    });

    it('pins item_type preservation for an unchanged services line on save', function (): void {
        // A services line must keep its type through the delete+recreate round-trip
        // (the form carries item_type per row).
        $this->itemB->update(['item_type' => ItemType::SERVICE]);

        $component = livewire(EditBuyerRequest::class, ['record' => $this->request->getKey()]);
        $items = $component->get('data.items');

        $component
            ->set('data.submission_method_choice', RequestSubmissionMethod::MANUAL->value)
            ->set('data.items', $items)
            ->call('save')
            ->assertHasNoFormErrors();

        $byDescription = $this->request->refresh()->items()->get()->keyBy('description');

        expect($byDescription)->toHaveCount(3)
            ->and($byDescription['Line A']->item_type)->toBe(ItemType::GOODS)
            ->and($byDescription['Line B']->item_type)->toBe(ItemType::SERVICE)
            ->and($byDescription['Line C']->item_type)->toBe(ItemType::GOODS);
    });
});

/**
 * Surface 2 — Request items relation manager edit (ItemsRelationManager).
 *
 * Current persistence: the edit action's using() closure updates the main
 * RequestItem, then $record->children()->delete() (query builder) followed by
 * RequestItem::create() for each submitted child row. Also exercises the
 * RequestItem observer cascade (item_type parent → children).
 */
describe('Request items RM edit (children mass delete + recreate)', function (): void {
    beforeEach(function (): void {
        $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
        $this->user->assignRole('admin');
        $this->team->users()->attach($this->user, ['role' => 'admin']);
        $this->actingAs($this->user);

        $this->request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();

        // The children repeater is only visible (and therefore dehydrated) when the
        // main item is a services line linked to an article.
        $article = Article::factory()->recycle($this->team)->create();
        $this->main = RequestItem::factory()->for($this->request)->matched($article)->create([
            'description' => 'Main service',
            'item_type' => ItemType::SERVICE,
            'quantity' => '1.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 0,
        ]);
        $this->childX = RequestItem::factory()->for($this->request)->create([
            'parent_id' => $this->main->getKey(),
            'description' => 'Child X',
            'quantity' => '3.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 0,
        ]);
        $this->childY = RequestItem::factory()->for($this->request)->create([
            'parent_id' => $this->main->getKey(),
            'description' => 'Child Y',
            'quantity' => '4.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 1,
        ]);

        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->team);
    });

    it('pins the resulting child set after a change / remove / add edit', function (): void {
        // Filament v5 keeps the mounted action form state at mountedActions.0.data;
        // set() there replaces the children repeater wholesale so the drop of
        // Child Y actually applies (fillForm/callTableAction data merges by key).
        livewire(ItemsRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])
            ->mountTableAction('edit', $this->main)
            ->set('mountedActions.0.data.description', 'Main service edited')
            ->set('mountedActions.0.data.quantity', 6)
            ->set('mountedActions.0.data.item_type', ItemType::SERVICE->value)
            ->set('mountedActions.0.data.children', [
                // Keep + modify Child X, drop Child Y, add Child Z.
                ['description' => 'Child X', 'quantity' => 9, 'unit_of_measure_id' => $this->uom->getKey()],
                ['description' => 'Child Z', 'quantity' => 2, 'unit_of_measure_id' => $this->uom->getKey()],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->main->refresh();

        // Main line field set.
        expect($this->main->description)->toBe('Main service edited')
            ->and((float) $this->main->quantity)->toBe(6.0)
            ->and($this->main->item_type)->toBe(ItemType::SERVICE);

        $children = $this->main->children()->orderBy('sort_order')->get();
        $childByDescription = $children->keyBy('description');

        // Invariant: final child field set + parent linkage.
        expect($children)->toHaveCount(2)
            ->and($childByDescription->keys()->sort()->values()->all())->toBe(['Child X', 'Child Z'])
            ->and((float) $childByDescription['Child X']->quantity)->toBe(9.0)
            ->and((float) $childByDescription['Child Z']->quantity)->toBe(2.0);

        foreach ($children as $child) {
            // parent_id hierarchy preserved.
            expect($child->parent_id)->toBe($this->main->getKey())
                // item_type cascade: children inherit the parent's type on create.
                ->and($child->item_type)->toBe(ItemType::SERVICE);
        }

        // Current behavior (D2 target): children are mass-deleted + recreated, so
        // even the kept "Child X" gets a new id and old child ids are gone.
        expect($children->pluck('id')->intersect([$this->childX->getKey(), $this->childY->getKey()]))->toBeEmpty()
            ->and(RequestItem::query()->whereKey($this->childY->getKey())->exists())->toBeFalse()
            ->and(RequestItem::query()->whereKey($this->childX->getKey())->exists())->toBeFalse();
    });

    it('pins the item_type cascade to recreated children when the main type changes', function (): void {
        livewire(ItemsRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])
            ->callTableAction('edit', $this->main, data: [
                'item_type' => ItemType::GOODS->value,
            ])
            ->assertHasNoTableActionErrors();

        // Switching a services main to goods drops children entirely (goods carry
        // no hierarchy). This is the current behavior the refactor must preserve.
        expect($this->main->refresh()->item_type)->toBe(ItemType::GOODS)
            ->and($this->main->children()->count())->toBe(0);
    });
});

/**
 * Surface 3 — Quote cart submit (SubmitQuoteCart).
 *
 * Create-only: builds a fresh Request and one RequestItem per cart line via
 * RequestItem::create(). No reconciliation, but pinned so the refactor keeps
 * the line-out mapping (article, quantity, item_type, unit) stable.
 */
describe('Quote cart submit (create-only line mapping)', function (): void {
    beforeEach(function (): void {
        config(['catalog.team_id' => $this->team->getKey()]);

        $this->cartUser = User::factory()->create(['email' => 'char.cart@buyer.test']);

        CompanyPortalUser::query()->create([
            'team_id' => $this->team->getKey(),
            'company_id' => $this->buyer->getKey(),
            'user_id' => $this->cartUser->getKey(),
            'portal' => PortalType::Buyer,
            'is_active' => true,
        ]);

        $this->articleP = Article::factory()->recycle($this->team)->create([
            'name' => 'Pump P-100',
            'unit' => 'pcs',
            'is_active' => true,
            'show_in_product_grid' => true,
        ]);
        $this->articleV = Article::factory()->recycle($this->team)->create([
            'name' => 'Valve V-200',
            'unit' => 'box',
            'is_active' => true,
            'show_in_product_grid' => true,
        ]);
    });

    it('pins the line-out mapping from cart lines to request items', function (): void {
        $request = app(\App\Actions\Catalog\SubmitQuoteCart::class)->execute(
            $this->cartUser,
            [
                $this->articleP->getKey() => 4.0,
                $this->articleV->getKey() => 9.0,
            ],
        );

        $items = $request->refresh()->items()->orderBy('sort_order')->get();
        $byArticle = $items->keyBy('article_id');

        expect($items)->toHaveCount(2)
            ->and($request->buyer_id)->toBe($this->buyer->getKey())
            ->and($request->submission_method)->toBe(RequestSubmissionMethod::CATALOG)
            ->and((float) $byArticle[$this->articleP->getKey()]->quantity)->toBe(4.0)
            ->and($byArticle[$this->articleP->getKey()]->description)->toBe('Pump P-100')
            ->and($byArticle[$this->articleP->getKey()]->item_type)->toBe(ItemType::GOODS)
            ->and($byArticle[$this->articleP->getKey()]->is_matched)->toBeTrue()
            ->and((float) $byArticle[$this->articleV->getKey()]->quantity)->toBe(9.0)
            ->and($byArticle[$this->articleV->getKey()]->description)->toBe('Valve V-200');
    });
});
