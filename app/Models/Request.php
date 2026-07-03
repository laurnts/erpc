<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuyerQuoteStatus;
use App\Enums\ItemType;
use App\Enums\OrderStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Enums\SupplierQuoteStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTeam;
use App\Observers\RequestObserver;
use Database\Factories\RequestFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $request_number
 * @property string $title
 * @property string|null $description
 * @property RequestStage $stage
 * @property RequestPriority $priority
 * @property Carbon|null $requested_at
 * @property Carbon|null $required_by
 * @property string|null $internal_notes
 * @property bool $is_active
 * @property int $buyer_id
 * @property int|null $project_id
 * @property RequestSubmissionMethod|null $submission_method
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by_user_id
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $all_items_matched
 * @property-read int $items_count
 * @property-read int $matched_items_count
 * @property-read int $unmatched_items_count
 * @property-read float $buyer_total
 * @property-read float $supplier_cost
 * @property-read float $gross_margin
 * @property-read float $margin_percent
 * @property-read float $amount_collected
 * @property-read float $amount_paid_out
 * @property-read float $net_cash_flow
 * @property-read float $expected_buyer_total
 * @property-read float $expected_supplier_cost
 * @property-read float $expected_gross_margin
 * @property-read float $expected_margin_percent
 * @property-read bool $has_buyer_order_confirmed
 * @property-read bool $has_supplier_order_confirmed
 * @property-read string $financial_status
 */
