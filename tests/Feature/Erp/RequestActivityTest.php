<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\BuyerOrder;
use App\Models\Request;
use App\Models\RequestActivity;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->request = Request::factory()->recycle($this->team)->create();
    $this->actingAs($this->user);
});

describe('RequestActivity Model', function (): void {
    it('can create a request activity with required fields', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forUser($this->user)
            ->withType(ActivityType::REQUEST_CREATED)
            ->create([
                'description' => 'Test activity description',
            ]);

        expect($activity)->toBeInstanceOf(RequestActivity::class)
            ->and($activity->activity_type)->toBe(ActivityType::REQUEST_CREATED)
            ->and($activity->description)->toBe('Test activity description')
            ->and($activity->team_id)->toBe($this->team->getKey())
            ->and($activity->request_id)->toBe($this->request->getKey())
            ->and($activity->user_id)->toBe($this->user->getKey());
    });

    it('can create activity without user (system generated)', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->systemGenerated()
            ->create();

        expect($activity->user_id)->toBeNull();
    });
});

describe('RequestActivity Relations', function (): void {
    it('belongs to a request', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->create();

        expect($activity->request)->toBeInstanceOf(Request::class)
            ->and($activity->request->getKey())->toBe($this->request->getKey());
    });

    it('belongs to a user', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->forUser($this->user)
            ->create();

        expect($activity->user)->toBeInstanceOf(User::class)
            ->and($activity->user->getKey())->toBe($this->user->getKey());
    });

    it('belongs to a team', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->create();

        expect($activity->team)->toBeInstanceOf(Team::class)
            ->and($activity->team->getKey())->toBe($this->team->getKey());
    });
});

describe('RequestActivity Type Casting', function (): void {
    it('casts activity_type to ActivityType enum', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withType(ActivityType::STAGE_CHANGED)
            ->create();

        expect($activity->activity_type)->toBeInstanceOf(ActivityType::class)
            ->and($activity->activity_type)->toBe(ActivityType::STAGE_CHANGED);
    });

    it('casts activity_type correctly for all enum values', function (): void {
        foreach (ActivityType::cases() as $type) {
            $activity = RequestActivity::factory()
                ->recycle($this->team)
                ->forRequest($this->request)
                ->withType($type)
                ->create();

            expect($activity->activity_type)->toBe($type);
        }
    });
});

describe('RequestActivity Metadata', function (): void {
    it('casts metadata to array', function (): void {
        $metadata = ['old_value' => 'Draft', 'new_value' => 'In Progress'];

        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withMetadata($metadata)
            ->create();

        expect($activity->metadata)->toBeArray()
            ->and($activity->metadata)->toBe($metadata);
    });

    it('handles complex metadata structures', function (): void {
        $metadata = [
            'changes' => [
                'title' => ['old' => 'Old Title', 'new' => 'New Title'],
                'priority' => ['old' => 'normal', 'new' => 'urgent'],
            ],
            'timestamp' => '2024-01-15T10:30:00Z',
            'nested' => ['deep' => ['value' => 123]],
        ];

        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withMetadata($metadata)
            ->create();

        expect($activity->metadata)->toBe($metadata)
            ->and($activity->metadata['changes']['title']['new'])->toBe('New Title')
            ->and($activity->metadata['nested']['deep']['value'])->toBe(123);
    });

    it('handles null metadata', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->create([
                'metadata' => null,
            ]);

        expect($activity->metadata)->toBeNull();
    });

    it('handles empty array metadata', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withMetadata([])
            ->create();

        expect($activity->metadata)->toBeArray()
            ->and($activity->metadata)->toBe([]);
    });
});

describe('RequestActivity Subject Morph', function (): void {
    it('morphs to a subject entity', function (): void {
        $buyerOrder = BuyerOrder::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->create();

        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->withSubject($buyerOrder)
            ->withType(ActivityType::BUYER_ORDER_CREATED)
            ->create([
                'description' => 'Buyer order created',
            ]);

        expect($activity->subject_type)->toBe($buyerOrder->getMorphClass())
            ->and($activity->subject_id)->toBe($buyerOrder->getKey())
            ->and($activity->subject)->toBeInstanceOf(BuyerOrder::class)
            ->and($activity->subject->getKey())->toBe($buyerOrder->getKey());
    });

    it('handles null subject correctly', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->create([
                'subject_type' => null,
                'subject_id' => null,
            ]);

        expect($activity->subject_type)->toBeNull()
            ->and($activity->subject_id)->toBeNull()
            ->and($activity->subject)->toBeNull();
    });
});

describe('RequestActivity Factory States', function (): void {
    it('creates request created activity', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->requestCreated()
            ->create();

        expect($activity->activity_type)->toBe(ActivityType::REQUEST_CREATED)
            ->and($activity->description)->toBe('Request was created');
    });

    it('creates stage changed activity with metadata', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->stageChanged('Draft', 'In Progress')
            ->create();

        expect($activity->activity_type)->toBe(ActivityType::STAGE_CHANGED)
            ->and($activity->description)->toBe('Stage changed from Draft to In Progress')
            ->and($activity->metadata)->toBe(['from' => 'Draft', 'to' => 'In Progress']);
    });

    it('creates item added activity', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->itemAdded()
            ->create();

        expect($activity->activity_type)->toBe(ActivityType::ITEM_ADDED);
    });

    it('creates payment received activity', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->paymentReceived()
            ->create();

        expect($activity->activity_type)->toBe(ActivityType::PAYMENT_RECEIVED);
    });

    it('creates note added activity', function (): void {
        $activity = RequestActivity::factory()
            ->recycle($this->team)
            ->forRequest($this->request)
            ->noteAdded()
            ->create();

        expect($activity->activity_type)->toBe(ActivityType::NOTE_ADDED);
    });
});

describe('ActivityType Enum', function (): void {
    it('returns correct labels', function (): void {
        expect(ActivityType::REQUEST_CREATED->getLabel())->toBe('Request Created')
            ->and(ActivityType::STAGE_CHANGED->getLabel())->toBe('Stage Changed')
            ->and(ActivityType::BUYER_ORDER_CREATED->getLabel())->toBe('Buyer Order Created')
            ->and(ActivityType::PAYMENT_RECEIVED->getLabel())->toBe('Payment Received');
    });

    it('returns correct icons', function (): void {
        expect(ActivityType::REQUEST_CREATED->getIcon())->toBe('heroicon-o-plus-circle')
            ->and(ActivityType::STAGE_CHANGED->getIcon())->toBe('heroicon-o-chevron-double-right')
            ->and(ActivityType::PAYMENT_RECEIVED->getIcon())->toBe('heroicon-o-banknotes');
    });

    it('returns correct colors', function (): void {
        expect(ActivityType::REQUEST_CREATED->getColor())->toBe('success')
            ->and(ActivityType::ITEM_REMOVED->getColor())->toBe('danger')
            ->and(ActivityType::BUYER_QUOTE_REJECTED->getColor())->toBe('danger')
            ->and(ActivityType::NOTE_ADDED->getColor())->toBe('gray');
    });
});
