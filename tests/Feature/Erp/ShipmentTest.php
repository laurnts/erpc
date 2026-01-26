<?php

declare(strict_types=1);

use App\Models\Company;

use App\Enums\ItemCondition;
use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->request = Request::factory()
        ->recycle($this->team)
        ->recycle($this->buyer)
        ->create();
    $this->actingAs($this->user);
});

describe('Shipment Model', function (): void {
    it('can create a shipment with required fields', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($shipment)->toBeInstanceOf(Shipment::class)
            ->and($shipment->type)->toBe(ShipmentType::INBOUND)
            ->and($shipment->status)->toBe(ShipmentStatus::PENDING)
            ->and($shipment->request_id)->toBe($this->request->getKey());
    });

    it('generates shipment number on creation', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($shipment->shipment_number)->toMatch('/^SHP-\d{4}-\d{4}$/');
    });

    it('defaults to pending status', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($shipment->status)->toBe(ShipmentStatus::PENDING);
    });

    it('defaults to inbound type', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($shipment->type)->toBe(ShipmentType::INBOUND);
    });

    it('belongs to a request', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($shipment->request)->toBeInstanceOf(Request::class)
            ->and($shipment->request->getKey())->toBe($this->request->getKey());
    });

    it('has many items', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->count(3)->recycle($shipment)->create();

        expect($shipment->items)->toHaveCount(3);
    });

    it('can be inbound type', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        expect($shipment->type)->toBe(ShipmentType::INBOUND)
            ->and($shipment->is_inbound)->toBeTrue()
            ->and($shipment->is_outbound)->toBeFalse();
    });

    it('can be outbound type', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->outbound()
            ->create();

        expect($shipment->type)->toBe(ShipmentType::OUTBOUND)
            ->and($shipment->is_outbound)->toBeTrue()
            ->and($shipment->is_inbound)->toBeFalse();
    });

    it('tracks carrier information', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->withCarrier('DHL', 'TRK-1234-5678')
            ->create();

        expect($shipment->carrier_name)->toBe('DHL')
            ->and($shipment->tracking_number)->toBe('TRK-1234-5678');
    });
});

describe('Shipment Status Transitions', function (): void {
    it('can mark shipment as in transit', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->pending()
            ->create();

        $shipment->markAsInTransit('TRK-123', now()->addDays(5));

        expect($shipment->status)->toBe(ShipmentStatus::IN_TRANSIT)
            ->and($shipment->shipped_at)->not->toBeNull()
            ->and($shipment->tracking_number)->toBe('TRK-123')
            ->and($shipment->expected_delivery_at)->not->toBeNull();
    });

    it('can mark shipment as delivered', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inTransit()
            ->create();

        $shipment->markAsDelivered();

        expect($shipment->status)->toBe(ShipmentStatus::DELIVERED)
            ->and($shipment->delivered_at)->not->toBeNull()
            ->and($shipment->is_delivered)->toBeTrue();
    });

    it('can mark shipment as partial', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inTransit()
            ->create();

        $shipment->markAsPartial();

        expect($shipment->status)->toBe(ShipmentStatus::PARTIAL);
    });

    it('can mark shipment as failed', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inTransit()
            ->create();

        $shipment->markAsFailed('Package lost in transit');

        expect($shipment->status)->toBe(ShipmentStatus::FAILED)
            ->and($shipment->notes)->toContain('Package lost in transit');
    });

    it('prevents invalid status transitions', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->delivered()
            ->create();

        expect(fn () => $shipment->markAsInTransit())
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('ShipmentItem Model', function (): void {
    it('can create a shipment item with required fields', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->create([
                'quantity_shipped' => '10.0000',
            ]);

        expect($item)->toBeInstanceOf(ShipmentItem::class)
            ->and((float) $item->quantity_shipped)->toBe(10.0);
    });

    it('belongs to a shipment', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()->recycle($shipment)->create();

        expect($item->shipment)->toBeInstanceOf(Shipment::class)
            ->and($item->shipment->getKey())->toBe($shipment->getKey());
    });

    it('defaults to good condition', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()->recycle($shipment)->create();

        expect($item->condition)->toBe(ItemCondition::GOOD);
    });

    it('can be marked as damaged', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->damaged('Box was crushed')
            ->create();

        expect($item->condition)->toBe(ItemCondition::DAMAGED)
            ->and($item->condition_notes)->toBe('Box was crushed')
            ->and($item->has_issue)->toBeTrue();
    });

    it('can be marked as rejected', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->rejected('Wrong product received')
            ->create();

        expect($item->condition)->toBe(ItemCondition::REJECTED)
            ->and($item->has_issue)->toBeTrue();
    });

    it('tracks quantity shipped and received', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->create([
                'quantity_shipped' => '100.0000',
                'quantity_received' => '95.0000',
            ]);

        expect((float) $item->quantity_shipped)->toBe(100.0)
            ->and((float) $item->quantity_received)->toBe(95.0)
            ->and($item->quantity_difference)->toBe(5.0)
            ->and($item->is_fully_received)->toBeFalse();
    });

    it('can be fully received', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->withQuantityShipped(50)
            ->fullyReceived()
            ->create();

        expect($item->is_fully_received)->toBeTrue()
            ->and($item->quantity_difference)->toBe(0.0);
    });

    it('can record receipt', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->withQuantityShipped(100)
            ->create();

        $item->recordReceipt(95, ItemCondition::DAMAGED, 'Some items were damaged');

        expect((float) $item->quantity_received)->toBe(95.0)
            ->and($item->condition)->toBe(ItemCondition::DAMAGED)
            ->and($item->condition_notes)->toBe('Some items were damaged');
    });

    it('returns receipt summary', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        $item = ShipmentItem::factory()
            ->recycle($shipment)
            ->create([
                'quantity_shipped' => '100.0000',
                'quantity_received' => '80.0000',
                'condition' => ItemCondition::GOOD,
            ]);

        $summary = $item->getReceiptSummary();

        expect($summary['shipped'])->toBe(100.0)
            ->and($summary['received'])->toBe(80.0)
            ->and($summary['difference'])->toBe(20.0)
            ->and($summary['percentage'])->toBe(80.0)
            ->and($summary['condition'])->toBe('Good')
            ->and($summary['is_complete'])->toBeFalse();
    });
});

