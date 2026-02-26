<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralPurchasingRole;
use App\Enums\OrderStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\SupplierOrderObserver;
use App\Services\TeamMemberService;
use Database\Factories\SupplierOrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
 * @property int|null $approver_1_id
 * @property int|null $approver_2_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read string $formatted_total
 * @property-read string $formatted_base_total
 * @property-read bool $is_editable
 * @property-read bool $is_cancellable
 * @property-read bool $is_approved
 * @property-read User|null $approver1
 * @property-read User|null $approver2
 */
#[ObservedBy(SupplierOrderObserver::class)]
final class SupplierOrder extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<SupplierOrderFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
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
        'approver_1_id',
        'approver_2_id',
        'approved_at',
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
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('local');
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
     * The shipments for this order (inbound).
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderByDesc('created_at');
    }

    /**
     * The first approver of this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_1_id');
    }

    /**
     * The second approver of this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_2_id');
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
            get: function (): string {
                $team = $this->team;
                $baseCurrency = $team?->getBaseCurrency();

                if ($baseCurrency === null) {
                    return number_format((float) $this->base_total, 2);
                }

                return $baseCurrency->format((float) $this->base_total);
            },
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
     * Check if the order is approved (has 2 approvals).
     *
     * @return Attribute<bool, never>
     */
    protected function isApproved(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status === OrderStatus::APPROVED && $this->approver_1_id !== null && $this->approver_2_id !== null,
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
     * Approve the order by a user.
     * Requires minimum 2 approvals from eligible roles (Dept Head of Sales, Deputy Director, Director).
     * Status automatically changes to APPROVED after second approval.
     *
     * @param  User  $approver  The user approving the order
     * @throws \InvalidArgumentException If user cannot approve or already approved
     */
    public function approve(User $approver): void
    {
        if (! $this->canBeApprovedBy($approver)) {
            throw new \InvalidArgumentException('User cannot approve this order.');
        }

        // Check if user already approved
        if ($this->approver_1_id === $approver->id || $this->approver_2_id === $approver->id) {
            throw new \InvalidArgumentException('User has already approved this order.');
        }

        // Set first approver if not set
        if ($this->approver_1_id === null) {
            $this->approver_1_id = $approver->id;
            $this->save();
            return;
        }

        // Set second approver and mark as approved
        if ($this->approver_2_id === null) {
            $this->approver_2_id = $approver->id;
            $this->approved_at = now();
            $this->status = OrderStatus::APPROVED;
            $this->save();
        }
    }

    /**
     * Mark this supplier order as approved via document acceptance (key account approved the document in Acceptance Report).
     */
    public function approveViaDocumentAcceptance(User $user): void
    {
        $this->approver_1_id = $this->approver_1_id ?? $user->id;
        $this->approver_2_id = $this->approver_2_id ?? $user->id;
        $this->approved_at = now();
        $this->status = OrderStatus::APPROVED;
        $this->save();
    }

    /**
     * Check if a user can approve this order.
     *
     * @param  User  $user  The user to check
     * @return bool True if user can approve
     */
    public function canBeApprovedBy(User $user): bool
    {
        // Must be in confirmed status
        if (! $this->status->canApprove()) {
            return false;
        }

        // User must be in the same team
        if ($this->team_id === null || ! $user->teams->contains($this->team_id)) {
            return false;
        }

        // Check if user has one of the approval roles
        $team = $this->team;
        if ($team === null) {
            return false;
        }

        // Administrators can approve
        if ($user->hasTeamRole($team, 'admin')) {
            return true;
        }

        $approvalRoles = [
            CentralPurchasingRole::DEPT_HEAD_SALES,
            CentralPurchasingRole::DEPUTY_DIRECTOR,
            CentralPurchasingRole::DIRECTOR,
        ];

        foreach ($approvalRoles as $role) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, $role);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark the order as sent (email sent to supplier).
     */
    public function markAsSent(): void
    {
        if (! $this->status->canSend()) {
            throw new \InvalidArgumentException('Only draft orders can be sent.');
        }

        $this->status = OrderStatus::SENT;
        $this->ordered_at = now();
        $this->save();
    }

    /**
     * Mark the order as sent/ordered (legacy method for backward compatibility).
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
            // Ensure unit is set from unit_of_measure_id, bypassing SafeUnitCast
            $unitCode = 'pcs'; // Default fallback
            if ($quoteItem->unit_of_measure_id !== null) {
                $unitOfMeasure = \App\Models\UnitOfMeasure::find($quoteItem->unit_of_measure_id);
                if ($unitOfMeasure !== null) {
                    $unitCode = $unitOfMeasure->code;
                } else {
                    // Fallback to quote item's unit if UnitOfMeasure not found
                    $quoteUnit = $quoteItem->unit;
                    $unitCode = $quoteUnit instanceof \App\Enums\Unit ? $quoteUnit->value : ($quoteUnit ?? 'pcs');
                }
            } else {
                // Fallback to quote item's unit
                $quoteUnit = $quoteItem->unit;
                $unitCode = $quoteUnit instanceof \App\Enums\Unit ? $quoteUnit->value : ($quoteUnit ?? 'pcs');
            }
            
            $item = SupplierOrderItem::make([
                'supplier_order_id' => $order->getKey(),
                'supplier_quote_item_id' => $quoteItem->getKey(),
                'request_item_id' => $quoteItem->request_item_id,
                'article_id' => $quoteItem->article_id,
                'description' => $quoteItem->description,
                'quantity' => $quoteItem->quantity,
                'unit_of_measure_id' => $quoteItem->unit_of_measure_id,
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
            
            // Use setRawAttributes to bypass SafeUnitCast and ensure unit is set
            $attributes = $item->getAttributes();
            $item->setRawAttributes(array_merge($attributes, ['unit' => (string) $unitCode]));
            
            // If supplier is taxable and item has tax, recalculate line total to include tax
            if ($quote->supplier !== null && $quote->supplier->is_taxable && (float) $item->tax_rate > 0) {
                $item->calculateLineTotal();
            }
            
            $item->save();
        }

        // Recalculate order totals from items (ensures tax_total is correct)
        $order->load('supplier');
        $order->recalculateTotals();

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
