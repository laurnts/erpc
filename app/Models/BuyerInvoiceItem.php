<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsErpActivity;
use App\Models\Concerns\StampsParentOnActivity;
use Database\Factories\BuyerInvoiceItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buyer_invoice_id
 * @property int|null $buyer_order_item_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property int|null $tax_code_id
 * @property string $tax_rate
 * @property bool $tax_inclusive
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BuyerInvoice $buyerInvoice
 * @property-read BuyerOrderItem|null $buyerOrderItem
 * @property-read RequestItem|null $requestItem
 * @property-read Article|null $article
 * @property-read TaxCode|null $taxCode
 * @property-read float $unit_price_exc_tax
 * @property-read float $tax_amount_per_unit
 */
final class BuyerInvoiceItem extends Model
{
    /** @use HasFactory<BuyerInvoiceItemFactory> */
    use HasFactory;

    use LogsErpActivity, StampsParentOnActivity {
        StampsParentOnActivity::isLogEmpty insteadof LogsErpActivity;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_invoice_id',
        'buyer_order_item_id',
        'request_item_id',
        'article_id',
        'description',
        'quantity',
        'unit',
        'unit_of_measure_id',
        'unit_price',
        'tax_code_id',
        'tax_rate',
        'tax_inclusive',
        'line_subtotal',
        'line_tax',
        'line_total',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => '1.0000',
        'unit_price' => '0.0000',
        'tax_rate' => '0.0000',
        'tax_inclusive' => false,
        'line_subtotal' => '0.0000',
        'line_tax' => '0.0000',
        'line_total' => '0.0000',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_inclusive' => 'boolean',
            'line_subtotal' => 'decimal:4',
            'line_tax' => 'decimal:4',
            'line_total' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return [
            'quantity',
            'unit_price',
            'tax_rate',
            'tax_code_id',
            'tax_inclusive',
            'unit_of_measure_id',
            'unit',
            'article_id',
            'line_total',
        ];
    }

    protected function activityParentAlias(): string
    {
        return 'buyer_invoice';
    }

    protected function activityParentIdColumn(): string
    {
        return 'buyer_invoice_id';
    }

    /**
     * The buyer invoice this item belongs to.
     *
     * @return BelongsTo<BuyerInvoice, $this>
     */
    public function buyerInvoice(): BelongsTo
    {
        return $this->belongsTo(BuyerInvoice::class);
    }

    /**
     * The source buyer order item this was created from.
     *
     * @return BelongsTo<BuyerOrderItem, $this>
     */
    public function buyerOrderItem(): BelongsTo
    {
        return $this->belongsTo(BuyerOrderItem::class);
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
     * The tax code applied to this item.
     *
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    /**
     * Calculate unit price excluding tax.
     *
     * @return Attribute<float, never>
     */
    protected function unitPriceExcTax(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $unitPrice = (float) $this->unit_price;
                $taxRate = (float) $this->tax_rate;

                if ($this->tax_inclusive && $taxRate > 0) {
                    return $unitPrice / (1 + ($taxRate / 100));
                }

                return $unitPrice;
            },
        );
    }

    /**
     * Calculate tax amount per unit.
     *
     * @return Attribute<float, never>
     */
    protected function taxAmountPerUnit(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $unitPriceExcTax = $this->unit_price_exc_tax;
                $taxRate = (float) $this->tax_rate;

                return $unitPriceExcTax * ($taxRate / 100);
            },
        );
    }

    /**
     * Calculate line totals before saving.
     */
    public function calculateTotals(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxRate = (float) $this->tax_rate;

        if ($this->tax_inclusive) {
            // Price includes tax, calculate backwards
            $unitPriceExcTax = $taxRate > 0 ? $unitPrice / (1 + ($taxRate / 100)) : $unitPrice;
            $lineSubtotal = $quantity * $unitPriceExcTax;
            $lineTax = $quantity * $unitPrice - $lineSubtotal;
            $lineTotal = $quantity * $unitPrice;
        } else {
            // Price excludes tax
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;
        }

        $this->line_subtotal = (string) round($lineSubtotal, 4);
        $this->line_tax = (string) round($lineTax, 4);
        $this->line_total = (string) round($lineTotal, 4);
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
     * Get the unit label (from UnitOfMeasure or fallback to unit string).
     */
    public function getUnitLabelAttribute(): string
    {
        if ($this->unitOfMeasure !== null) {
            return $this->unitOfMeasure->label;
        }

        // Fallback to unit string
        if ($this->unit !== null) {
            return (string) $this->unit;
        }

        return '—';
    }

    /**
     * Create from a buyer order item with locked values.
     */
    public static function createFromOrderItem(BuyerInvoice $buyerInvoice, BuyerOrderItem $orderItem): self
    {
        $item = new self;
        $item->buyer_invoice_id = $buyerInvoice->getKey();
        $item->buyer_order_item_id = $orderItem->getKey();
        $item->request_item_id = $orderItem->request_item_id;
        $item->article_id = $orderItem->article_id;
        $item->description = $orderItem->description;
        $item->quantity = $orderItem->quantity;
        $item->unit_of_measure_id = $orderItem->unit_of_measure_id;

        // Ensure unit is set from unit_of_measure_id or order item's unit
        if ($orderItem->unit_of_measure_id !== null) {
            $unitOfMeasure = \App\Models\UnitOfMeasure::find($orderItem->unit_of_measure_id);
            if ($unitOfMeasure !== null) {
                $item->unit = $unitOfMeasure->code;
            } else {
                // Fallback to order item's unit
                $orderUnit = $orderItem->unit;
                $item->unit = $orderUnit instanceof \App\Enums\Unit ? $orderUnit->value : ($orderUnit ?? 'pcs');
            }
        } else {
            // Fallback to order item's unit
            $orderUnit = $orderItem->unit;
            $item->unit = $orderUnit instanceof \App\Enums\Unit ? $orderUnit->value : ($orderUnit ?? 'pcs');
        }

        // Lock pricing from order
        $item->unit_price = (string) round((float) $orderItem->unit_price, 4);
        $item->tax_code_id = $orderItem->tax_code_id;
        $item->tax_rate = $orderItem->tax_rate;
        $item->tax_inclusive = $orderItem->is_tax_inclusive;

        // Calculate and lock totals
        $item->calculateTotals();

        $item->sort_order = $orderItem->sort_order;

        $item->save();

        return $item;
    }
}