describe('Shipment Quantity Summary', function (): void {
    it('calculates total quantity shipped', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->recycle($shipment)->create(['quantity_shipped' => '10.0000']);
        ShipmentItem::factory()->recycle($shipment)->create(['quantity_shipped' => '20.0000']);
        ShipmentItem::factory()->recycle($shipment)->create(['quantity_shipped' => '30.0000']);

        expect($shipment->total_quantity_shipped)->toBe(60.0);
    });

    it('calculates total quantity received', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->recycle($shipment)->create([
            'quantity_shipped' => '10.0000',
            'quantity_received' => '10.0000',
        ]);
        ShipmentItem::factory()->recycle($shipment)->create([
            'quantity_shipped' => '20.0000',
            'quantity_received' => '15.0000',
        ]);

        expect($shipment->total_quantity_received)->toBe(25.0);
    });

    it('returns quantity summary', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->recycle($shipment)->create([
            'quantity_shipped' => '100.0000',
            'quantity_received' => '80.0000',
        ]);

        $summary = $shipment->getQuantitySummary();

        expect($summary['shipped'])->toBe(100.0)
            ->and($summary['received'])->toBe(80.0)
            ->and($summary['difference'])->toBe(20.0)
            ->and($summary['percentage'])->toBe(80.0);
    });

    it('detects all items received', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->recycle($shipment)->fullyReceived()->create([
            'quantity_shipped' => '10.0000',
        ]);
        ShipmentItem::factory()->recycle($shipment)->fullyReceived()->create([
            'quantity_shipped' => '20.0000',
        ]);

        expect($shipment->allItemsReceived())->toBeTrue();
    });

    it('detects items with issues', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        ShipmentItem::factory()->recycle($shipment)->good()->create();
        ShipmentItem::factory()->recycle($shipment)->damaged()->create();

        expect($shipment->hasItemsWithIssues())->toBeTrue();
    });
});

describe('Request Shipments Relationship', function (): void {
    it('request has many shipments', function (): void {
        Shipment::factory()
            ->count(3)
            ->recycle($this->team)
            ->recycle($this->request)
            ->create();

        expect($this->request->shipments)->toHaveCount(3);
    });

    it('request can filter inbound shipments', function (): void {
        Shipment::factory()
            ->count(2)
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        Shipment::factory()
            ->count(1)
            ->recycle($this->team)
            ->recycle($this->request)
            ->outbound()
            ->create();

        expect($this->request->inboundShipments)->toHaveCount(2)
            ->and($this->request->outboundShipments)->toHaveCount(1);
    });
});

describe('ShipmentType Enum', function (): void {
    it('has correct labels', function (): void {
        expect(ShipmentType::INBOUND->getLabel())->toBe('Inbound')
            ->and(ShipmentType::OUTBOUND->getLabel())->toBe('Outbound');
    });

    it('has correct colors', function (): void {
        expect(ShipmentType::INBOUND->getColor())->toBe('info')
            ->and(ShipmentType::OUTBOUND->getColor())->toBe('success');
    });

    it('has correct icons', function (): void {
        expect(ShipmentType::INBOUND->getIcon())->toBe('heroicon-o-arrow-down-tray')
            ->and(ShipmentType::OUTBOUND->getIcon())->toBe('heroicon-o-arrow-up-tray');
    });
});

