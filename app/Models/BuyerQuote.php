<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuyerQuoteStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\BuyerQuoteObserver;
use Database\Factories\BuyerQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;

/**
 * @property int $id
 * @property int $team_id
 * @property int $request_id
 * @property int $buyer_id
 * @property int|null $creator_id
 * @property string $quote_number
 * @property int $version
 * @property int|null $previous_version_id
 * @property BuyerQuoteStatus $status
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $prepayment_type
 * @property string $prepayment_amount
 * @property int $prepayment_percent
 * @property int $payment_terms_days
 * @property string|null $payment_terms_description
 * @property Carbon|null $issued_at
 * @property Carbon|null $valid_until
 * @property string|null $terms_and_conditions
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property array<string, mixed>|null $notification_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read float $total_margin_amount
 * @property-read float $total_margin_percent
 * @property-read bool $is_expired
 * @property-read bool $can_edit
 * @property-read BuyerQuote|null $previousVersion
 * @property-read Request $request
 * @property-read Company $buyer
 * @property-read Currency $currency
 */
#[ObservedBy(BuyerQuoteObserver::class)]
final class BuyerQuote extends Model implements HasCustomFields
{
    use HasCreator;

    /** @use HasFactory<BuyerQuoteFactory> */
    use HasFactory;

