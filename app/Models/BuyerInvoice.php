<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\TeamErpSettings;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Models\Concerns\LogsErpActivity;
use App\Observers\BuyerInvoiceObserver;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Database\Factories\BuyerInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int|null $team_id
 * @property int|null $creator_id
 * @property int $request_id
 * @property int|null $buyer_order_id
 * @property string|null $invoice_number
 * @property InvoiceType $type
 * @property InvoiceStatus $status
 * @property int|null $original_invoice_id
 * @property string|null $credit_reason
 * @property int $currency_id
 * @property string $exchange_rate
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $amount_paid
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property int $net_days
 * @property string|null $notes
 * @property array<string, mixed>|null $notification_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $is_prepayment
 * @property-read bool $is_credit_note
 * @property-read float $amount_outstanding
 * @property-read int $days_overdue
 * @property-read Request $request
 * @property-read BuyerOrder|null $buyerOrder
 * @property-read Currency $currency
 * @property-read BuyerInvoice|null $originalInvoice
 * @property-read Collection<int, BuyerInvoiceItem> $items
 * @property-read Collection<int, BuyerPayment> $payments
 */
#[ObservedBy(BuyerInvoiceObserver::class)]
final class BuyerInvoice extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<BuyerInvoiceFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use LogsErpActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'buyer_order_id',
        'invoice_number',
        'type',
        'status',
        'original_invoice_id',
        'credit_reason',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'tax_total',
        'total',
        'amount_paid',
        'issued_at',
        'due_at',
        'net_days',
        'notes',
        'notification_metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => InvoiceType::STANDARD,
        'status' => InvoiceStatus::DRAFT,
        'exchange_rate' => '1.00000000',
        'subtotal' => '0.0000',
        'tax_total' => '0.0000',
        'total' => '0.0000',
        'amount_paid' => '0.0000',
        'net_days' => 30,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'net_days' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
            'notification_metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return [
            'invoice_number',
            'type',
            'status',
            'total',
            'amount_paid',
            'issued_at',
            'due_at',
            'exchange_rate',
            'currency_id',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment_proof')
            ->useDisk('private');
    }

    /**
     * The request this invoice is for.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The buyer order this invoice is for (if any).
     *
     * @return BelongsTo<BuyerOrder, $this>
     */
    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(BuyerOrder::class);
    }

    /**
     * The currency for this invoice.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The original invoice for credit notes.
     *
     * @return BelongsTo<BuyerInvoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * The items in this invoice.
     *
     * @return HasMany<BuyerInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BuyerInvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * The payments recorded against this invoice.
     *
     * @return HasMany<BuyerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BuyerPayment::class);
    }

    /**
     * Check if this is a prepayment invoice.
     *
     * @return Attribute<bool, never>
     */
    protected function isPrepayment(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->type === InvoiceType::PREPAYMENT,
        );
    }

    /**
     * Check if this is a credit note.
     *
     * @return Attribute<bool, never>
     */
    protected function isCreditNote(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->type === InvoiceType::CREDIT_NOTE,
        );
    }

    /**
     * Get the outstanding amount (total - amount_paid).
     *
     * @return Attribute<float, never>
     */
    protected function amountOutstanding(): Attribute
    {
        return Attribute::make(
            get: fn (): float => max(0, (float) $this->total - (float) $this->amount_paid),
        );
    }

    /**
     * Get the number of days overdue (0 if not overdue).
     *
     * @return Attribute<int, never>
     */
    protected function daysOverdue(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->due_at === null) {
                    return 0;
                }

                if ($this->status === InvoiceStatus::PAID || $this->status === InvoiceStatus::CANCELLED) {
                    return 0;
                }

                $daysOverdue = $this->due_at->diffInDays(now(), absolute: false);

                return max(0, (int) $daysOverdue);
            },
        );
    }

    /**
     * Take an invoice number from the counter, unless one is already held.
     *
     * Idempotent by design: re-issuing, or any second call, must never
     * renumber a document that has already gone out to a buyer.
     */
    public function assignNumberIfMissing(): void
    {
        $number = $this->invoice_number;

        if ($number !== null && $number !== '') {
            return;
        }

        if ($this->team_id === null) {
            throw new \InvalidArgumentException('Cannot number an invoice with no team.');
        }

        $this->invoice_number = self::generateNextNumber($this->team_id);
    }

    /**
     * Mark the invoice as sent.
     *
     * The read-modify-write (re-read status, assign a number, save) is wrapped
     * in a transaction with the row locked: without it, two concurrent calls
     * on the same draft can each pass the transition guard, each allocate a
     * number from the counter, and then the loser's save overwrites the
     * winner's — the invoice ends up with one valid number, but a second
     * number was allocated and never attached to anything (burned). That is
     * not a correctness bug (never a duplicate), but invoice numbers are
     * assigned at issue specifically so discarded drafts cost nothing, so a
     * burned number from a mere race is worth preventing.
     */
    public function markAsSent(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::SENT)) {
            throw new \InvalidArgumentException('Cannot transition to sent from current status.');
        }

        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            // A concurrent markAsSent() already issued this invoice while we
            // waited for the lock: nothing left to do, and re-running the
            // block below would allocate (and burn) another number.
            if ($locked->status !== InvoiceStatus::DRAFT || ($locked->invoice_number !== null && $locked->invoice_number !== '')) {
                return;
            }

            // Numbering happens here, not at create: a draft that is never issued
            // must not consume a number. Assigned after the transition guard so a
            // rejected transition cannot burn one.
            $this->assignNumberIfMissing();

            $this->status = InvoiceStatus::SENT;

            if ($this->issued_at === null) {
                $this->issued_at = now();
            }

            // Calculate due date if not set
            if ($this->due_at === null) {
                $this->due_at = $this->issued_at->copy()->addDays($this->net_days);
            }

            $this->save();
        });
    }

    /**
     * Mark the invoice as partially paid.
     */
    public function markAsPartiallyPaid(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::PARTIAL)) {
            throw new \InvalidArgumentException('Cannot transition to partial from current status.');
        }

        $this->status = InvoiceStatus::PARTIAL;
        $this->save();
    }

    /**
     * Mark the invoice as paid.
     */
    public function markAsPaid(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::PAID)) {
            throw new \InvalidArgumentException('Cannot transition to paid from current status.');
        }

        $this->status = InvoiceStatus::PAID;
        $this->save();
    }

    /**
     * Mark the invoice as overdue.
     */
    public function markAsOverdue(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::OVERDUE)) {
            throw new \InvalidArgumentException('Cannot transition to overdue from current status.');
        }

        $this->status = InvoiceStatus::OVERDUE;
        $this->save();
    }

    /**
     * Cancel the invoice.
     */
    public function cancel(): void
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::CANCELLED)) {
            throw new \InvalidArgumentException('Cannot cancel this invoice.');
        }

        $this->status = InvoiceStatus::CANCELLED;
        $this->save();
    }

    /**
     * Create a credit note from this invoice.
     *
     * @param  array<array{description: string, quantity: float, unit_price: float, tax_rate?: float}>  $items
     */
    public function createCreditNote(array $items, string $reason): self
    {
        if ($this->type === InvoiceType::CREDIT_NOTE) {
            throw new \InvalidArgumentException('Cannot create a credit note from a credit note.');
        }

        $creditNote = new self;
        $creditNote->team_id = $this->team_id;
        /** @var int|null $creatorId */
        $creatorId = auth()->id();
        $creditNote->creator_id = $creatorId;
        $creditNote->request_id = $this->request_id;
        $creditNote->buyer_order_id = $this->buyer_order_id;
        $creditNote->type = InvoiceType::CREDIT_NOTE;
        $creditNote->status = InvoiceStatus::DRAFT;
        $creditNote->original_invoice_id = $this->getKey();
        $creditNote->credit_reason = $reason;
        $creditNote->currency_id = $this->currency_id;
        $creditNote->exchange_rate = $this->exchange_rate;
        $creditNote->net_days = 0;
        $creditNote->save();

        // Create credit note items
        $sortOrder = 0;
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $itemData) {
            $quantity = (float) $itemData['quantity'];
            $unitPrice = (float) $itemData['unit_price'];
            $taxRate = (float) ($itemData['tax_rate'] ?? 0);

            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $lineTax;

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;

            BuyerInvoiceItem::create([
                'buyer_invoice_id' => $creditNote->getKey(),
                'description' => $itemData['description'],
                'quantity' => (string) $quantity,
                'unit_price' => (string) $unitPrice,
                'tax_rate' => (string) $taxRate,
                'tax_inclusive' => false,
                'line_subtotal' => (string) $lineSubtotal,
                'line_tax' => (string) $lineTax,
                'line_total' => (string) $lineTotal,
                'sort_order' => $sortOrder++,
            ]);
        }

        $creditNote->subtotal = (string) $subtotal;
        $creditNote->tax_total = (string) $taxTotal;
        $creditNote->total = (string) ($subtotal + $taxTotal);
        $creditNote->save();

        return $creditNote;
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $this->load('items');

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($this->items as $item) {
            $subtotal += (float) $item->line_subtotal;
            $taxTotal += (float) $item->line_tax;
        }

        $this->subtotal = (string) round($subtotal, 4);
        $this->tax_total = (string) round($taxTotal, 4);
        $this->total = (string) round($subtotal + $taxTotal, 4);
        $this->saveQuietly();
    }

    /**
     * Recalculate amount paid from payments.
     */
    public function recalculateAmountPaid(): void
    {
        // Only CONFIRMED payments reduce the outstanding balance. Pending
        // (buyer-submitted, awaiting staff confirmation) entries do not count.
        $amountPaid = $this->payments()
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount');

        $this->amount_paid = (string) round((float) $amountPaid, 4);
        $this->saveQuietly();
    }

    /**
     * Update status based on payment amounts.
     */
    public function updatePaymentStatus(): void
    {
        $this->recalculateAmountPaid();

        $total = (float) $this->total;
        $amountPaid = (float) $this->amount_paid;

        // Only update if not in draft or cancelled status
        if ($this->status === InvoiceStatus::DRAFT || $this->status === InvoiceStatus::CANCELLED) {
            return;
        }

        // Fully paid
        if ($amountPaid >= $total && $total > 0) {
            if ($this->status !== InvoiceStatus::PAID) {
                $this->status = InvoiceStatus::PAID;
                $this->saveQuietly();
            }

            return;
        }

        // Partially paid
        if ($amountPaid > 0 && $amountPaid < $total) {
            if ($this->status !== InvoiceStatus::PARTIAL && $this->status !== InvoiceStatus::OVERDUE) {
                $this->status = InvoiceStatus::PARTIAL;
                $this->saveQuietly();
            }

            return;
        }

        // No payments or payments deleted - revert to appropriate status
        if ($amountPaid <= 0) {
            // Check overdue
            if ($this->due_at !== null && $this->due_at->isPast()) {
                if ($this->status !== InvoiceStatus::OVERDUE) {
                    $this->status = InvoiceStatus::OVERDUE;
                    $this->saveQuietly();
                }
            } elseif ($this->status === InvoiceStatus::PAID || $this->status === InvoiceStatus::PARTIAL) {
                // Revert to sent status if was previously paid/partial
                $this->status = InvoiceStatus::SENT;
                $this->saveQuietly();
            }
        }
    }

    /**
     * Get the display text for the invoice.
     */
    public function getDisplayTextAttribute(): string
    {
        return sprintf('%s - %s (%s)', $this->invoice_number ?? 'Draft', $this->type->getLabel(), $this->status->getLabel());
    }

    /**
     * Generate the next invoice number for the given team.
     *
     * @see BuyerQuote::generateNextNumber() for why this is a counter row.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_invoice_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_invoice', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    /**
     * Issue a sent invoice from a confirmed buyer order.
     */
    public static function issueFromOrder(BuyerOrder $order): self
    {
        if ($order->status !== OrderStatus::CONFIRMED) {
            throw new \InvalidArgumentException('Only confirmed orders can be invoiced.');
        }

        $existing = self::query()
            ->where('buyer_order_id', $order->getKey())
            ->where('type', InvoiceType::STANDARD)
            ->whereNot('status', InvoiceStatus::CANCELLED)
            ->exists();

        if ($existing) {
            throw new \InvalidArgumentException('This order already has an active invoice.');
        }

        $currencyId = $order->buyerQuote?->currency_id
            ?? $order->team?->getBaseCurrency()?->getKey();

        if ($currencyId === null) {
            throw new \InvalidArgumentException('No currency could be resolved for this invoice.');
        }

        $invoice = new self;
        $invoice->team_id = $order->team_id;
        /** @var int|null $creatorId */
        $creatorId = auth()->id();
        $invoice->creator_id = $creatorId;
        $invoice->request_id = $order->request_id;
        $invoice->buyer_order_id = $order->getKey();
        $invoice->type = InvoiceType::STANDARD;
        $invoice->status = InvoiceStatus::DRAFT;
        $invoice->currency_id = $currencyId;
        $invoice->net_days = $order->payment_terms_days;
        $invoice->save();

        $order->loadMissing('items');
        foreach ($order->items as $orderItem) {
            BuyerInvoiceItem::createFromOrderItem($invoice, $orderItem);
        }

        $invoice->recalculateTotals();
        $invoice->markAsSent();

        return $invoice->refresh();
    }
}
