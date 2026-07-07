<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuyerQuoteStatus;
use App\Enums\PrepaymentType;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\BuyerQuoteObserver;
use App\Services\Erp\Financial\TotalsCollector;
use App\Services\Erp\Financial\TotalsLine;
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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
 * @property PrepaymentType $prepayment_type
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
final class BuyerQuote extends Model implements HasCustomFields, HasMedia
{
    use HasCreator;

    /** @use HasFactory<BuyerQuoteFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * Upload directory for buyer PO files. FileUpload components and their
     * AttachUploadedFiles call sites must reference the same value — drift
     * between them silently drops attachments.
     */
    public const string PO_FILES_UPLOAD_DIRECTORY = 'uploads-tmp/buyer-po';

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
        'prepayment_type' => PrepaymentType::PERCENT,
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
            'prepayment_type' => PrepaymentType::class,
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
     * Copy payment terms (prepayment + schedule) from a supplier quote to this buyer quote.
     * Replaces any existing payment terms on this quote.
     */
    public function copyPaymentTermsFromSupplierQuote(SupplierQuote $supplierQuote): void
    {
        $this->prepayment_type = $supplierQuote->prepayment_type;
        // When type is PERCENT but prepayment_percent is 0, use prepayment_amount as the percentage (legacy data)
        if ($supplierQuote->prepayment_type === PrepaymentType::PERCENT && (int) $supplierQuote->prepayment_percent === 0 && (float) $supplierQuote->prepayment_amount > 0) {
            $this->prepayment_percent = (int) round((float) $supplierQuote->prepayment_amount);
            $this->prepayment_amount = '0.0000';
        } else {
            $this->prepayment_amount = $supplierQuote->prepayment_amount;
            $this->prepayment_percent = $supplierQuote->prepayment_percent;
        }
        $this->saveQuietly();

        $this->paymentTerms()->delete();
        foreach ($supplierQuote->paymentTerms as $term) {
            $this->paymentTerms()->create([
                'due_days' => $term->due_days,
                'percentage' => $term->percentage,
                'job_progress' => $term->job_progress,
                'sort_order' => $term->sort_order,
            ]);
        }
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('buyer_po')
            ->useDisk('local') // Store in private storage
            ->acceptsMimeTypes([
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                'application/vnd.ms-excel', // xls
                'image/png',
                'image/jpeg',
                'image/jpg',
                'application/msword', // doc
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            ]);
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
     * Check if there are additional request items with selected supplier quotes
     * that are not yet included in this buyer quote.
     */
    public function hasAdditionalItems(): bool
    {
        if ($this->request_id === null) {
            return false;
        }

        // Get request item IDs already in this buyer quote
        $existingRequestItemIds = $this->items()
            ->whereNotNull('request_item_id')
            ->pluck('request_item_id')
            ->toArray();

        // Get all request items with selected supplier quotes for this request
        $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
            ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $this->request_id)
                ->where('status', \App\Enums\SupplierQuoteStatus::SELECTED))
            ->whereNotNull('request_item_id')
            ->pluck('request_item_id')
            ->unique()
            ->toArray();

        // Check if there are any request items with selected quotes that aren't in this buyer quote
        $additionalItemIds = array_diff($selectedSupplierQuoteItems, $existingRequestItemIds);

