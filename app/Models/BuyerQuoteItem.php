<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Enums\Erp\PriceBasis;
use App\Enums\Unit;
use App\Observers\BuyerQuoteItemObserver;
use App\Services\Erp\Financial\DocumentTotals;
use App\Services\Erp\Financial\LineCalculator;
use App\Services\Erp\Financial\MarginConvention;
use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
use Database\Factories\BuyerQuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $buyer_quote_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property int|null $supplier_quote_item_id
 * @property string $description
 * @property string $quantity
 * @property Unit $unit
 * @property string $cost_price
 * @property string $unit_price
 * @property string $unit_price_exc_tax
 * @property string $margin_amount
 * @property string $margin_percent
 * @property int|null $tax_code_id
 * @property bool $is_tax_inclusive
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property int $sort_order
 * @property string|null $notes
 * @property bool $hide_from_pdf
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BuyerQuote $buyerQuote
 * @property-read RequestItem|null $requestItem
 * @property-read Article|null $article
 * @property-read TaxCode|null $taxCode
 * @property-read float $calculated_margin_amount
 * @property-read float $calculated_margin_percent
 */
#[ObservedBy(BuyerQuoteItemObserver::class)]
final class BuyerQuoteItem extends Model
{
    /** @use HasFactory<BuyerQuoteItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_quote_id',
        'request_item_id',
        'article_id',
        'supplier_quote_item_id',
        'description',
        'quantity',
        'unit',
        'unit_of_measure_id',
        'cost_price',
        'unit_price',
        'unit_price_exc_tax',
        'margin_amount',
        'margin_percent',
        'tax_code_id',
        'is_tax_inclusive',
        'tax_rate',
        'tax_amount',
        'line_subtotal',
        'line_tax',
        'line_total',
        'sort_order',
        'notes',
        'hide_from_pdf',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => '1.0000',
        'unit' => 'pcs',
        'cost_price' => '0.0000',
        'unit_price' => '0.0000',
        'unit_price_exc_tax' => '0.0000',
        'margin_amount' => '0.0000',
        'margin_percent' => '0.0000',
        'is_tax_inclusive' => false,
        'tax_rate' => '0.0000',
        'tax_amount' => '0.0000',
        'line_subtotal' => '0.0000',
        'line_tax' => '0.0000',
        'line_total' => '0.0000',
        'sort_order' => 0,
        'hide_from_pdf' => false,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => SafeUnitCast::class,
            'cost_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'unit_price_exc_tax' => 'decimal:4',
            'margin_amount' => 'decimal:4',
            'margin_percent' => 'decimal:4',
            'is_tax_inclusive' => 'boolean',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'line_tax' => 'decimal:4',
            'line_total' => 'decimal:4',
            'sort_order' => 'integer',
            'hide_from_pdf' => 'boolean',
        ];
    }

    /**
     * The buyer quote this item belongs to.
     *
     * @return BelongsTo<BuyerQuote, $this>
     */
    public function buyerQuote(): BelongsTo
    {
        return $this->belongsTo(BuyerQuote::class);
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
     * The source supplier quote item (if created from a supplier quote).
     *
     * @return BelongsTo<SupplierQuoteItem, $this>
     */
    public function supplierQuoteItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteItem::class);
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
     * Calculate the margin amount (selling price - cost price) per unit.
     *
     * @return Attribute<float, never>
     */
    protected function calculatedMarginAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->unit_price_exc_tax - (float) $this->cost_price,
        );
    }

    /**
     * Calculate the margin percentage based on selling price (margin on selling).
     *
     * @return Attribute<float, never>
     */
    protected function calculatedMarginPercent(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $unitPriceExcTax = (float) $this->unit_price_exc_tax;
                if ($unitPriceExcTax <= 0) {
                    return 0.0;
                }

                $marginAmount = (float) $this->unit_price_exc_tax - (float) $this->cost_price;

                return round(($marginAmount / $unitPriceExcTax) * 100, 2);
            },
        );
    }

    /**
     * Recalculate all price-related fields based on current values.
     */
    public function recalculatePrices(): void
    {
        // Buyer items: unit_price is always the net (ex-tax) price, and the
        // is_tax_inclusive "+ Tax" checkbox decides whether tax is added on top.
        $taxRate = (float) $this->tax_rate;

        $amounts = (new LineCalculator)->calculate(
            unitPriceInput: (float) $this->unit_price,
            priceBasis: PriceBasis::NET,
            taxable: $this->is_tax_inclusive && $taxRate > 0,
            taxRate: $taxRate,
            quantity: (float) $this->quantity,
            currencyDecimals: 0,
        );

        $this->unit_price_exc_tax = (string) $amounts->unitPriceExcTax;
        $this->line_subtotal = (string) $amounts->lineSubtotal;
        $this->line_tax = (string) $amounts->lineTax;
        $this->line_total = (string) $amounts->lineTotal;
        $this->tax_amount = (string) $amounts->taxAmountPerUnit;

        $costPrice = (float) $this->cost_price;
        $this->margin_amount = (string) round($amounts->unitPriceExcTax - $costPrice, 0);
        $this->margin_percent = (string) round(
            MarginConvention::marginPercent($costPrice, $amounts->unitPriceExcTax),
            4,
        );
    }

    /**
     * Update tax rate from tax code if set.
     */
    public function updateTaxRateFromCode(): void
    {
        if ($this->tax_code_id !== null) {
            $taxCode = $this->taxCode;
            if ($taxCode !== null) {
                $this->tax_rate = (string) $taxCode->rate;
            }
        }
    }

    /**
     * Create from a supplier quote item with markup.
     */
    public static function createFromSupplierQuoteItem(
        BuyerQuote $buyerQuote,
        Model $supplierQuoteItem,
        float $markupPercent = 0,
    ): self {
        $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
        $sellingPrice = $markupPercent > 0
            ? $costPrice * (1 + $markupPercent / 100)
            : $costPrice;

        $item = new self;
        $item->buyer_quote_id = $buyerQuote->getKey();
        $item->supplier_quote_item_id = $supplierQuoteItem->getKey();
        $item->request_item_id = $supplierQuoteItem->request_item_id ?? null;
        $item->article_id = $supplierQuoteItem->article_id ?? null;
        $item->description = $supplierQuoteItem->description;
        $item->quantity = $supplierQuoteItem->quantity;
        $item->unit = $supplierQuoteItem->unit;
        $item->cost_price = (string) $costPrice;
        $item->unit_price = (string) $sellingPrice;
        $item->tax_code_id = $supplierQuoteItem->tax_code_id ?? null;
        $item->is_tax_inclusive = $supplierQuoteItem->is_tax_inclusive ?? false;
        $item->tax_rate = $supplierQuoteItem->tax_rate ?? '0.0000';
        $item->sort_order = $supplierQuoteItem->sort_order ?? 0;
        $item->notes = $supplierQuoteItem->notes ?? null;

        $item->recalculatePrices();

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

    public function isChildItem(): bool
    {
        return $this->requestItem?->parent_id !== null;
    }

    /**
     * Line tax for PNL display, derived from stored values when line_tax is missing.
     */
    public function getEffectiveLineTax(): float
    {
        $lineTax = (float) $this->line_tax;
        if ($lineTax > 0) {
            return $lineTax;
        }

        $lineSubtotal = (float) $this->line_subtotal;
        $lineTotal = (float) $this->line_total;

        if ($lineTotal > $lineSubtotal) {
            return $lineTotal - $lineSubtotal;
        }

        if ($lineSubtotal <= 0) {
            return 0.0;
        }

        $taxRate = (float) $this->tax_rate;

        if ($taxRate <= 0 && $this->isChildItem()) {
            $mainItem = self::query()
                ->where('buyer_quote_id', $this->buyer_quote_id)
                ->where('request_item_id', $this->requestItem?->parent_id)
                ->first();

            if ($mainItem !== null && $mainItem->is_tax_inclusive && (float) $mainItem->tax_rate > 0) {
                return round($lineSubtotal * (float) $mainItem->tax_rate / 100, 0);
            }

            return 0.0;
        }

        if ($taxRate <= 0) {
            return 0.0;
        }

        if ($this->is_tax_inclusive || ($this->isChildItem() && $this->tax_code_id !== null)) {
            return round($lineSubtotal * $taxRate / 100, 0);
        }

        return 0.0;
    }

    /**
     * Line total for PNL display, including tax when applicable.
     */
    public function getEffectiveLineTotal(): float
    {
        $lineSubtotal = (float) $this->line_subtotal;
        $lineTotal = (float) $this->line_total;
        $effectiveTax = $this->getEffectiveLineTax();

        if ($effectiveTax > 0 && $lineTotal <= $lineSubtotal) {
            return $lineSubtotal + $effectiveTax;
        }

        return $lineTotal;
    }

    /**
     * Margin % on cost, matching buyer quote form (margin_percent_input uses integer rounding).
     */
    public function getDisplayMarginPercent(): int
    {
        $costPrice = (float) $this->cost_price;
        $unitPriceExcTax = (float) $this->unit_price_exc_tax;

        if ($costPrice <= 0 || $unitPriceExcTax <= 0) {
            return 0;
        }

        return (int) round((($unitPriceExcTax - $costPrice) / $costPrice) * 100);
    }

    /**
     * Items to include in sell/cost totals (main items only for service requests).
     *
     * @param  Collection<int, self>  $items
     * @return Collection<int, self>
     */
    public static function filterForServiceTotals(Collection $items, bool $isServiceRequest): Collection
    {
        if (! $isServiceRequest) {
            return $items;
        }

        return $items->filter(fn (self $item): bool => ! $item->isChildItem());
    }

    /**
     * Aggregate a group of items into P&L totals via the shared collector.
     * The margin base is the net sell (line_subtotal), never the gross line total.
     *
     * @param  Collection<int, self>  $items
     */
    public static function collectTotals(Collection $items, bool $isServiceRequest): DocumentTotals
    {
        $filtered = self::filterForServiceTotals($items, $isServiceRequest);

        return (new TotalsCollector)->collect(
            $filtered->map(fn (self $item): TotalsLine => new TotalsLine(
                lineSubtotal: (float) $item->line_subtotal,
                lineTax: (float) $item->line_tax,
                lineTotal: (float) $item->line_total,
                costPrice: (float) $item->cost_price,
                quantity: (float) $item->quantity,
            ))->values(),
        );
    }

    /**
     * Order items as main item followed by its child/detail items.
     *
     * @param  Collection<int, self>  $items
     * @return Collection<int, array{item: self, is_child: bool}>
     */
    public static function organizeHierarchically(Collection $items): Collection
    {
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
