<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Enums\NoteVisibility;
use App\Models\Company;
use App\Models\Request;
use App\Models\RequestNote;
use App\Models\User;
use App\Policies\RequestNotePolicy;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
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

    $this->source = app(RequestTimelineSource::class);
});

/**
 * @return \Illuminate\Support\Collection<int, \App\Data\TimelineEntry>
 */
function staffNoteEntries(Tests\TestCase $test): \Illuminate\Support\Collection
{
    return collect($test->source->entries($test->request, TimelineParty::staff())->items())
        ->where('entryType', TimelineAudience::ENTRY_NOTE);
}

it('surfaces notes of every visibility for staff with author attribution', function (): void {
    RequestNote::factory()->recycle($this->team)->for($this->request)->internal()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => 'internal-secret-note',
    ]);
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithBuyer()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => 'buyer-shared-note',
    ]);
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithSupplier($this->supplier)->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => 'supplier-shared-note',
    ]);

    $notes = staffNoteEntries($this);

    expect($notes)->toHaveCount(3)
        ->and($notes->every(fn ($n): bool => $n->actorType === ActorType::Staff))->toBeTrue()
        ->and($notes->every(fn ($n): bool => $n->actorLabel === 'Staff Nora'))->toBeTrue()
        ->and($notes->pluck('body')->all())->toContain('internal-secret-note', 'buyer-shared-note', 'supplier-shared-note');
});

it('records the note visibility, event, and lane on the entry', function (): void {
    RequestNote::factory()->recycle($this->team)->for($this->request)->sharedWithBuyer()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
    ]);

    $note = staffNoteEntries($this)->firstOrFail();

    expect($note->properties['visibility'])->toBe(NoteVisibility::Buyer->value)
        ->and($note->event)->toBe('note')
        ->and($note->lane)->toBe('note');
});

it('surfaces a note attachment in both the attachments array and the headline', function (): void {
    $note = RequestNote::factory()->recycle($this->team)->for($this->request)->internal()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
        'body' => 'see attached spec',
    ]);

    $note->addMediaFromString('dummy-bytes')
        ->usingFileName('spec-sheet.pdf')
        ->toMediaCollection(RequestNote::ATTACHMENTS_COLLECTION);

    $entry = staffNoteEntries($this)->firstOrFail();

    expect($entry->attachments)->toContain('spec-sheet.pdf')
        ->and($entry->headline)->toContain('spec-sheet.pdf')
        ->and($entry->headline)->toContain('see attached spec');
});

it('authorizes staff to read and author notes on their own request', function (): void {
    $policy = app(RequestNotePolicy::class);

    $note = RequestNote::factory()->recycle($this->team)->for($this->request)->internal()->authoredByStaff($this->staff)->create([
        'team_id' => $this->team->getKey(),
    ]);

    expect($policy->view($this->staff, $note))->toBeTrue()
        ->and($policy->createNote($this->staff, $this->request, NoteVisibility::Internal))->toBeTrue()
        ->and($policy->createNote($this->staff, $this->request, NoteVisibility::Buyer))->toBeTrue();
});
