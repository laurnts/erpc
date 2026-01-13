<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\BuyerQuoteItemObserver;
use App\Services\Erp\TaxCalculationService;
use Database\Factories\BuyerQuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buyer_quote_id
 * @property int|null $request_item_id
 * @property int|null $article_id
 * @property int|null $supplier_quote_item_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
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
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
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
     * Calculate the margin percentage based on cost price.
     *
     * @return Attribute<float, never>
     */
    protected function calculatedMarginPercent(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $costPrice = (float) $this->cost_price;
                if ($costPrice <= 0) {
                    return 0.0;
                }

                $marginAmount = (float) $this->unit_price_exc_tax - $costPrice;

                return round(($marginAmount / $costPrice) * 100, 2);
            },
        );
    }

    /**
     * Recalculate all price-related fields based on current values.
     */
    public function recalculatePrices(): void
    {
        $taxService = app(TaxCalculationService::class);

        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxRate = (float) $this->tax_rate;
        $isInclusive = $this->is_tax_inclusive;

        // Calculate unit price excluding tax
        if ($isInclusive) {
            $this->unit_price_exc_tax = (string) $taxService->calculatePriceWithoutTax($unitPrice, $taxRate);
        } else {
            $this->unit_price_exc_tax = (string) $unitPrice;
        }

        // Calculate line totals using tax service
        $lineResult = $taxService->calculateLineTotal($quantity, $unitPrice, $taxRate, $isInclusive);

        $this->line_subtotal = (string) $lineResult['subtotal'];
        $this->line_tax = (string) $lineResult['tax_amount'];
        $this->line_total = (string) $lineResult['total'];
        $this->tax_amount = (string) ($lineResult['tax_amount'] / max($quantity, 0.0001));

        // Calculate margin
        $costPrice = (float) $this->cost_price;
        $unitPriceExcTax = (float) $this->unit_price_exc_tax;
        $this->margin_amount = (string) ($unitPriceExcTax - $costPrice);

        if ($costPrice > 0) {
            $this->margin_percent = (string) round((($unitPriceExcTax - $costPrice) / $costPrice) * 100, 4);
        } else {
            $this->margin_percent = '0.0000';
        }
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
}
