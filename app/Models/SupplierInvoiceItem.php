<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupplierInvoiceItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int|null $supplier_order_item_id
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
 * @property-read SupplierInvoice $supplierInvoice
 * @property-read SupplierOrderItem|null $supplierOrderItem
 * @property-read RequestItem|null $requestItem
 * @property-read Article|null $article
 * @property-read TaxCode|null $taxCode
 * @property-read string $formatted_unit_price
 * @property-read string $formatted_line_total
 * @property-read float $unit_price_exc_tax
 * @property-read float $tax_amount
 */
final class SupplierInvoiceItem extends Model
{
    /** @use HasFactory<SupplierInvoiceItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_invoice_id',
        'supplier_order_item_id',
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
        'unit' => 'pcs',
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
     * The supplier invoice this item belongs to.
     *
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /**
     * The source supplier order item (if linked).
     *
     * @return BelongsTo<SupplierOrderItem, $this>
     */
    public function supplierOrderItem(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderItem::class);
    }

    /**
     * The original request item this invoice item is for.
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
     * The article for this invoice item.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * The tax code for this invoice item.
     *
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    /**
     * Get formatted unit price.
     *
     * @return Attribute<string, never>
     */
    protected function formattedUnitPrice(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $currency = $this->supplierInvoice->currency;
                if ($currency === null) {
                    return number_format((float) $this->unit_price, 2);
                }

                return $currency->format((float) $this->unit_price);
            },
        );
    }

    /**
     * Get formatted line total.
     *
     * @return Attribute<string, never>
     */
    protected function formattedLineTotal(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $currency = $this->supplierInvoice->currency;
                if ($currency === null) {
                    return number_format((float) $this->line_total, 2);
                }

                return $currency->format((float) $this->line_total);
            },
        );
    }

    /**
     * Get unit price excluding tax.
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
                    return round($unitPrice / (1 + $taxRate / 100), 4);
                }

                return $unitPrice;
            },
        );
    }

    /**
     * Get tax amount per unit.
     *
     * @return Attribute<float, never>
     */
    protected function taxAmount(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $unitPrice = (float) $this->unit_price;
                $taxRate = (float) $this->tax_rate;

                if ($this->tax_inclusive) {
                    $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);

                    return round($unitPrice - $unitPriceExcTax, 4);
                }

                return round($unitPrice * $taxRate / 100, 4);
            },
        );
    }

    /**
     * Calculate line total and update line amounts.
     */
    public function calculateLineTotal(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxRate = (float) $this->tax_rate;

        $lineAmount = $quantity * $unitPrice;

        if ($this->tax_inclusive) {
            // Unit price includes tax
            $unitPriceExcTax = $unitPrice / (1 + $taxRate / 100);
            $lineSubtotal = $quantity * $unitPriceExcTax;
            $lineTax = $lineAmount - $lineSubtotal;
            $lineTotal = $lineAmount;
        } else {
            // Unit price excludes tax
            $lineSubtotal = $lineAmount;
            $lineTax = $lineAmount * $taxRate / 100;
            $lineTotal = $lineSubtotal + $lineTax;
        }

        $this->line_subtotal = (string) round($lineSubtotal, 4);
        $this->line_tax = (string) round($lineTax, 4);
        $this->line_total = (string) round($lineTotal, 4);
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
}
