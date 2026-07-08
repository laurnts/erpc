<?php

declare(strict_types=1);

use App\Actions\SupplierPortal\StampSupplierQuoteSent;
use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\SupplierQuote;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team = $this->admin->personalTeam();

    actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team);
});

it('logs a sent activity when the request is dispatched to the supplier', function (): void {
    $quote = SupplierQuote::factory()->recycle($this->team)->pending()->create();

    ActivityLog::query()->delete();

    app(StampSupplierQuoteSent::class)->execute($quote);

    $activities = ActivityLog::query()->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->event)->toBe('sent')
        ->and($activity->subject_type)->toBe('supplier_quote')
        ->and($activity->subject_id)->toBe($quote->id)
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->actor_type)->toBe(ActorType::Staff)
        ->and($activity->properties->get('sent_to_supplier_at'))->toBe($quote->refresh()->sent_to_supplier_at->toDateTimeString());
});

it('logs each dispatch separately because every send is a fresh request', function (): void {
    $quote = SupplierQuote::factory()->recycle($this->team)->pending()->create();

    ActivityLog::query()->delete();

    app(StampSupplierQuoteSent::class)->execute($quote);

    $this->travel(1)->minutes();

    app(StampSupplierQuoteSent::class)->execute($quote->refresh());

    expect(ActivityLog::query()->where('event', 'sent')->count())->toBe(2);
});

it('logs a sent activity on re-send after a supplier decline', function (): void {
    $quote = SupplierQuote::factory()
        ->recycle($this->team)
        ->pending()
        ->sentToSupplier()
        ->declined()
        ->create();

    ActivityLog::query()->delete();

    app(StampSupplierQuoteSent::class)->execute($quote);

    $activity = ActivityLog::query()->latest('id')->first();

    expect(ActivityLog::query()->count())->toBe(1)
        ->and($activity->event)->toBe('sent')
        ->and($activity->subject_type)->toBe('supplier_quote')
        ->and($activity->subject_id)->toBe($quote->id)
        ->and($quote->refresh()->declined_at)->toBeNull();
});
