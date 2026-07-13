<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Enums\PortalType;
use App\Livewire\RequestNoteComposer;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\User;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Storage::fake('local');

    $this->staff = User::factory()->withPersonalTeam()->create(['name' => 'Staff Nora']);
    $this->team = $this->staff->personalTeam();

    actingAs($this->staff);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();

    $this->request = Request::factory()->recycle($this->team)->create([
        'buyer_id' => $this->buyer->getKey(),
    ]);
});

/**
 * @return \Illuminate\Support\Collection<int, string|null>
 */
function composerStaffNoteBodies(Tests\TestCase $test): \Illuminate\Support\Collection
{
    return collect(app(RequestTimelineSource::class)->entries($test->request, TimelineParty::staff())->items())
        ->where('entryType', TimelineAudience::ENTRY_NOTE)
        ->pluck('body');
}

/**
 * @return \Illuminate\Support\Collection<int, string|null>
 */
function composerBuyerNoteBodies(Tests\TestCase $test): \Illuminate\Support\Collection
{
    $entries = app(PortalTimelineSource::class)->forParty($test->request, TimelineParty::buyer($test->buyer->getKey()));

    return collect($entries)->where('entryType', TimelineAudience::ENTRY_NOTE)->pluck('body');
}

function actAsBuyerPortalUser(Tests\TestCase $test): User
{
    $portalUser = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $test->team->getKey(),
        'company_id' => $test->buyer->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Buyer,
        'invited_by' => $test->staff->getKey(),
        'is_active' => true,
    ]);

    actingAs($portalUser, 'buyer');
    Filament::setCurrentPanel('buyer');
    app(\App\Services\Portal\BuyerPortalContext::class)->setCompany($test->buyer->getKey());

    return $portalUser;
}

function actAsSupplierPortalUser(Tests\TestCase $test, Company $supplier): User
{
    $portalUser = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $test->team->getKey(),
        'company_id' => $supplier->getKey(),
        'user_id' => $portalUser->getKey(),
        'portal' => PortalType::Supplier,
        'invited_by' => $test->staff->getKey(),
        'is_active' => true,
    ]);

    actingAs($portalUser, 'supplier');
    Filament::setCurrentPanel('supplier');
    app(\App\Services\Portal\SupplierPortalContext::class)->setCompany($supplier->getKey());

    return $portalUser;
}

it('lets staff post an internal note with a stamped attachment that stays off the buyer feed', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('body', 'internal-composer-note')
        ->set('attachments', [UploadedFile::fake()->create('spec.pdf', 12, 'application/pdf')])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('note-posted');

    $note = RequestNote::query()->latest('id')->firstOrFail();

    expect($note->visibility)->toBe(NoteVisibility::Internal)
        ->and($note->author_actor_type)->toBe(ActorType::Staff)
        ->and((int) $note->author_id)->toBe($this->staff->getKey())
        ->and((int) $note->team_id)->toBe($this->team->getKey());

    $media = $note->getMedia(RequestNote::ATTACHMENTS_COLLECTION)->first();

    expect($media)->not->toBeNull()
        ->and($media->file_name)->toBe('spec.pdf')
        ->and($media->getCustomProperty('uploader_actor_type'))->toBe(ActorType::Staff->value)
        ->and((int) $media->getCustomProperty('uploader_id'))->toBe($this->staff->getKey());

    expect(composerStaffNoteBodies($this))->toContain('internal-composer-note')
        ->and(composerBuyerNoteBodies($this))->not->toContain('internal-composer-note');
});

it('lets staff share a note with the buyer so it reaches the buyer portal feed', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('visibility', NoteVisibility::Buyer->value)
        ->set('body', 'buyer-shared-composer-note')
        ->call('submit')
        ->assertHasNoErrors();

    $note = RequestNote::query()->latest('id')->firstOrFail();

    expect($note->visibility)->toBe(NoteVisibility::Buyer)
        ->and($note->author_actor_type)->toBe(ActorType::Staff)
        ->and($note->audience_company_id)->toBeNull();

    expect(composerBuyerNoteBodies($this))->toContain('buyer-shared-composer-note');
});

it('lets staff share a note with a chosen supplier scoped to that supplier only', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('visibility', NoteVisibility::Supplier->value)
        ->set('supplierCompanyId', $this->supplier->getKey())
        ->set('body', 'supplier-shared-composer-note')
        ->call('submit')
        ->assertHasNoErrors();

    $note = RequestNote::query()->latest('id')->firstOrFail();

    expect($note->visibility)->toBe(NoteVisibility::Supplier)
        ->and((int) $note->audience_company_id)->toBe($this->supplier->getKey());
});

it('rejects a staff supplier-share with no supplier chosen', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('visibility', NoteVisibility::Supplier->value)
        ->set('body', 'orphan-supplier-note')
        ->call('submit')
        ->assertHasErrors('supplierCompanyId');

    expect(RequestNote::query()->count())->toBe(0);
});

it('lets a buyer post a Buyer-visibility note visible to staff and that buyer only', function (): void {
    $portalUser = actAsBuyerPortalUser($this);

    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('body', 'buyer-authored-note')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('note-posted');

    $note = RequestNote::query()->latest('id')->firstOrFail();

    expect($note->visibility)->toBe(NoteVisibility::Buyer)
        ->and($note->author_actor_type)->toBe(ActorType::Buyer)
        ->and((int) $note->author_id)->toBe($portalUser->getKey())
        ->and($note->audience_company_id)->toBeNull();

    expect(composerBuyerNoteBodies($this))->toContain('buyer-authored-note')
        ->and(composerStaffNoteBodies($this))->toContain('buyer-authored-note');
});

it('scopes a supplier-authored note to the supplier, hidden from buyer and other suppliers', function (): void {
    $otherSupplier = Company::factory()->supplier()->recycle($this->team)->create();

    actAsSupplierPortalUser($this, $this->supplier);

    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('body', 'supplier-authored-note')
        ->call('submit')
        ->assertHasNoErrors();

    $note = RequestNote::query()->latest('id')->firstOrFail();

    expect($note->visibility)->toBe(NoteVisibility::Supplier)
        ->and($note->author_actor_type)->toBe(ActorType::Supplier)
        ->and((int) $note->audience_company_id)->toBe($this->supplier->getKey());

    $ownFeed = collect(app(PortalTimelineSource::class)->forParty($this->request, TimelineParty::supplier($this->supplier->getKey())))
        ->where('entryType', TimelineAudience::ENTRY_NOTE)->pluck('body');
    $otherFeed = collect(app(PortalTimelineSource::class)->forParty($this->request, TimelineParty::supplier($otherSupplier->getKey())))
        ->where('entryType', TimelineAudience::ENTRY_NOTE)->pluck('body');

    expect($ownFeed)->toContain('supplier-authored-note')
        ->and($otherFeed)->not->toContain('supplier-authored-note')
        ->and(composerBuyerNoteBodies($this))->not->toContain('supplier-authored-note')
        ->and(composerStaffNoteBodies($this))->toContain('supplier-authored-note');
});

it('anchors the sr-only file input inside a positioned label so it cannot stretch the page', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->assertSeeHtml('class="relative flex cursor-pointer');
});

it('rejects a note with an empty body and no attachment', function (): void {
    livewire(RequestNoteComposer::class, ['request' => $this->request])
        ->set('body', '   ')
        ->call('submit')
        ->assertHasErrors('body');

    expect(RequestNote::query()->count())->toBe(0);
});