#[ObservedBy(RequestObserver::class)]
final class Request extends Model implements HasCustomFields, HasMedia
{
    use HasCreator;

    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    use HasNotes;
    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_number',
        'title',
        'description',
        'stage',
        'priority',
        'requested_at',
        'required_by',
        'internal_notes',
        'is_active',
        'buyer_id',
        'project_id',
        'submission_method',
        'submitted_at',
        'submitted_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stage' => RequestStage::DRAFT,
        'priority' => RequestPriority::NORMAL,
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'stage' => RequestStage::class,
            'priority' => RequestPriority::class,
            'submission_method' => RequestSubmissionMethod::class,
            'requested_at' => 'date',
            'required_by' => 'date',
            'submitted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local');
        $this->addMediaCollection('completion_reports')
            ->useDisk('local');
        $this->addMediaCollection('goods_receive')
            ->useDisk('local');
    }

    /**
     * The buyer (company) that owns this request.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
    }

    /**
     * Portal user who submitted this request.
     *
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function isPortalSubmission(): bool
    {
        return $this->submission_method !== null;
    }

    public function isEditableByCustomer(): bool
    {
        return $this->isPortalSubmission()
            && $this->stage === RequestStage::DRAFT;
    }

    /**
     * The project this request is associated with.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The items in this request.
     *
     * @return HasMany<RequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class)->orderBy('sort_order');
    }

    /**
     * The tasks associated with this request.
     *
     * @return MorphToMany<Task, $this>
     */
    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'taskable');
    }

    /**
     * The supplier quotes for this request.
     *
     * @return HasMany<SupplierQuote, $this>
     */
    public function supplierQuotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class);
    }

    /**
     * The buyer quotes for this request.
     *
     * @return HasMany<BuyerQuote, $this>
     */
    public function buyerQuotes(): HasMany
    {
        return $this->hasMany(BuyerQuote::class);
    }

    /**
     * The buyer orders for this request.
     *
     * @return HasMany<BuyerOrder, $this>
     */
    public function buyerOrders(): HasMany
    {
        return $this->hasMany(BuyerOrder::class);
    }

    /**
     * The supplier orders (purchase orders) for this request.
     *
     * @return HasMany<SupplierOrder, $this>
     */
    public function supplierOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class);
    }

    /**
     * Goods receive document batches (each batch can contain multiple uploaded files).
     *
     * @return HasMany<GoodsReceiveBatch, $this>
     */
    public function goodsReceiveBatches(): HasMany
    {
        return $this->hasMany(GoodsReceiveBatch::class);
    }

    /**
     * The shipments for this request.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * The quotation evaluations for this request.
     *
     * @return HasMany<QuotationEvaluation, $this>
     */
    public function quotationEvaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class);
    }

    /**
     * The acceptance reports for this request (Service requests only).
     *
     * @return HasMany<AcceptanceReport, $this>
     */
    public function acceptanceReports(): HasMany
    {
        return $this->hasMany(AcceptanceReport::class);
    }

    /**
     * The profit and loss documents for this request.
     *
     * @return HasMany<ProfitAndLoss, $this>
     */
    public function profitAndLosses(): HasMany
    {
        return $this->hasMany(ProfitAndLoss::class);
    }

    /**
     * The activity log for this request.
     *
     * @return HasMany<RequestActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(RequestActivity::class)->orderByDesc('created_at');
    }

    /**
     * The inbound shipments (from suppliers) for this request.
     *
     * @return HasMany<Shipment, $this>
     */
    public function inboundShipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->where('type', \App\Enums\ShipmentType::INBOUND);
    }

    /**
     * The outbound shipments (to buyers) for this request.
     *
     * @return HasMany<Shipment, $this>
     */
    public function outboundShipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->where('type', \App\Enums\ShipmentType::OUTBOUND);
    }

    /**
     * Check if all items in this request have been matched to articles.
     * Only counts main items, not child items (for Service requests).
     *
     * @return Attribute<bool, never>
     */
    protected function allItemsMatched(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->items()
                ->whereNull('parent_id') // Only main items
                ->where('is_matched', false)
                ->doesntExist(),
        );
    }

    /**
     * Get total items count.
     *
     * @return Attribute<int, never>
     */
    protected function itemsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->items()->count(),
        );
    }

    /**
     * Get matched items count.
     * Only counts main items, not child items (for Service requests).
     *
     * @return Attribute<int, never>
     */
    protected function matchedItemsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->items()
                ->whereNull('parent_id') // Only main items
                ->where('is_matched', true)
                ->whereNotNull('article_id') // Must have article_id to be considered matched
                ->count(),
        );
    }

    /**
     * Get unmatched items count.
     * Only counts main items, not child items (for Service requests).
     *
     * @return Attribute<int, never>
     */
    protected function unmatchedItemsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->items()
                ->whereNull('parent_id') // Only main items
                ->where(function ($query) {
                    $query->where('is_matched', false)
                        ->orWhereNull('article_id'); // Also count items without article_id as unmatched
                })
                ->count(),
        );
    }

    /**
     * Check if stage transition is allowed.
     */
    public function canTransitionTo(RequestStage $newStage): bool
    {
        return $this->stage->canTransitionTo($newStage);
    }

    /**
     * Transition to a new stage with validation.
     *
     * @throws \InvalidArgumentException
     */
    public function transitionTo(RequestStage $newStage): void
    {
        if (! $this->canTransitionTo($newStage)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot transition from %s to %s',
                    $this->stage->getLabel(),
                    $newStage->getLabel()
                )
            );
        }

        // All main-level items must be matched when required; child items
        // (detail breakdown under services items) are exempt.
        if ($newStage->requiresMatchedItems() && ! $this->all_items_matched) {
            throw new \InvalidArgumentException(
                'All main items must be matched to articles before transitioning to '.$newStage->getLabel()
            );
        }

        $this->stage = $newStage;
        $this->save();
    }

    /**
     * Check if request items can be edited in current stage.
     * Allows editing in early stages, or in AWAITING_BUYER_CONFIRMATION if no buyer order is confirmed yet.
     */
    public function canEditItems(): bool
    {
        // Allow editing in early stages (DRAFT, AWAITING_SUPPLIER_RESPONSE, PREPARING_BUYER_QUOTE)
        if ($this->stage->allowsItemEditing()) {
            return true;
        }

        // Also allow in AWAITING_BUYER_CONFIRMATION if no confirmed buyer order exists
        return $this->stage === RequestStage::AWAITING_BUYER_CONFIRMATION && ! $this->has_buyer_order_confirmed;
    }

    /**
     * Get total revenue from buyer orders (in base currency).
     * Sum of all buyer order totals for this request.
     *
     * @return Attribute<float, never>
     */
    protected function buyerTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->buyerOrders()->sum('total'),
        );
    }

    /**
     * Get total cost from supplier orders (in base currency).
     * Sum of all supplier order base_total values.
     *
     * @return Attribute<float, never>
     */
    protected function supplierCost(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->supplierOrders()->sum('base_total'),
        );
    }

    /**
     * Get gross margin (buyer total minus supplier cost).
     *
     * @return Attribute<float, never>
     */
    protected function grossMargin(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->buyer_total - $this->supplier_cost,
        );
    }

    /**
     * Get margin percentage ((gross margin / buyer total) * 100).
     * Returns 0 if buyer total is zero to avoid division by zero.
     *
     * @return Attribute<float, never>
     */
    protected function marginPercent(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $buyerTotal = $this->buyer_total;

                if ($buyerTotal === 0.0) {
                    return 0.0;
                }

                return ($this->gross_margin / $buyerTotal) * 100;
            },
        );
    }

    /**
     * Get total amount collected from buyer payments.
     * Sum of all payments received through buyer invoices.
     *
     * @return Attribute<float, never>
     */
    protected function amountCollected(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $result = DB::table('buyer_payments')
                    ->join('buyer_invoices', 'buyer_payments.buyer_invoice_id', '=', 'buyer_invoices.id')
                    ->where('buyer_invoices.request_id', $this->getKey())
                    ->whereNull('buyer_invoices.deleted_at')
                    ->sum('buyer_payments.amount');

                return (float) $result;
            },
        );
    }

    /**
     * Get total amount paid out to suppliers.
     * Sum of all payments made through supplier invoices.
     *
     * @return Attribute<float, never>
     */
    protected function amountPaidOut(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $result = DB::table('supplier_payments')
                    ->join('supplier_invoices', 'supplier_payments.supplier_invoice_id', '=', 'supplier_invoices.id')
                    ->where('supplier_invoices.request_id', $this->getKey())
                    ->whereNull('supplier_invoices.deleted_at')
                    ->sum('supplier_payments.amount');

                return (float) $result;
            },
        );
    }

    /**
     * Get net cash flow (amount collected minus amount paid out).
     *
     * @return Attribute<float, never>
     */
    protected function netCashFlow(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->amount_collected - $this->amount_paid_out,
        );
    }

    /**
     * Get expected buyer total from accepted quotes (when no confirmed orders exist).
     *
     * @return Attribute<float, never>
     */
    protected function expectedBuyerTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->buyerQuotes()
                ->where('status', BuyerQuoteStatus::ACCEPTED)
                ->sum('total'),
        );
    }

    /**
     * Get expected supplier cost from selected quotes (when no confirmed orders exist).
     *
     * @return Attribute<float, never>
     */
    protected function expectedSupplierCost(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->supplierQuotes()
                ->where('status', SupplierQuoteStatus::SELECTED)
                ->sum('total_base'),
        );
    }

    /**
     * Get expected gross margin (expected buyer total minus expected supplier cost).
     *
     * @return Attribute<float, never>
     */
    protected function expectedGrossMargin(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->expected_buyer_total - $this->expected_supplier_cost,
        );
    }

    /**
     * Get expected margin percentage.
     *
     * @return Attribute<float, never>
     */
    protected function expectedMarginPercent(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $buyerTotal = $this->expected_buyer_total;

                if ($buyerTotal === 0.0) {
                    return 0.0;
                }

                return ($this->expected_gross_margin / $buyerTotal) * 100;
            },
        );
    }

    /**
     * Check if at least one buyer order is confirmed (not draft or cancelled).
     *
     * @return Attribute<bool, never>
     */
    protected function hasBuyerOrderConfirmed(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->buyerOrders()
                ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
                ->exists(),
        );
    }

    /**
     * Check if at least one supplier order is confirmed (not draft or cancelled).
     *
     * @return Attribute<bool, never>
     */
    protected function hasSupplierOrderConfirmed(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->supplierOrders()
                ->whereNotIn('status', [OrderStatus::DRAFT, OrderStatus::CANCELLED])
                ->exists(),
        );
    }

    /**
     * Get financial status for display.
     * Returns 'confirmed' when both buyer and supplier orders are confirmed,
     * 'expected' when there are accepted/selected quotes but orders not confirmed,
     * or 'none' when no financial data exists.
     *
     * @return Attribute<string, never>
     */
    protected function financialStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                // Check if both buyer and supplier orders are confirmed
                if ($this->has_buyer_order_confirmed && $this->has_supplier_order_confirmed) {
                    return 'confirmed';
                }

                // Check if we have expected values from quotes
                $hasExpectedBuyer = $this->expected_buyer_total > 0;
                $hasExpectedSupplier = $this->expected_supplier_cost > 0;

                // Also check if there are any orders (even draft)
                $hasBuyerOrders = $this->buyer_total > 0;
                $hasSupplierOrders = $this->supplier_cost > 0;

                if ($hasExpectedBuyer || $hasExpectedSupplier || $hasBuyerOrders || $hasSupplierOrders) {
                    return 'expected';
                }

                return 'none';
            },
        );
    }

    /**
     * Human-readable summary of the item types on this request.
     *
     * @return Attribute<string, never>
     */
    protected function itemTypeSummary(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $hasGoods = $this->hasGoodsItems();
                $hasServices = $this->hasServiceItems();

                return match (true) {
                    $hasGoods && $hasServices => 'Mixed',
                    $hasServices => 'Services',
                    $hasGoods => 'Goods',
                    default => '—',
                };
            },
        );
    }

    /**
     * Whether the request has at least one goods item.
     */
    public function hasGoodsItems(): bool
    {
        return $this->hasItemsOfType(ItemType::GOODS);
    }

    /**
     * Whether the request has at least one services item.
     */
    public function hasServiceItems(): bool
    {
        return $this->hasItemsOfType(ItemType::SERVICE);
    }

    private function hasItemsOfType(ItemType $type): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn (RequestItem $item): bool => $item->item_type === $type);
        }

        return $this->items()->where('item_type', $type)->exists();
    }

    /**
     * Whether fulfillment is confirmed via acceptance reports (any services item).
     */
    public function usesAcceptanceReports(): bool
    {
        return $this->hasServiceItems();
    }

    /**
     * Whether fulfillment happens through physical shipments (any goods item).
     */
    public function requiresShipments(): bool
    {
        return $this->hasGoodsItems();
    }

    /**
     * Whether quote payment terms track job progress (any services item).
     */
    public function hasJobProgress(): bool
    {
        return $this->hasServiceItems();
    }

    /**
     * Check if quotation evaluation can be created for this request (any goods item).
     */
    public function canCreateQuotationEvaluation(): bool
    {
        return $this->hasGoodsItems();
    }

    /**
     * Check if the request has at least one supplier quote that is obtained and selected.
     * When true, user can proceed to Buyer Quotes without QE approval.
     */
    public function hasObtainedSelectedSupplierQuote(): bool
    {
        return $this->supplierQuotes()
            ->where('obtained', true)
            ->where('status', SupplierQuoteStatus::SELECTED)
            ->exists();
    }
}
