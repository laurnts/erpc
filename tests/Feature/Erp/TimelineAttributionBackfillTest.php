<?php

declare(strict_types=1);

use App\Data\TimelineEntry;
use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Timeline\RequestTimelineSource;
use App\Services\Timeline\TimelineAudience;
use App\Services\Timeline\TimelineParty;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('local');

    $this->admin = User::factory()->withPersonalTeam()->create(['name' => 'Jun Sin']);
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);

    $this->source = app(RequestTimelineSource::class);
});

/**
 * @return list<TimelineEntry>
 */
function mediaTimelineEntries(RequestTimelineSource $source, Request $request): array
{
    return collect($source->entries($request, TimelineParty::staff())->items())
        ->filter(fn (TimelineEntry $entry): bool => $entry->entryType === TimelineAudience::ENTRY_MEDIA)
        ->values()
        ->all();
}

it('stamps seeded staff-collection uploads as staff and renders them as a person, not System', function (): void {
    $request = Request::factory()->recycle($this->team)->create(['creator_id' => $this->admin->getKey()]);
    $order = SupplierOrder::factory()->recycle($this->team)->create([
        'request_id' => $request->id,
    ]);

    $this->artisan('request:seed-documents', [
        'identifier' => $order->po_number,
        '--gate' => 'goods-receive',
    ])->assertSuccessful();

    $media = $request->refresh()->getMedia('goods_receive')->first();

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty('uploader_actor_type'))->toBe(ActorType::Staff->value)
        ->and($media->getCustomProperty('uploader_id'))->toBe($this->admin->getKey());

    $entries = mediaTimelineEntries($this->source, $request);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->actorType)->toBe(ActorType::Staff)
        ->and($entries[0]->actorType)->not->toBe(ActorType::System)
        ->and($entries[0]->actorLabel)->toBe('Jun Sin');
});

it('backfills unstamped media by collection provenance and the owning creator', function (): void {
    $request = Request::factory()->recycle($this->team)->create(['creator_id' => $this->admin->getKey()]);

    // Buyer-facing collection, no uploader stamp (legacy upload).
    $request->addMediaFromString('buyer-file')
        ->usingFileName('buyer-intent.pdf')
        ->toMediaCollection('attachments');

    // Internal/staff collection, no uploader stamp.
    $request->addMediaFromString('staff-file')
        ->usingFileName('goods-receipt.pdf')
        ->toMediaCollection('goods_receive');

    $this->artisan('timeline:backfill-attribution')->assertSuccessful();

    $buyerMedia = $request->refresh()->getMedia('attachments')->first();
    $staffMedia = $request->refresh()->getMedia('goods_receive')->first();

    expect($buyerMedia->getCustomProperty('uploader_actor_type'))->toBe(ActorType::Buyer->value)
        ->and($buyerMedia->getCustomProperty('uploader_id'))->toBe($this->admin->getKey())
        ->and($staffMedia->getCustomProperty('uploader_actor_type'))->toBe(ActorType::Staff->value);

    $actorTypes = collect(mediaTimelineEntries($this->source, $request))
        ->pluck('actorType');

    expect($actorTypes)->toContain(ActorType::Buyer)
        ->and($actorTypes)->toContain(ActorType::Staff)
        ->and($actorTypes)->not->toContain(ActorType::System);
});

it('attributes a null-causer activity row to the subject creator as staff', function (): void {
    $request = Request::factory()->recycle($this->team)->create(['creator_id' => $this->admin->getKey()]);

    $activity = new ActivityLog;
    $activity->forceFill([
        'log_name' => 'default',
        'description' => 'updated',
        'subject_type' => 'request',
        'subject_id' => $request->id,
        'event' => 'updated',
        'causer_type' => null,
        'causer_id' => null,
        'actor_type' => ActorType::System,
        'team_id' => $this->team->id,
        'properties' => ['attributes' => ['stage' => 'confirmed']],
    ])->save();

    $this->artisan('timeline:backfill-attribution')->assertSuccessful();

    $activity->refresh();

    expect($activity->causer_id)->toBe($this->admin->getKey())
        ->and($activity->causer_type)->toBe((new User)->getMorphClass())
        ->and($activity->actor_type)->toBe(ActorType::Staff);
});

it('leaves genuine automation (non-default log name) activity rows untouched', function (): void {
    $request = Request::factory()->recycle($this->team)->create(['creator_id' => $this->admin->getKey()]);

    $activity = new ActivityLog;
    $activity->forceFill([
        'log_name' => 'system',
        'description' => 'reminder sent',
        'subject_type' => 'request',
        'subject_id' => $request->id,
        'event' => 'notified',
        'causer_type' => null,
        'causer_id' => null,
        'actor_type' => ActorType::System,
        'team_id' => $this->team->id,
        'properties' => [],
    ])->save();

    $this->artisan('timeline:backfill-attribution')->assertSuccessful();

    $activity->refresh();

    expect($activity->causer_id)->toBeNull()
        ->and($activity->actor_type)->toBe(ActorType::System);
});

it('is idempotent across repeated runs', function (): void {
    $request = Request::factory()->recycle($this->team)->create(['creator_id' => $this->admin->getKey()]);

    $request->addMediaFromString('staff-file')
        ->usingFileName('goods-receipt.pdf')
        ->toMediaCollection('goods_receive');

    $activity = new ActivityLog;
    $activity->forceFill([
        'log_name' => 'default',
        'description' => 'updated',
        'subject_type' => 'request',
        'subject_id' => $request->id,
        'event' => 'updated',
        'causer_type' => null,
        'causer_id' => null,
        'actor_type' => ActorType::System,
        'team_id' => $this->team->id,
        'properties' => [],
    ])->save();

    $this->artisan('timeline:backfill-attribution')->assertSuccessful();

    $mediaAfterFirst = $request->refresh()->getMedia('goods_receive')->first()->custom_properties;
    $activity->refresh();
    $causerAfterFirst = $activity->causer_id;

    $this->artisan('timeline:backfill-attribution')->assertSuccessful();

    $mediaAfterSecond = $request->refresh()->getMedia('goods_receive')->first()->custom_properties;
    $activity->refresh();

    expect($mediaAfterSecond)->toBe($mediaAfterFirst)
        ->and($activity->causer_id)->toBe($causerAfterFirst)
        ->and($activity->actor_type)->toBe(ActorType::Staff);
});
