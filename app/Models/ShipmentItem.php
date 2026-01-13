<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemCondition;
use Database\Factories\ShipmentItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shipment_id
 * @property int|null $supplier_order_item_id
 * @property int|null $buyer_order_item_id
 * @property string $quantity_shipped
 * @property string|null $quantity_received
 * @property ItemCondition $condition
 * @property string|null $condition_notes
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shipment $shipment
 * @property-read SupplierOrderItem|null $supplierOrderItem
 * @property-read BuyerOrderItem|null $buyerOrderItem
 * @property-read float $quantity_difference
 * @property-read bool $is_fully_received
 * @property-read bool $has_issue
 * @property-read string $display_text
 */
final class ShipmentItem extends Model
{
    /** @use HasFactory<ShipmentItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'supplier_order_item_id',
        'buyer_order_item_id',
        'quantity_shipped',
        'quantity_received',
        'condition',
        'condition_notes',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity_shipped' => '0.0000',
        'condition' => ItemCondition::GOOD,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity_shipped' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'condition' => ItemCondition::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * The shipment this item belongs to.
     *
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * The supplier order item (for inbound shipments).
     *
     * @return BelongsTo<SupplierOrderItem, $this>
     */
    public function supplierOrderItem(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderItem::class);
    }

    /**
     * The buyer order item (for outbound shipments).
     *
     * @return BelongsTo<BuyerOrderItem, $this>
     */
    public function buyerOrderItem(): BelongsTo
    {
        return $this->belongsTo(BuyerOrderItem::class);
    }

    /**
     * Get the associated order item based on shipment type.
     */
    public function getOrderItem(): SupplierOrderItem|BuyerOrderItem|null
    {
        return $this->supplierOrderItem ?? $this->buyerOrderItem;
    }

    /**
     * Get the quantity difference (shipped - received).
     *
     * @return Attribute<float, never>
     */
    protected function quantityDifference(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $shipped = (float) $this->quantity_shipped;
                $received = (float) ($this->quantity_received ?? 0);

                return $shipped - $received;
            },
        );
    }

    /**
     * Check if the item has been fully received.
     *
     * @return Attribute<bool, never>
     */
    protected function isFullyReceived(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->quantity_received === null) {
                    return false;
                }

                return (float) $this->quantity_received >= (float) $this->quantity_shipped;
            },
        );
    }

    /**
     * Check if the item has any issues (damaged or rejected).
     *
     * @return Attribute<bool, never>
     */
    protected function hasIssue(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->condition !== ItemCondition::GOOD,
        );
    }

    /**
     * Get display text for this item.
     *
     * @return Attribute<string, never>
     */
    protected function displayText(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $orderItem = $this->getOrderItem();
                if ($orderItem !== null) {
                    return $orderItem->description;
                }

                return sprintf('Item #%d', $this->sort_order + 1);
            },
        );
    }

    /**
     * Record receipt of this item.
     */
    public function recordReceipt(float $quantityReceived, ItemCondition $condition = ItemCondition::GOOD, ?string $notes = null): void
    {
        $this->quantity_received = (string) $quantityReceived;
        $this->condition = $condition;

        if ($notes !== null) {
            $this->condition_notes = $notes;
        }

        $this->save();
    }

    /**
     * Get the article from the associated order item.
     */
    public function getArticle(): ?Article
    {
        $orderItem = $this->getOrderItem();

        return $orderItem?->article;
    }

    /**
     * Get the description from the associated order item.
     */
    public function getDescription(): string
    {
        $orderItem = $this->getOrderItem();

        return $orderItem->description ?? '';
    }

    /**
     * Get the unit from the associated order item.
     */
    public function getUnit(): string
    {
        $orderItem = $this->getOrderItem();

        return $orderItem->unit ?? 'pcs';
    }

    /**
     * Get receipt status summary.
     *
     * @return array{shipped: float, received: float, difference: float, percentage: float, condition: string, is_complete: bool}
     */
    public function getReceiptSummary(): array
    {
        $shipped = (float) $this->quantity_shipped;
        $received = (float) ($this->quantity_received ?? 0);
        $difference = $shipped - $received;
        $percentage = $shipped > 0 ? ($received / $shipped) * 100 : 0;

        return [
            'shipped' => $shipped,
            'received' => $received,
            'difference' => $difference,
            'percentage' => round($percentage, 2),
            'condition' => $this->condition->getLabel(),
            'is_complete' => $this->is_fully_received && $this->condition === ItemCondition::GOOD,
        ];
    }
}
