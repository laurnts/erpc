<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Models\Concerns\LogsErpActivity;
use App\Observers\ShipmentObserver;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use App\Support\RomanNumerals;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int $request_id
 * @property ShipmentType $type
 * @property ShipmentStatus $status
 * @property int|null $supplier_order_id
 * @property int|null $buyer_order_id
 * @property string $shipment_number
 * @property string|null $do_number
 * @property string|null $carrier_name
 * @property string|null $tracking_number
 * @property Carbon|null $shipped_at
 * @property Carbon|null $expected_delivery_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $do_sent_at
 * @property string|null $notes
 * @property int|null $pic_contact_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $is_inbound
 * @property-read bool $is_outbound
 * @property-read bool $is_delivered
 * @property-read float $total_quantity_shipped
 * @property-read float $total_quantity_received
 * @property-read Request $request
 * @property-read People|null $picContact
 * @property-read SupplierOrder|null $supplierOrder
 * @property-read BuyerOrder|null $buyerOrder
 */
#[ObservedBy(ShipmentObserver::class)]
final class Shipment extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use LogsErpActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'type',
        'status',
        'supplier_order_id',
        'buyer_order_id',
        'shipment_number',
        'do_number',
        'carrier_name',
        'tracking_number',
        'shipped_at',
        'expected_delivery_at',
        'delivered_at',
        'do_sent_at',
        'notes',
        'pic_contact_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => ShipmentType::INBOUND,
        'status' => ShipmentStatus::PENDING,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShipmentType::class,
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'do_sent_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return [
            'shipment_number',
            'type',
            'status',
            'do_number',
            'carrier_name',
            'tracking_number',
            'shipped_at',
            'expected_delivery_at',
            'delivered_at',
            'do_sent_at',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('shipping_doc')
            ->useDisk('private');

        $this->addMediaCollection('pod')
            ->useDisk('private');
    }

    /**
     * The request this shipment is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Scope a query to shipments whose request belongs to a buyer company.
     * Portal surfaces must use this scope instead of inline buyer_id clauses.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forBuyerCompany(Builder $query, int $buyerCompanyId): Builder
    {
        return $query->whereHas('request', fn (Builder $requestQuery) => $requestQuery->where('buyer_id', $buyerCompanyId));
    }

    /**
     * PIC (Person In Charge) contact at the buyer.
     *
     * @return BelongsTo<People, $this>
     */
    public function picContact(): BelongsTo
    {
        return $this->belongsTo(People::class, 'pic_contact_id');
    }

    /**
     * The supplier order for inbound shipments.
     *
     * @return BelongsTo<SupplierOrder, $this>
     */
    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    /**
     * The buyer order for outbound shipments.
     *
     * @return BelongsTo<BuyerOrder, $this>
     */
    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(BuyerOrder::class);
    }

    /**
     * The items in this shipment.
     *
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class)->orderBy('sort_order');
    }

    /**
     * Check if this is an inbound shipment.
     *
     * @return Attribute<bool, never>
     */
    protected function isInbound(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->type === ShipmentType::INBOUND,
        );
    }

    /**
     * Check if this is an outbound shipment.
     *
     * @return Attribute<bool, never>
     */
    protected function isOutbound(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->type === ShipmentType::OUTBOUND,
        );
    }

    /**
     * Check if the shipment has been delivered.
     *
     * @return Attribute<bool, never>
     */
    protected function isDelivered(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status === ShipmentStatus::DELIVERED,
        );
    }

    /**
     * Get total quantity shipped.
     *
     * @return Attribute<float, never>
     */
    protected function totalQuantityShipped(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->items()->sum('quantity_shipped'),
        );
    }

    /**
     * Get total quantity received.
     *
     * @return Attribute<float, never>
     */
    protected function totalQuantityReceived(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->items()->sum('quantity_received'),
        );
    }

    /**
     * Mark the shipment as in transit.
     */
    public function markAsInTransit(?string $trackingNumber = null, ?Carbon $expectedDelivery = null): void
    {
        if (! $this->status->canTransitionTo(ShipmentStatus::IN_TRANSIT)) {
            throw new \InvalidArgumentException('Cannot transition to in transit from current status.');
        }

        $this->status = ShipmentStatus::IN_TRANSIT;
        $this->shipped_at = now();

        if ($trackingNumber !== null) {
            $this->tracking_number = $trackingNumber;
        }

        if ($expectedDelivery instanceof \Illuminate\Support\Carbon) {
            $this->expected_delivery_at = $expectedDelivery;
        }

        $this->save();
    }

    /**
     * Mark the shipment as delivered.
     */
    public function markAsDelivered(?Carbon $deliveredAt = null): void
    {
        if (! $this->status->canTransitionTo(ShipmentStatus::DELIVERED)) {
            throw new \InvalidArgumentException('Cannot transition to delivered from current status.');
        }

        $this->status = ShipmentStatus::DELIVERED;
        $this->delivered_at = $deliveredAt ?? now();
        $this->save();
    }

    /**
     * Mark the shipment as partially delivered.
     */
    public function markAsPartial(): void
    {
        if (! $this->status->canTransitionTo(ShipmentStatus::PARTIAL)) {
            throw new \InvalidArgumentException('Cannot transition to partial from current status.');
        }

        $this->status = ShipmentStatus::PARTIAL;
        $this->save();
    }

    /**
     * Mark the shipment as failed.
     */
    public function markAsFailed(?string $reason = null): void
    {
        if (! $this->status->canTransitionTo(ShipmentStatus::FAILED)) {
            throw new \InvalidArgumentException('Cannot transition to failed from current status.');
        }

        $this->status = ShipmentStatus::FAILED;

        if ($reason !== null) {
            $this->notes = ($this->notes !== null ? $this->notes."\n" : '').'Failure reason: '.$reason;
        }

        $this->save();
    }

    /**
     * Get the associated order based on shipment type.
     */
    public function getOrder(): SupplierOrder|BuyerOrder|null
    {
        return $this->is_inbound ? $this->supplierOrder : $this->buyerOrder;
    }

    /**
     * Get the display text for the shipment.
     */
    public function getDisplayTextAttribute(): string
    {
        return sprintf('%s - %s (%s)', $this->shipment_number, $this->type->getLabel(), $this->status->getLabel());
    }

    /**
     * Check if all items have been received (quantity_received matches quantity_shipped).
     */
    public function allItemsReceived(): bool
    {
        return $this->items()
            ->whereColumn('quantity_received', '<', 'quantity_shipped')
            ->doesntExist();
    }

    /**
     * Check if any items have issues (damaged or rejected).
     */
    public function hasItemsWithIssues(): bool
    {
        return $this->items()
            ->whereIn('condition', ['damaged', 'rejected'])
            ->exists();
    }

    /**
     * Get quantity comparison summary.
     *
     * @return array{shipped: float, received: float, difference: float, percentage: float}
     */
    public function getQuantitySummary(): array
    {
        $shipped = $this->total_quantity_shipped;
        $received = $this->total_quantity_received;
        $difference = $shipped - $received;
        $percentage = $shipped > 0 ? ($received / $shipped) * 100 : 0;

        return [
            'shipped' => $shipped,
            'received' => $received,
            'difference' => $difference,
            'percentage' => round($percentage, 2),
        ];
    }

    /**
     * Generate Delivery Order number.
     * Format: {4digit_increment}-CP/DO/{roman_month}/{year}
     *
     * The counter is per team, per month: the period string pairs the roman
     * month with the year (e.g. "VII/2026") so it matches exactly what ends
     * up in the number itself, letting the backfill extract it with one regex
     * group instead of reassembling it from separate pieces.
     */
    public function generateDoNumber(): string
    {
        $month = (int) now()->format('n');
        $year = (int) now()->format('Y');
        $romanMonth = RomanNumerals::month($month);

        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $this->team_id, 'shipment_do', $romanMonth.'/'.$year);

        $doNumber = sprintf('%04d-CP/DO/%s/%d', $sequence, $romanMonth, $year);
        $this->do_number = $doNumber;
        $this->save();

        return $doNumber;
    }

    /**
     * Get DO number, generating if not set (without saving).
     */
    public function getDoNumber(): string
    {
        if ($this->do_number !== null && $this->do_number !== '') {
            return $this->do_number;
        }

        // Generate but don't save yet (will be saved when PDF is generated)
        $month = (int) now()->format('n');
        $year = (int) now()->format('Y');
        $romanMonth = RomanNumerals::month($month);

        $pattern = sprintf('%%-CP/DO/%s/%d', $romanMonth, $year);
        $existingDoNumbers = self::query()
            ->withTrashed()
            ->where('team_id', $this->team_id)
            ->where('do_number', 'like', $pattern)
            ->whereNotNull('do_number')
            ->pluck('do_number')
            ->toArray();

        $nextIncrement = 1;
        if (! empty($existingDoNumbers)) {
            $regex = '/^(\d{4})-CP\/DO\/'.preg_quote($romanMonth, '/').'\/'.$year.'$/';
            foreach ($existingDoNumbers as $doNumber) {
                if (preg_match($regex, (string) $doNumber, $matches)) {
                    $increment = (int) $matches[1];
                    if ($increment >= $nextIncrement) {
                        $nextIncrement = $increment + 1;
                    }
                }
            }
        }

        return sprintf('%04d-CP/DO/%s/%d', $nextIncrement, $romanMonth, $year);
    }
}