        return ! empty($additionalItemIds);
    }

    /**
     * Create a new version of this quote, including any additional items.
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

        // Copy existing items to new quote
        foreach ($this->items as $item) {
            $newItem = $item->replicate();
            $newItem->buyer_quote_id = $newQuote->getKey();
            $newItem->save();
        }

        // Add additional items from selected supplier quotes
        $this->addAdditionalItemsToQuote($newQuote);

        // Copy payment terms to new quote
        foreach ($this->paymentTerms as $paymentTerm) {
            $newPaymentTerm = $paymentTerm->replicate();
            $newPaymentTerm->buyer_quote_id = $newQuote->getKey();
            $newPaymentTerm->save();
        }

        // Mark this quote as superseded
        $this->status = BuyerQuoteStatus::SUPERSEDED;
        $this->save();

        // Reset PNL for this request and link it to the new quote version.
        $this->resetPnlStatusForRequest($newQuote);

        return $newQuote;
    }

    /**
     * Add additional items from selected supplier quotes to the buyer quote.
     */
    private function addAdditionalItemsToQuote(BuyerQuote $newQuote): void
    {
        if ($this->request_id === null) {
            return;
        }

        // Get request item IDs already in the original buyer quote
        $existingRequestItemIds = $this->items()
            ->whereNotNull('request_item_id')
            ->pluck('request_item_id')
            ->toArray();

        // Get team settings for defaults
        $team = $this->team;
        $settings = $team?->getErpSettings() ?? new \App\Data\TeamErpSettings;
        $defaultMarginPercent = $settings->default_margin_percent ?? 3.0;

        // Get default tax code
        $defaultTaxCode = \App\Models\TaxCode::query()
            ->where('team_id', $this->team_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        // Get all selected supplier quote items for this request
        $selectedSupplierQuoteItems = \App\Models\SupplierQuoteItem::query()
            ->whereHas('supplierQuote', fn ($q) => $q->where('request_id', $this->request_id)
                ->where('status', \App\Enums\SupplierQuoteStatus::SELECTED))
            ->whereNotNull('request_item_id')
            ->whereNotIn('request_item_id', $existingRequestItemIds)
            ->with(['requestItem.article', 'requestItem.unitOfMeasure'])
            ->get();

        // Get the highest sort_order from existing items
        $maxSortOrder = $newQuote->items()->max('sort_order') ?? -1;
        $sortOrder = $maxSortOrder + 1;

        foreach ($selectedSupplierQuoteItems as $supplierQuoteItem) {
            $requestItem = $supplierQuoteItem->requestItem;
            if ($requestItem === null) {
                continue;
            }

            $costPrice = (float) $supplierQuoteItem->unit_price_exc_tax;
            $supplierQuoteItemId = $supplierQuoteItem->getKey();

            // Calculate tax-related values
            $taxRate = $defaultTaxCode !== null ? (float) $defaultTaxCode->rate : 0.0;
            $addTax = $defaultTaxCode !== null && $defaultTaxCode->is_inclusive_default;
            $quantity = (float) $requestItem->quantity;

            // Calculate selling price with default margin on selling (NET price)
            // Formula: margin% = (selling - cost) / selling × 100
            // Solving for selling: selling = cost / (1 - margin%/100)
            $unitPriceExcTax = $costPrice > 0 && $defaultMarginPercent < 100
                ? round($costPrice / (1 - $defaultMarginPercent / 100), 4)
                : 0.0;

            // If tax is inclusive, unit_price should include tax; otherwise unit_price = net price
            if ($addTax && $taxRate > 0) {
                $unitPrice = round($unitPriceExcTax * (1 + $taxRate / 100), 4);
            } else {
                $unitPrice = $unitPriceExcTax;
            }

            // Line subtotal is quantity * net price (amount before tax)
            $lineSubtotal = $quantity * $unitPriceExcTax;

            // Calculate tax if tax rate > 0
            if ($taxRate > 0) {
                if ($addTax) {
                    // Tax is inclusive - line_total includes tax, line_subtotal is extracted
                    $lineTotal = $quantity * $unitPrice;
                    $lineTax = $lineTotal - $lineSubtotal;
                } else {
                    // Tax is exclusive - add tax on top
                    $lineTax = $lineSubtotal * $taxRate / 100;
                    $lineTotal = $lineSubtotal + $lineTax;
                }
            } else {
                $lineTax = 0;
                $lineTotal = $lineSubtotal;
            }

            // Calculate margin: ((selling_price - cost_price) / selling_price) * 100
            $marginAmount = $unitPriceExcTax - $costPrice;
            $marginPercent = $unitPriceExcTax > 0 ? ($marginAmount / $unitPriceExcTax) * 100 : 0;

            $newQuote->items()->create([
                'request_item_id' => $requestItem->getKey(),
                'article_id' => $requestItem->article_id,
                'supplier_quote_item_id' => $supplierQuoteItemId,
                'description' => $requestItem->article !== null ? $requestItem->article->name : $requestItem->description,
                'quantity' => $requestItem->quantity,
                'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                'unit' => $requestItem->unitOfMeasure->code ?? $requestItem->unit->value ?? 'pcs',
                'cost_price' => $costPrice,
                'unit_price' => $unitPrice,
                'unit_price_exc_tax' => round($unitPriceExcTax, 0),
                'tax_code_id' => $defaultTaxCode?->getKey(),
                'tax_rate' => $taxRate,
                'tax_amount' => round($lineTax / max($quantity, 0.0001), 4),
                'is_tax_inclusive' => $addTax,
                'line_subtotal' => round($lineSubtotal, 4),
                'line_tax' => round($lineTax, 4),
                'line_total' => round($lineTotal, 0),
                'margin_amount' => round($marginAmount, 4),
                'margin_percent' => round($marginPercent, 4),
                'sort_order' => $sortOrder++,
            ]);
        }

        // Recalculate totals after adding items
        $newQuote->recalculateTotals();
    }

    /**
     * Reset PNL for this request when a new quote version is created.
     */
    private function resetPnlStatusForRequest(BuyerQuote $newQuote): void
    {
        if ($this->request_id === null) {
            return;
        }

        $profitAndLosses = \App\Models\ProfitAndLoss::query()
            ->where('request_id', $this->request_id)
            ->get();

        foreach ($profitAndLosses as $pnl) {
            $pnl->buyer_quote_id = $newQuote->getKey();

            if ($pnl->status === \App\Enums\PNLStatus::APPROVED) {
                $pnl->status = \App\Enums\PNLStatus::NEED_APPROVAL;
                $pnl->dept_head_sales_approved_at = null;
                $pnl->deputy_director_approved_at = null;
                $pnl->director_approved_at = null;
                // Clear the frozen figures so the next approval re-captures them.
                $pnl->financial_snapshot = null;
            }

            $pnl->saveQuietly();
        }
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
     * Child/detail lines under services main items are excluded from totals.
     */
    public function recalculateTotals(): void
    {
        $this->load(['items.requestItem', 'request']);

        $itemsForTotal = BuyerQuoteItem::filterForTotals($this->items);

        $totals = (new TotalsCollector)->collect(
            $itemsForTotal->map(fn (BuyerQuoteItem $item): TotalsLine => new TotalsLine(
                lineSubtotal: (float) $item->line_subtotal,
                lineTax: (float) $item->line_tax,
                lineTotal: (float) $item->line_total,
                costPrice: (float) $item->cost_price,
                quantity: (float) $item->quantity,
            ))->values(),
        );

        $this->subtotal = (string) $totals->subtotal;
        $this->tax_total = (string) $totals->taxTotal;
        $this->total = (string) $totals->grandTotal;
        $this->saveQuietly();
    }

    /**
     * Copy tax settings from parent main items onto child line items that are missing tax.
     */
    public function syncChildItemTaxFromParents(): void
    {
        $this->load(['items.requestItem']);

        $mainItemsByRequestItemId = $this->items
            ->filter(fn (BuyerQuoteItem $item): bool => $item->requestItem === null || $item->requestItem->parent_id === null)
            ->keyBy('request_item_id');

        foreach ($this->items as $childItem) {
            if (! $childItem->isChildItem()) {
                continue;
            }

            $parentRequestItemId = $childItem->requestItem?->parent_id;
            if ($parentRequestItemId === null) {
                continue;
            }

            /** @var BuyerQuoteItem|null $mainItem */
            $mainItem = $mainItemsByRequestItemId->get($parentRequestItemId);
            if ($mainItem === null || $mainItem->tax_code_id === null || ! $mainItem->is_tax_inclusive) {
                continue;
            }

            $needsSync = $childItem->tax_code_id === null
                || ! $childItem->is_tax_inclusive
                || (float) $childItem->line_tax <= 0;

            if (! $needsSync) {
                continue;
            }

            $childItem->tax_code_id = $mainItem->tax_code_id;
            $childItem->tax_rate = $mainItem->tax_rate;
            $childItem->is_tax_inclusive = $mainItem->is_tax_inclusive;
            $childItem->recalculatePrices();
            $childItem->saveQuietly();
        }

        $this->recalculateTotals();
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
            $regex = '/^'.preg_quote((string) $prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastQuote->quote_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
