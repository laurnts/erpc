<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\SupplierOrderObserver;
use Database\Factories\SupplierOrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int $request_id
 * @property int $supplier_id
 * @property int|null $supplier_quote_id
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string $po_number
 * @property OrderStatus $status
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $base_subtotal
 * @property string $base_tax_total
 * @property string $base_total
 * @property int|null $payment_terms_days
 * @property string|null $payment_terms_text
 * @property Carbon|null $expected_delivery_date
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property Carbon|null $ordered_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read string $formatted_total
 * @property-read string $formatted_base_total
 * @property-read bool $is_editable
 * @property-read bool $is_cancellable
 */
#[ObservedBy(SupplierOrderObserver::class)]
final class SupplierOrder extends Model
{
    use HasCreator;

    /** @use HasFactory<SupplierOrderFactory> */
    use HasFactory;

    use HasTeam;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'supplier_id',
        'supplier_quote_id',
        'currency_id',
        'exchange_rate',
        'po_number',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'base_subtotal',
        'base_tax_total',
        'base_total',
        'payment_terms_days',
        'payment_terms_text',
        'expected_delivery_date',
        'notes',
        'internal_notes',
        'ordered_at',
        'confirmed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::DRAFT,
        'exchange_rate' => '1.00000000',
        'subtotal' => '0.0000',
        'tax_total' => '0.0000',
        'total' => '0.0000',
        'base_subtotal' => '0.0000',
        'base_tax_total' => '0.0000',
        'base_total' => '0.0000',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'base_subtotal' => 'decimal:4',
            'base_tax_total' => 'decimal:4',
            'base_total' => 'decimal:4',
            'payment_terms_days' => 'integer',
            'expected_delivery_date' => 'date',
            'ordered_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The request this order is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The supplier (company) for this order.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    /**
     * The source supplier quote (if created from a quote).
     *
     * @return BelongsTo<SupplierQuote, $this>
     */
    public function supplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class);
    }

    /**
     * The currency for this order.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The items in this order.
     *
     * @return HasMany<SupplierOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderItem::class)->orderBy('sort_order');
    }

    /**
     * Get formatted total in order currency.
     *
     * @return Attribute<string, never>
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $currency = $this->currency;
                if ($currency === null) {
                    return number_format((float) $this->total, 2);
                }

                return $currency->format((float) $this->total);
            },
        );
    }

    /**
     * Get formatted total in base currency.
     *
     * @return Attribute<string, never>
     */
    protected function formattedBaseTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => number_format((float) $this->base_total, 2),
        );
    }

    /**
     * Check if the order can be edited.
     *
     * @return Attribute<bool, never>
     */
    protected function isEditable(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->canEdit(),
        );
    }

    /**
     * Check if the order can be cancelled.
     *
     * @return Attribute<bool, never>
     */
    protected function isCancellable(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->canCancel(),
        );
    }

    /**
     * Recalculate totals from items.
     * Note: Order values are typically locked, so this is mainly for initial creation.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;
            $unitPriceExcTax = (float) $item->unit_price_exc_tax;
            $taxAmount = (float) $item->tax_amount;

            $lineSubtotal = $quantity * $unitPriceExcTax;
            $lineTax = $quantity * $taxAmount;
            $lineTotal = (float) $item->line_total;

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $total += $lineTotal;
        }

        $exchangeRate = (float) $this->exchange_rate;

        $this->subtotal = (string) round($subtotal, 4);
        $this->tax_total = (string) round($taxTotal, 4);
        $this->total = (string) round($total, 4);

        // Calculate base currency values
        $this->base_subtotal = (string) round($subtotal * $exchangeRate, 4);
        $this->base_tax_total = (string) round($taxTotal * $exchangeRate, 4);
        $this->base_total = (string) round($total * $exchangeRate, 4);

        $this->saveQuietly();
    }

    /**
     * Mark the order as confirmed.
     */
    public function confirm(): void
    {
        if (! $this->status->canConfirm()) {
            return;
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmed_at = now();
        $this->save();
    }

    /**
     * Mark the order as cancelled.
     */
    public function cancel(): void
    {
        if (! $this->status->canCancel()) {
            return;
        }

        $this->status = OrderStatus::CANCELLED;
        $this->save();
    }

    /**
     * Mark the order as sent/ordered.
     */
    public function markAsOrdered(): void
    {
        $this->ordered_at = now();

        if ($this->status === OrderStatus::DRAFT) {
            $this->status = OrderStatus::CONFIRMED;
            $this->confirmed_at = now();
        }

        $this->save();
    }

    /**
     * Create a supplier order from a supplier quote.
     */
    public static function createFromQuote(SupplierQuote $quote): self
    {
        $order = new self;
        $order->team_id = $quote->team_id;
        $order->creator_id = auth()->id();
        $order->request_id = $quote->request_id;
        $order->supplier_id = $quote->supplier_id;
        $order->supplier_quote_id = $quote->getKey();
        $order->currency_id = $quote->currency_id;
        $order->exchange_rate = $quote->exchange_rate;
        $order->subtotal = $quote->subtotal;
        $order->tax_total = $quote->tax_total;
        $order->total = $quote->total;
        $order->base_subtotal = $quote->subtotal_base;
        $order->base_tax_total = $quote->tax_total_base;
        $order->base_total = $quote->total_base;
        $order->notes = $quote->notes;
        $order->internal_notes = $quote->internal_notes;
        $order->save();

        // Copy items from quote
        foreach ($quote->items as $quoteItem) {
            SupplierOrderItem::create([
                'supplier_order_id' => $order->getKey(),
                'supplier_quote_item_id' => $quoteItem->getKey(),
                'request_item_id' => $quoteItem->request_item_id,
                'article_id' => $quoteItem->article_id,
                'description' => $quoteItem->description,
                'quantity' => $quoteItem->quantity,
                'unit' => $quoteItem->unit,
                'unit_price' => $quoteItem->unit_price,
                'unit_price_exc_tax' => $quoteItem->unit_price_exc_tax,
                'tax_amount' => $quoteItem->tax_amount,
                'line_total' => $quoteItem->line_total,
                'tax_code_id' => $quoteItem->tax_code_id,
                'is_tax_inclusive' => $quoteItem->is_tax_inclusive,
                'tax_rate' => $quoteItem->tax_rate,
                'sort_order' => $quoteItem->sort_order,
                'notes' => $quoteItem->notes,
            ]);
        }

        return $order;
    }

    /**
     * Get the cost summary for this order.
     *
     * @return array{subtotal: float, tax_total: float, total: float, base_subtotal: float, base_tax_total: float, base_total: float, currency_code: string|null, exchange_rate: float}
     */
    public function getCostSummary(): array
    {
        return [
            'subtotal' => (float) $this->subtotal,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'base_subtotal' => (float) $this->base_subtotal,
            'base_tax_total' => (float) $this->base_tax_total,
            'base_total' => (float) $this->base_total,
            'currency_code' => $this->currency?->code,
            'exchange_rate' => (float) $this->exchange_rate,
        ];
    }
}
