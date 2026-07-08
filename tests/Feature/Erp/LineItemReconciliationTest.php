<?php

declare(strict_types=1);

/**
 * Regression tests for the D2 persistence refactor (mass-delete-and-recreate →
 * in-place reconciliation) of add-line-item-activity-logging.
 *
 * The characterization suite (LineItemPersistenceCharacterizationTest) pins the
 * resulting item SET. These tests pin the EVENTS the reconciler must now fire so
 * the audit log is correct: a genuine quantity edit fires exactly one `updated`
 * (no delete+create churn), a removal fires `deleted`, and the surviving primary
 * keys + RequestItem hierarchy are preserved.
 *
 * The `*Item` models get their activity-logging trait from a parallel change; to
 * stay independent of merge order these assert the raw Eloquent model events
 * (via Event::listen) rather than activity_log rows.
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
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->uom = UnitOfMeasure::factory()->for($this->team)->create(['code' => 'pcs']);
});

/**
 * Counts RequestItem model events, optionally scoped to top-level or child rows.
 *
 * @return array{updated: int, deleted: int, created: int}
 */
function requestItemEventCounts(callable $run, ?bool $childrenOnly = null): array
{
    $counts = ['updated' => 0, 'deleted' => 0, 'created' => 0];

    $matches = static function (RequestItem $item) use ($childrenOnly): bool {
        if ($childrenOnly === null) {
            return true;
        }

        return $childrenOnly ? $item->parent_id !== null : $item->parent_id === null;
    };

    foreach (['updated', 'deleted', 'created'] as $event) {
        Event::listen(
            'eloquent.'.$event.': '.RequestItem::class,
            static function (RequestItem $item) use (&$counts, $event, $matches): void {
                if ($matches($item)) {
                    $counts[$event]++;
                }
            },
        );
    }

    $run();

    return $counts;
}

describe('Buyer request edit reconciliation (surface 1)', function (): void {
    beforeEach(function (): void {
        config(['app.buyer_portal_enabled' => true]);

        $this->portalUser = User::factory()->create(['email' => 'recon.portal@buyer.test']);

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
            'unit' => 'pcs',
            'sort_order' => 0,
        ]);
        $this->itemB = RequestItem::factory()->for($this->request)->create([
            'description' => 'Line B',
            'item_type' => ItemType::GOODS,
            'quantity' => '5.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'unit' => 'pcs',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->portalUser, 'buyer');
        \Filament\Facades\Filament::setCurrentPanel('buyer');
        app(BuyerPortalContext::class)->setCompany($this->buyer->getKey());
    });

    it('fires exactly one updated event for a quantity edit — no delete/create churn', function (): void {
        $component = livewire(EditBuyerRequest::class, ['record' => $this->request->getKey()]);
        $items = $component->get('data.items');

        $keyByDescription = [];
        foreach ($items as $rowKey => $row) {
            $keyByDescription[$row['description']] = $rowKey;
        }
        $items[$keyByDescription['Line A']]['quantity'] = 25;

        $counts = requestItemEventCounts(function () use ($component, $items): void {
            $component
                ->set('data.submission_method_choice', RequestSubmissionMethod::MANUAL->value)
                ->set('data.items', $items)
                ->call('save')
                ->assertHasNoFormErrors();
        });

        expect($counts)->toBe(['updated' => 1, 'deleted' => 0, 'created' => 0]);

        // Surviving rows keep their ids; values reconciled in place.
        $this->itemA->refresh();
        $this->itemB->refresh();
        expect((float) $this->itemA->quantity)->toBe(25.0)
            ->and((float) $this->itemB->quantity)->toBe(5.0)
            ->and($this->request->items()->count())->toBe(2);
    });

    it('fires a deleted event for a removed line and preserves the rest', function (): void {
        $component = livewire(EditBuyerRequest::class, ['record' => $this->request->getKey()]);
        $items = $component->get('data.items');

        $keyByDescription = [];
        foreach ($items as $rowKey => $row) {
            $keyByDescription[$row['description']] = $rowKey;
        }
        unset($items[$keyByDescription['Line B']]);

        $counts = requestItemEventCounts(function () use ($component, $items): void {
            $component
                ->set('data.submission_method_choice', RequestSubmissionMethod::MANUAL->value)
                ->set('data.items', $items)
                ->call('save')
                ->assertHasNoFormErrors();
        });

        expect($counts['deleted'])->toBe(1)
            ->and($counts['created'])->toBe(0);

        expect(RequestItem::query()->whereKey($this->itemB->getKey())->exists())->toBeFalse()
            ->and(RequestItem::query()->whereKey($this->itemA->getKey())->exists())->toBeTrue()
            ->and($this->request->items()->count())->toBe(1);
    });
});

describe('Request items RM child reconciliation (surface 2)', function (): void {
    beforeEach(function (): void {
        $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
        $this->user->assignRole('admin');
        $this->team->users()->attach($this->user, ['role' => 'admin']);
        $this->actingAs($this->user);

        $this->request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();

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
            'item_type' => ItemType::SERVICE,
            'quantity' => '3.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 0,
        ]);
        $this->childY = RequestItem::factory()->for($this->request)->create([
            'parent_id' => $this->main->getKey(),
            'description' => 'Child Y',
            'item_type' => ItemType::SERVICE,
            'quantity' => '4.0000',
            'unit_of_measure_id' => $this->uom->getKey(),
            'sort_order' => 1,
        ]);

        \Filament\Facades\Filament::setCurrentPanel('admin');
        \Filament\Facades\Filament::setTenant($this->team);
    });

    it('updates a kept child in place, deletes a removed child, creates a new one', function (): void {
        $component = livewire(ItemsRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->mountTableAction('edit', $this->main);

        // fillForm now carries each child's id — keep Child X (with its id) and
        // bump its quantity, drop Child Y, add Child Z (no id → a genuine create).
        $children = $component->get('mountedActions.0.data.children');

        $rebuilt = [];
        foreach ($children as $child) {
            if ($child['description'] === 'Child X') {
                $child['quantity'] = 9;
                $rebuilt[] = $child;
            }
        }
        $rebuilt[] = ['description' => 'Child Z', 'quantity' => 2, 'unit_of_measure_id' => $this->uom->getKey()];

        $counts = requestItemEventCounts(function () use ($component, $rebuilt): void {
            $component
                ->set('mountedActions.0.data.children', $rebuilt)
                ->callMountedTableAction()
                ->assertHasNoTableActionErrors();
        }, childrenOnly: true);

        expect($counts)->toBe(['updated' => 1, 'deleted' => 1, 'created' => 1]);

        // Kept child keeps its id; hierarchy + type cascade preserved.
        $this->childX->refresh();
        expect((float) $this->childX->quantity)->toBe(9.0)
            ->and($this->childX->parent_id)->toBe($this->main->getKey())
            ->and(RequestItem::query()->whereKey($this->childY->getKey())->exists())->toBeFalse();

        $children = $this->main->children()->orderBy('sort_order')->get();
        $byDescription = $children->keyBy('description');
        expect($children)->toHaveCount(2)
            ->and($byDescription->keys()->sort()->values()->all())->toBe(['Child X', 'Child Z'])
            ->and($byDescription['Child Z']->parent_id)->toBe($this->main->getKey())
            ->and($byDescription['Child Z']->item_type)->toBe(ItemType::SERVICE);
    });
});