describe('ShipmentStatus Enum', function (): void {
    it('has correct labels', function (): void {
        expect(ShipmentStatus::PENDING->getLabel())->toBe('Pending')
            ->and(ShipmentStatus::IN_TRANSIT->getLabel())->toBe('In Transit')
            ->and(ShipmentStatus::DELIVERED->getLabel())->toBe('Delivered')
            ->and(ShipmentStatus::PARTIAL->getLabel())->toBe('Partial Delivery')
            ->and(ShipmentStatus::FAILED->getLabel())->toBe('Failed');
    });

    it('detects terminal statuses', function (): void {
        expect(ShipmentStatus::DELIVERED->isTerminal())->toBeTrue()
            ->and(ShipmentStatus::FAILED->isTerminal())->toBeTrue()
            ->and(ShipmentStatus::PENDING->isTerminal())->toBeFalse()
            ->and(ShipmentStatus::IN_TRANSIT->isTerminal())->toBeFalse();
    });

    it('allows quantity tracking only for delivery statuses', function (): void {
        expect(ShipmentStatus::DELIVERED->allowsQuantityTracking())->toBeTrue()
            ->and(ShipmentStatus::PARTIAL->allowsQuantityTracking())->toBeTrue()
            ->and(ShipmentStatus::PENDING->allowsQuantityTracking())->toBeFalse()
            ->and(ShipmentStatus::IN_TRANSIT->allowsQuantityTracking())->toBeFalse();
    });

    it('validates allowed transitions', function (): void {
        expect(ShipmentStatus::PENDING->canTransitionTo(ShipmentStatus::IN_TRANSIT))->toBeTrue()
            ->and(ShipmentStatus::PENDING->canTransitionTo(ShipmentStatus::DELIVERED))->toBeFalse()
            ->and(ShipmentStatus::IN_TRANSIT->canTransitionTo(ShipmentStatus::DELIVERED))->toBeTrue()
            ->and(ShipmentStatus::IN_TRANSIT->canTransitionTo(ShipmentStatus::PARTIAL))->toBeTrue()
            ->and(ShipmentStatus::DELIVERED->canTransitionTo(ShipmentStatus::PENDING))->toBeFalse();
    });
});

describe('ItemCondition Enum', function (): void {
    it('has correct labels', function (): void {
        expect(ItemCondition::GOOD->getLabel())->toBe('Good')
            ->and(ItemCondition::DAMAGED->getLabel())->toBe('Damaged')
            ->and(ItemCondition::REJECTED->getLabel())->toBe('Rejected');
    });

    it('identifies acceptable conditions', function (): void {
        expect(ItemCondition::GOOD->isAcceptable())->toBeTrue()
            ->and(ItemCondition::DAMAGED->isAcceptable())->toBeFalse()
            ->and(ItemCondition::REJECTED->isAcceptable())->toBeFalse();
    });

    it('identifies conditions requiring notes', function (): void {
        expect(ItemCondition::GOOD->requiresNotes())->toBeFalse()
            ->and(ItemCondition::DAMAGED->requiresNotes())->toBeTrue()
            ->and(ItemCondition::REJECTED->requiresNotes())->toBeTrue();
    });
});

describe('Shipment DO Number Generation', function (): void {
    it('generates DO number with correct format', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $doNumber = $shipment->generateDoNumber();

        // Format: {4digit}-CP/DO/{roman_month}/{year}
        expect($doNumber)->toMatch('/^\d{4}-CP\/DO\/[IVX]+\/\d{4}$/')
            ->and($shipment->do_number)->toBe($doNumber);
    });

    it('uses roman numeral for current month', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $doNumber = $shipment->generateDoNumber();
        $currentMonth = (int) now()->format('n');
        $romanMonth = match ($currentMonth) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        };

        expect($doNumber)->toContain($romanMonth);
    });

    it('uses current year in DO number', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $doNumber = $shipment->generateDoNumber();
        $currentYear = now()->format('Y');

        expect($doNumber)->toContain($currentYear);
    });

    it('increments DO numbers sequentially for same team/month/year', function (): void {
        $shipment1 = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $shipment2 = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $doNumber1 = $shipment1->generateDoNumber();
        $doNumber2 = $shipment2->generateDoNumber();

        // Extract increment numbers
        preg_match('/^(\d{4})-CP\/DO\//', $doNumber1, $matches1);
        preg_match('/^(\d{4})-CP\/DO\//', $doNumber2, $matches2);

        $increment1 = (int) $matches1[1];
        $increment2 = (int) $matches2[1];

        expect($increment2)->toBe($increment1 + 1);
    });

    it('returns cached DO number if already generated', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create();

        $doNumber1 = $shipment->generateDoNumber();
        $doNumber2 = $shipment->getDoNumber();

        expect($doNumber1)->toBe($doNumber2)
            ->and($shipment->do_number)->toBe($doNumber1);
    });

    it('auto-generates DO number when accessed if not set', function (): void {
        $shipment = Shipment::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->inbound()
            ->create([
                'do_number' => null,
            ]);

        $doNumber = $shipment->getDoNumber();

        expect($doNumber)->toMatch('/^\d{4}-CP\/DO\/[IVX]+\/\d{4}$/')
            ->and($shipment->do_number)->toBeNull(); // getDoNumber doesn't save, only generateDoNumber does
    });
});
