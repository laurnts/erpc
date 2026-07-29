<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Enums\Erp\PriceBasis;
use App\Enums\Unit;
use App\Models\Concerns\LogsErpActivity;
use App\Models\Concerns\StampsParentOnActivity;
use App\Observers\SupplierQuoteItemObserver;
use App\Services\Erp\Financial\LineCalculator;
use App\Support\Money;
use Database\Factories\SupplierQuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $supplier_quote_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property int|null $unit_of_measure_id
 * @property string $description
 * @property string $quantity
 * @property Unit $unit
 * @property string $unit_price
 * @property string $unit_price_exc_tax
 * @property int|null $tax_code_id
 * @property bool $is_tax_inclusive
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property bool $is_selected
 * @property int $sort_order
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SupplierQuote|null $supplierQuote
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

    use LogsErpActivity, StampsParentOnActivity {
        StampsParentOnActivity::isLogEmpty insteadof LogsErpActivity;
    }

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
        'unit_of_measure_id',
        'unit_price',
        'unit_price_exc_tax',
        'tax_code_id',
        'is_tax_inclusive',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_tax',
        'line_total',
        'is_selected',
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
        'is_selected' => false,
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
            'unit_price' => 'decimal:4',
            'unit_price_exc_tax' => 'decimal:4',
            'is_tax_inclusive' => 'boolean',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'line_tax' => 'decimal:4',
            'line_total' => 'decimal:4',
            'is_selected' => 'boolean',
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
            'is_tax_inclusive',
            'unit_of_measure_id',
            'unit',
            'article_id',
            'line_total',
            'is_selected',
        ];
    }

    protected function activityParentAlias(): string
    {
        return 'supplier_quote';
    }

    protected function activityParentIdColumn(): string
    {
        return 'supplier_quote_id';
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
     * The unit of measure for this item.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
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
        // Default to taxable if the supplier record can't be resolved.
        $supplier = $this->supplierQuote?->supplier;
        $isSupplierTaxable = $supplier->is_taxable ?? true;

        // A non-taxable supplier carries no tax; clear any stale tax metadata.
        if (! $isSupplierTaxable) {
            $this->tax_rate = '0.0000';
            $this->tax_code_id = null;
            $this->is_tax_inclusive = false;
        }

        // Supplier prices may be entered gross (tax-inclusive) or net; map the
        // stored flag onto the shared calculator's explicit price basis.
        // Supplier documents keep four decimals — this was currencyDecimals: 4.
        $currency = $this->supplierQuote?->currency->code ?? 'IDR';

        /** @var numeric-string $taxRate */
        $taxRate = (string) $this->tax_rate;

        /** @var numeric-string $quantity */
        $quantity = (string) $this->quantity;

        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: Money::fromDecimal($this->unit_price, $currency),
            priceBasis: $this->is_tax_inclusive ? PriceBasis::GROSS : PriceBasis::NET,
            taxable: $isSupplierTaxable && bccomp($taxRate, '0', Money::SCALE) === 1,
            taxRate: $taxRate,
            quantity: $quantity,
            roundingScale: 4,
        );

        $this->unit_price_exc_tax = $amounts->unitPriceExcTax->toDecimal();
        $this->line_subtotal = $amounts->lineSubtotal->toDecimal();
        $this->line_tax = $amounts->lineTax->toDecimal();
        $this->line_total = $amounts->lineTotal->toDecimal();
        $this->tax_amount = $amounts->taxAmountPerUnit->toDecimal();
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

    public function isChildItem(): bool
    {
        return $this->requestItem?->parent_id !== null;
    }

    /**
     * Quote items as main item followed by its child/detail items.
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