    use HasTeam;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'buyer_id',
        'quote_number',
        'version',
        'previous_version_id',
        'status',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'tax_total',
        'total',
        'prepayment_type',
        'prepayment_amount',
        'prepayment_percent',
        'payment_terms_days',
        'payment_terms_description',
        'issued_at',
        'valid_until',
        'terms_and_conditions',
        'notes',
        'internal_notes',
        'notification_metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
        'status' => BuyerQuoteStatus::DRAFT,
        'exchange_rate' => '1.00000000',
        'subtotal' => '0.0000',
        'tax_total' => '0.0000',
        'total' => '0.0000',
        'prepayment_type' => 'percent',
        'prepayment_amount' => '0.0000',
        'prepayment_percent' => 0,
        'payment_terms_days' => 30,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => BuyerQuoteStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'prepayment_type' => 'string',
            'prepayment_amount' => 'decimal:4',
            'prepayment_percent' => 'integer',
            'payment_terms_days' => 'integer',
            'issued_at' => 'date',
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
     * The buyer (company) this quote is for.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
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
     * The previous version of this quote (for versioning).
     *
     * @return BelongsTo<BuyerQuote, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    /**
     * The newer versions of this quote.
     *
     * @return HasMany<BuyerQuote, $this>
     */
    public function newerVersions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_version_id');
    }

    /**
     * The items in this quote.
     *
     * @return HasMany<BuyerQuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BuyerQuoteItem::class)->orderBy('sort_order');
    }

    /**
     * The extensions (validity date changes) for this quote.
     *
     * @return HasMany<BuyerQuoteExtension, $this>
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(BuyerQuoteExtension::class)->orderByDesc('created_at');
    }

    /**
     * The payment terms for this quote.
     *
     * @return HasMany<BuyerQuotePaymentTerm, $this>
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(BuyerQuotePaymentTerm::class)->orderBy('sort_order');
    }

    /**
     * Check if the quote is expired.
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
     * Check if the quote can be edited.
     *
     * @return Attribute<bool, never>
     */
    protected function canEdit(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status->canEdit(),
        );
    }

    /**
     * Calculate total margin amount from all items.
     *
     * @return Attribute<float, never>
     */
    protected function totalMarginAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->items->sum('margin_amount'),
        );
    }

    /**
     * Calculate total margin percent based on cost vs selling price.
     *
     * @return Attribute<float, never>
     */
    protected function totalMarginPercent(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $totalCost = (float) $this->items->sum(fn (BuyerQuoteItem $item): float => (float) $item->cost_price * (float) $item->quantity);
                $totalSelling = (float) $this->items->sum('line_subtotal');

                if ($totalCost <= 0) {
                    return 0.0;
                }

                return round((($totalSelling - $totalCost) / $totalCost) * 100, 2);
            },
        );
    }

    /**
     * Create a new version of this quote.
     */
    public function createNewVersion(): self
    {
        $newQuote = $this->replicate([
            'quote_number',
            'issued_at',
            'status',
            'items_count', // Exclude virtual count attribute from table
        ]);

        $newQuote->version = $this->version + 1;
        $newQuote->previous_version_id = $this->getKey();
        $newQuote->status = BuyerQuoteStatus::DRAFT;
        // Generate a new quote number since replicate excludes it
        $newQuote->quote_number = self::generateNextNumber($this->team_id);
        $newQuote->save();

        // Copy items to new quote
        foreach ($this->items as $item) {
            $newItem = $item->replicate();
            $newItem->buyer_quote_id = $newQuote->getKey();
            $newItem->save();
        }

        // Copy payment terms to new quote
        foreach ($this->paymentTerms as $paymentTerm) {
            $newPaymentTerm = $paymentTerm->replicate();
            $newPaymentTerm->buyer_quote_id = $newQuote->getKey();
            $newPaymentTerm->save();
        }

        // Mark this quote as superseded
        $this->status = BuyerQuoteStatus::SUPERSEDED;
        $this->save();

        return $newQuote;
    }

    /**
     * Extend the validity date with a reason.
     */
    public function extendValidity(Carbon $newValidUntil, ?string $reason = null): BuyerQuoteExtension
    {
        $extension = new BuyerQuoteExtension;
        $extension->buyer_quote_id = $this->getKey();
        $extension->original_valid_until = $this->valid_until;
        $extension->new_valid_until = $newValidUntil;
        $extension->reason = $reason;
        $extension->extended_by_id = auth()->id();
        $extension->save();

        $this->valid_until = $newValidUntil;
        $this->save();

        return $extension;
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        // Refresh the items relationship to get fresh data
        $this->load('items');

        $this->subtotal = (string) $this->items->sum('line_subtotal');
        $this->tax_total = (string) $this->items->sum('line_tax');
        $this->total = (string) $this->items->sum('line_total');
        $this->saveQuietly();
    }

    /**
     * Mark quote as sent.
     */
    public function markAsSent(): void
    {
        if ($this->status !== BuyerQuoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Only draft quotes can be sent.');
        }

        $this->status = BuyerQuoteStatus::SENT;
        $this->issued_at = now();
        $this->save();
    }

    /**
     * Mark quote as accepted.
     */
    public function markAsAccepted(): void
    {
        if ($this->status !== BuyerQuoteStatus::SENT) {
            throw new \InvalidArgumentException('Only sent quotes can be accepted.');
        }

        $this->status = BuyerQuoteStatus::ACCEPTED;
        $this->save();
    }

    /**
     * Mark quote as rejected.
     */
    public function markAsRejected(): void
    {
        if ($this->status !== BuyerQuoteStatus::SENT) {
            throw new \InvalidArgumentException('Only sent quotes can be rejected.');
        }

        $this->status = BuyerQuoteStatus::REJECTED;
        $this->save();
    }

    /**
     * Get the display text for the quote (for select fields etc).
     */
    public function getDisplayTextAttribute(): string
    {
        return sprintf('%s (v%d) - %s', $this->quote_number, $this->version, $this->status->getLabel());
    }

    /**
     * Generate the next quote number for the given team.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new \App\Data\TeamErpSettings;
        $prefix = $settings->buyer_quote_number_prefix;

        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-%';

        // Get the highest sequence number for this team and year
        $lastQuote = self::withTrashed()
            ->where('team_id', $teamId)
            ->where('quote_number', 'like', $pattern)
            ->orderByDesc('quote_number')
            ->first();

        $nextNumber = 1;
        if ($lastQuote !== null) {
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastQuote->quote_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
