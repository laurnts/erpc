<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierQuoteStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\SupplierQuoteObserver;
use Database\Factories\SupplierQuoteFactory;
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
 * @property int $request_id
 * @property int $supplier_id
 * @property int|null $creator_id
 * @property string $quote_number
 * @property string|null $supplier_reference
 * @property SupplierQuoteStatus $status
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $subtotal_base
 * @property string $tax_total_base
 * @property string $total_base
 * @property Carbon|null $quoted_at
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property array<string, mixed>|null $notification_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $is_valid
 * @property-read bool $is_expired
 * @property-read string $formatted_total
 * @property-read string $formatted_total_base
 */
#[ObservedBy(SupplierQuoteObserver::class)]
final class SupplierQuote extends Model
{
    use HasCreator;

    /** @use HasFactory<SupplierQuoteFactory> */
    use HasFactory;

    use HasTeam;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'supplier_id',
        'quote_number',
        'supplier_reference',
        'status',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'tax_total',
        'total',
        'subtotal_base',
        'tax_total_base',
        'total_base',
        'quoted_at',
        'valid_until',
        'notes',
        'internal_notes',
        'notification_metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SupplierQuoteStatus::PENDING,
        'exchange_rate' => '1.00000000',
        'subtotal' => '0.0000',
        'tax_total' => '0.0000',
        'total' => '0.0000',
        'subtotal_base' => '0.0000',
        'tax_total_base' => '0.0000',
        'total_base' => '0.0000',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierQuoteStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'subtotal_base' => 'decimal:4',
            'tax_total_base' => 'decimal:4',
            'total_base' => 'decimal:4',
            'quoted_at' => 'date',
            'valid_until' => 'date',
            'notification_metadata' => 'array',
        ];
    }

    /**
     * The request this quote is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The supplier (company) who provided this quote.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    /**
     * The currency for this quote.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The items in this quote.
     *
     * @return HasMany<SupplierQuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class)->orderBy('sort_order');
    }

    /**
     * Check if the quote is still valid (not expired).
     *
     * @return Attribute<bool, never>
     */
    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->valid_until === null || $this->valid_until->isFuture(),
        );
    }

    /**
     * Check if the quote has expired.
     *
     * @return Attribute<bool, never>
     */
    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->valid_until !== null && $this->valid_until->isPast(),
        );
    }

    /**
     * Get formatted total in quote currency.
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
    protected function formattedTotalBase(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $team = $this->team;
                $baseCurrency = $team?->getBaseCurrency();

                if ($baseCurrency === null) {
                    return number_format((float) $this->total_base, 2);
                }

                return $baseCurrency->format((float) $this->total_base);
            },
        );
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = $items->sum(fn (SupplierQuoteItem $item): float => (float) $item->line_subtotal);
        $taxTotal = $items->sum(fn (SupplierQuoteItem $item): float => (float) $item->line_tax);
        $total = $items->sum(fn (SupplierQuoteItem $item): float => (float) $item->line_total);

        $exchangeRate = (float) $this->exchange_rate;

        $this->subtotal = (string) round($subtotal, 4);
        $this->tax_total = (string) round($taxTotal, 4);
        $this->total = (string) round($total, 4);

        // Calculate base currency values
        $this->subtotal_base = (string) round($subtotal * $exchangeRate, 4);
        $this->tax_total_base = (string) round($taxTotal * $exchangeRate, 4);
        $this->total_base = (string) round($total * $exchangeRate, 4);

        // Auto-change status from PENDING to RECEIVED when prices are inputted
        if ($this->status === SupplierQuoteStatus::PENDING && $total > 0) {
            $this->status = SupplierQuoteStatus::RECEIVED;
        }

        $this->saveQuietly();
    }

    /**
     * Mark this quote as selected.
     */
    public function markAsSelected(): void
    {
        $this->status = SupplierQuoteStatus::SELECTED;
        $this->save();
    }

    /**
     * Mark this quote as rejected.
     */
    public function markAsRejected(): void
    {
        $this->status = SupplierQuoteStatus::REJECTED;
        $this->save();
    }

    /**
     * Check and update expired status if needed.
     */
    public function checkAndUpdateExpiredStatus(): void
    {
        if ($this->status === SupplierQuoteStatus::PENDING && $this->is_expired) {
            $this->status = SupplierQuoteStatus::EXPIRED;
            $this->saveQuietly();
        }
    }

    /**
     * Get the consolidated cost summary.
     *
     * @return array{subtotal: float, tax_total: float, total: float, subtotal_base: float, tax_total_base: float, total_base: float, currency_code: string|null, exchange_rate: float}
     */
    public function getCostSummary(): array
    {
        return [
            'subtotal' => (float) $this->subtotal,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'subtotal_base' => (float) $this->subtotal_base,
            'tax_total_base' => (float) $this->tax_total_base,
            'total_base' => (float) $this->total_base,
            'currency_code' => $this->currency?->code,
            'exchange_rate' => (float) $this->exchange_rate,
        ];
    }
}
