<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Enums\Unit;
use Database\Factories\BuyerOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $buyer_order_id
 * @property int|null $buyer_quote_item_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property string $description
 * @property string $quantity
 * @property Unit $unit
 * @property int|null $unit_of_measure_id
 * @property string $unit_price
 * @property string $unit_price_exc_tax
 * @property string $tax_amount
 * @property string $line_total
 * @property int|null $tax_code_id
 * @property bool $is_tax_inclusive
 * @property string $tax_rate
 * @property int $sort_order
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BuyerOrder $buyerOrder
 * @property-read BuyerQuoteItem|null $buyerQuoteItem
 * @property-read RequestItem|null $requestItem
 * @property-read Article|null $article
 * @property-read TaxCode|null $taxCode
 * @property-read UnitOfMeasure|null $unitOfMeasure
 */
final class BuyerOrderItem extends Model
{
    /** @use HasFactory<BuyerOrderItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_order_id',
        'buyer_quote_item_id',
        'request_item_id',
        'article_id',
        'description',
        'quantity',
        'unit',
        'unit_of_measure_id',
        'unit_price',
        'unit_price_exc_tax',
        'tax_amount',
        'line_total',
        'tax_code_id',
        'is_tax_inclusive',
        'tax_rate',
        'sort_order',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => '1.0000',
        'unit' => 'pcs',
        'unit_price' => '0.00',
        'unit_price_exc_tax' => '0.00',
        'tax_amount' => '0.00',
        'line_total' => '0.00',
        'is_tax_inclusive' => false,
        'tax_rate' => '0.0000',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => SafeUnitCast::class,
            'unit_price' => 'decimal:2',
            'unit_price_exc_tax' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_tax_inclusive' => 'boolean',
            'tax_rate' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The buyer order this item belongs to.
     *
     * @return BelongsTo<BuyerOrder, $this>
     */
    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(BuyerOrder::class);
    }

    /**
     * The source buyer quote item this was created from.
     *
     * @return BelongsTo<BuyerQuoteItem, $this>
     */
    public function buyerQuoteItem(): BelongsTo
    {
        return $this->belongsTo(BuyerQuoteItem::class);
    }

    /**
     * The original request item this was created from.
     *
     * @return BelongsTo<RequestItem, $this>
     */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    /**
     * The unit of measure for this item.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * The article for this line item.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * The tax code applied to this item (locked from quote).
     *
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    /**
     * Create from a buyer quote item with locked values.
     */
    public static function createFromQuoteItem(BuyerOrder $buyerOrder, BuyerQuoteItem $quoteItem): self
    {
        $item = new self;
        $item->buyer_order_id = $buyerOrder->getKey();
        $item->buyer_quote_item_id = $quoteItem->getKey();
        $item->request_item_id = $quoteItem->request_item_id;
        $item->article_id = $quoteItem->article_id;
        $item->description = $quoteItem->description;
        $item->quantity = $quoteItem->quantity;
        $item->unit_of_measure_id = $quoteItem->unit_of_measure_id;

        // Ensure unit is set from unit_of_measure_id, bypassing SafeUnitCast
        $unitCode = 'pcs'; // Default fallback
        if ($item->unit_of_measure_id !== null) {
            $unitOfMeasure = \App\Models\UnitOfMeasure::find($item->unit_of_measure_id);
            if ($unitOfMeasure !== null) {
                $unitCode = $unitOfMeasure->code;
            } else {
                // Fallback to quote item's unit if UnitOfMeasure not found
                $quoteUnit = $quoteItem->unit;
                $unitCode = $quoteUnit instanceof \App\Enums\Unit ? $quoteUnit->value : ($quoteUnit ?? 'pcs');
            }
        } else {
            // Fallback to quote item's unit
            $quoteUnit = $quoteItem->unit;
            $unitCode = $quoteUnit instanceof \App\Enums\Unit ? $quoteUnit->value : ($quoteUnit ?? 'pcs');
        }

        // Use setRawAttributes to bypass SafeUnitCast and ensure unit is set
        $attributes = $item->getAttributes();
        $item->setRawAttributes(array_merge($attributes, ['unit' => (string) $unitCode]));

        // Lock pricing from quote
        $item->unit_price = (string) round((float) $quoteItem->unit_price, 2);
        $item->unit_price_exc_tax = (string) round((float) $quoteItem->unit_price_exc_tax, 2);
        $item->tax_amount = (string) round((float) $quoteItem->tax_amount, 2);
        $item->line_total = (string) round((float) $quoteItem->line_total, 2);

        // Lock tax fields from quote item
        $item->tax_code_id = $quoteItem->tax_code_id;
        $item->is_tax_inclusive = $quoteItem->is_tax_inclusive;
        $item->tax_rate = $quoteItem->tax_rate;

        $item->sort_order = $quoteItem->sort_order;
        $item->notes = $quoteItem->notes;

        $item->save();

        return $item;
    }

    /**
     * Get the display text for this item.
     */
    public function getDisplayTextAttribute(): string
    {
        if ($this->article !== null) {
            return sprintf('[%s] %s', $this->article->code, $this->article->name);
        }

        return $this->description;
    }

    /**
     * Get the unit label (from UnitOfMeasure or fallback to unit code).
     */
    public function getUnitLabelAttribute(): string
    {
        if ($this->unitOfMeasure !== null) {
            return $this->unitOfMeasure->label;
        }

        // Fallback to unit enum value or raw unit string
        if ($this->unit !== null) {
            return $this->unit instanceof Unit ? $this->unit->value : (string) $this->unit;
        }

        return '—';
    }

    /**
     * Calculate line subtotal (quantity * unit_price_exc_tax).
     */
    public function getLineSubtotalAttribute(): string
    {
        $quantity = (float) $this->quantity;
        $unitPriceExcTax = (float) $this->unit_price_exc_tax;

        return (string) round($quantity * $unitPriceExcTax, 2);
    }

    /**
     * Calculate line tax (quantity * tax_amount).
     */
    public function getLineTaxAttribute(): string
    {
        $quantity = (float) $this->quantity;
        $taxAmount = (float) $this->tax_amount;

        return (string) round($quantity * $taxAmount, 2);
    }

    public function isChildItem(): bool
    {
        return $this->requestItem?->parent_id !== null;
    }

    /**
     * Order items as main item followed by its child/detail items.
     *
     * @param  Collection<int, self>  $items
     * @return Collection<int, array{item: self, is_child: bool}>
     */
    public static function organizeHierarchically(Collection $items): Collection
    {
        $items->loadMissing('requestItem');

        $mainItems = $items
            ->filter(fn (self $item): bool => ! $item->isChildItem())
            ->sortBy('sort_order')
            ->values();

        $childItemsByParentId = $items
            ->filter(fn (self $item): bool => $item->isChildItem())
            ->groupBy(fn (self $item): int => (int) $item->requestItem->parent_id);

        $organized = collect();

        foreach ($mainItems as $mainItem) {
            $organized->push(['item' => $mainItem, 'is_child' => false]);

            $children = $childItemsByParentId->get($mainItem->request_item_id, collect())
                ->sortBy('sort_order')
                ->values();

            foreach ($children as $childItem) {
                $organized->push(['item' => $childItem, 'is_child' => true]);
            }
        }

        return $organized;
    }
}
