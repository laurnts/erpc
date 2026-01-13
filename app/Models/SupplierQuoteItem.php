<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\SupplierQuoteItemObserver;
use Database\Factories\SupplierQuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_quote_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $unit_price_exc_tax
 * @property int|null $tax_code_id
 * @property bool $is_tax_inclusive
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property int $sort_order
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SupplierQuote $supplierQuote
 * @property-read RequestItem|null $requestItem
 * @property-read Article|null $article
 * @property-read TaxCode|null $taxCode
 * @property-read string $formatted_unit_price
 * @property-read string $formatted_line_total
 */
#[ObservedBy(SupplierQuoteItemObserver::class)]
final class SupplierQuoteItem extends Model
{
    /** @use HasFactory<SupplierQuoteItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_quote_id',
        'request_item_id',
        'article_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'unit_price_exc_tax',
        'tax_code_id',
        'is_tax_inclusive',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_tax',
        'line_total',
        'sort_order',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => '1.0000',
        'unit' => 'pcs',
        'unit_price' => '0.0000',
        'unit_price_exc_tax' => '0.0000',
        'is_tax_inclusive' => false,
        'tax_rate' => '0.0000',
        'tax_amount' => '0.0000',
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
            'unit_price_exc_tax' => 'decimal:4',
            'is_tax_inclusive' => 'boolean',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'line_tax' => 'decimal:4',
            'line_total' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The supplier quote this item belongs to.
     *
     * @return BelongsTo<SupplierQuote, $this>
     */
    public function supplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class);
    }

    /**
     * The original request item this quote item is for.
     *
     * @return BelongsTo<RequestItem, $this>
     */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    /**
     * The article for this quote item.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * The tax code for this quote item.
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
                $currency = $this->supplierQuote?->currency;
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
                $currency = $this->supplierQuote?->currency;
                if ($currency === null) {
                    return number_format((float) $this->line_total, 2);
                }

                return $currency->format((float) $this->line_total);
            },
        );
    }

    /**
     * Calculate line totals based on current values.
     * This should be called by the observer.
     */
    public function calculateTotals(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxRate = (float) $this->tax_rate;
        $isTaxInclusive = $this->is_tax_inclusive;

        $lineAmount = $quantity * $unitPrice;

        if ($isTaxInclusive) {
            // Unit price includes tax
            $this->line_total = (string) round($lineAmount, 4);
            $this->line_subtotal = (string) round($lineAmount / (1 + $taxRate / 100), 4);
            $this->line_tax = (string) round((float) $this->line_total - (float) $this->line_subtotal, 4);
            $this->unit_price_exc_tax = (string) round($unitPrice / (1 + $taxRate / 100), 4);
            $this->tax_amount = (string) round($unitPrice - (float) $this->unit_price_exc_tax, 4);
        } else {
            // Unit price excludes tax
            $this->line_subtotal = (string) round($lineAmount, 4);
            $this->line_tax = (string) round($lineAmount * $taxRate / 100, 4);
            $this->line_total = (string) round((float) $this->line_subtotal + (float) $this->line_tax, 4);
            $this->unit_price_exc_tax = $this->unit_price;
            $this->tax_amount = (string) round($unitPrice * $taxRate / 100, 4);
        }
    }

    /**
     * Get display text for this item.
     */
    public function getDisplayTextAttribute(): string
    {
        if ($this->article !== null) {
            return sprintf('[%s] %s', $this->article->code, $this->article->name);
        }

        return $this->description;
    }
}
