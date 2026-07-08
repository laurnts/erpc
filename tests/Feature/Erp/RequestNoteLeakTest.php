<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\User;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

const INTERNAL_BODY = 'internal-only-secret-margin';
const BUYER_BODY = 'buyer-visible-delivery-update';
const SUPPLIER_A_BODY = 'supplier-a-packing-instructions';
const SUPPLIER_B_BODY = 'supplier-b-private-cost-note';

beforeEach(function (): void {
    $this->staff = User::factory()->withPersonalTeam()->create(['name' => 'Staff Member Olivia']);
    $this->team = $this->staff->personalTeam();

    actingAs($this->staff);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplierA = Company::factory()->supplier()->recycle($this->team)->create();
    $this->supplierB = Company::factory()->supplier()->recycle($this->team)->create();

    $this->request = Request::factory()->recycle($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
    ]);

    // Internal-only note — must never reach any portal.
    RequestNote::factory()->recycle($this->team)->for($this->request)->internal()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => INTERNAL_BODY,
    ]);

    // Buyer-shared note — only the buyer (and staff) may read it.
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithBuyer()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => BUYER_BODY,
    ]);

    // Supplier note shared to supplier A — only supplier A (and staff) may read it.
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithSupplier($this->supplierA)->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => SUPPLIER_A_BODY,
    ]);

    // Supplier note authored by supplier B (their own note) — only supplier B (and staff) may read it.
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithSupplier($this->supplierB)->authoredBySupplier()->create([
        'team_id' => $this->team->getKey(),
        'body' => SUPPLIER_B_BODY,
    ]);

    $this->portal = app(PortalTimelineSource::class);
    $this->internal = app(RequestTimelineSource::class);
});

/**
 * @param  list<\App\Data\TimelineEntry>  $entries
 * @return list<string|null>
 */
function noteBodies(array $entries): array
{
    return collect($entries)
        ->where('entryType', TimelineAudience::ENTRY_NOTE)
        ->pluck('body')
        ->all();
}

it('shows the buyer only the buyer-shared note, never internal or supplier notes', function (): void {
    $entries = $this->portal->forParty($this->request, TimelineParty::buyer($this->buyer->getKey()));

    expect(noteBodies($entries))->toBe([BUYER_BODY]);

    $rendered = view('timeline.portal-timeline', ['entries' => $entries])->render();

    expect($rendered)->toContain(BUYER_BODY)
        ->and($rendered)->not->toContain(INTERNAL_BODY)
        ->and($rendered)->not->toContain(SUPPLIER_A_BODY)
        ->and($rendered)->not->toContain(SUPPLIER_B_BODY)
        ->and($rendered)->not->toContain('Staff Member Olivia');
});

it('shows supplier A only the note shared to A, never internal, buyer, or supplier B notes', function (): void {
    $entries = $this->portal->forParty($this->request, TimelineParty::supplier($this->supplierA->getKey()));

    expect(noteBodies($entries))->toBe([SUPPLIER_A_BODY]);

    $rendered = view('timeline.portal-timeline', ['entries' => $entries])->render();

    expect($rendered)->toContain(SUPPLIER_A_BODY)
        ->and($rendered)->not->toContain(INTERNAL_BODY)
        ->and($rendered)->not->toContain(BUYER_BODY)
        ->and($rendered)->not->toContain(SUPPLIER_B_BODY)
        ->and($rendered)->not->toContain('Staff Member Olivia');
});

it('shows supplier B only its own note, isolated from supplier A', function (): void {
    $entries = $this->portal->forParty($this->request, TimelineParty::supplier($this->supplierB->getKey()));

    expect(noteBodies($entries))->toBe([SUPPLIER_B_BODY]);

    $rendered = view('timeline.portal-timeline', ['entries' => $entries])->render();

    expect($rendered)->toContain(SUPPLIER_B_BODY)
        ->and($rendered)->not->toContain(SUPPLIER_A_BODY)
        ->and($rendered)->not->toContain(INTERNAL_BODY)
        ->and($rendered)->not->toContain(BUYER_BODY);
});

it('shows staff every note regardless of visibility', function (): void {
    $entries = collect($this->internal->entries($this->request, TimelineParty::staff())->items())->all();

    expect(noteBodies($entries))->toHaveCount(4)
        ->and(noteBodies($entries))->toContain(INTERNAL_BODY, BUYER_BODY, SUPPLIER_A_BODY, SUPPLIER_B_BODY);
});
