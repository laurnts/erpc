<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\SupplierInvoiceObserver;
use Database\Factories\SupplierInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int $request_id
 * @property int $supplier_id
 * @property int|null $supplier_order_id
 * @property string $invoice_number
 * @property string $reference_number
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
 * @property Carbon|null $invoice_date
 * @property Carbon|null $due_at
 * @property int $net_days
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $is_credit_note
 * @property-read float $amount_outstanding
 * @property-read int $days_overdue
 * @property-read float $base_currency_total
 * @property-read string $formatted_total
 * @property-read string $formatted_amount_outstanding
 * @property-read Request $request
 * @property-read Company $supplier
 * @property-read SupplierOrder|null $supplierOrder
 * @property-read Currency $currency
 * @property-read SupplierInvoice|null $originalInvoice
 * @property-read Collection<int, SupplierInvoiceItem> $items
 * @property-read Collection<int, SupplierPayment> $payments
 */
#[ObservedBy(SupplierInvoiceObserver::class)]
final class SupplierInvoice extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<SupplierInvoiceFactory> */
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
        'supplier_order_id',
        'invoice_number',
        'reference_number',
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
        'invoice_date',
        'due_at',
        'net_days',
        'notes',
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
            'invoice_date' => 'date',
            'due_at' => 'date',
        ];
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('invoice_document')
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
     * The supplier (company) who issued this invoice.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    /**
     * The supplier order this invoice is for.
     *
     * @return BelongsTo<SupplierOrder, $this>
     */
    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
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
     * The original invoice (for credit notes).
     *
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * The items in this invoice.
     *
     * @return HasMany<SupplierInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * The payments made against this invoice.
     *
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
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
     * Get the amount outstanding.
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

                $daysOverdue = $this->due_at->diffInDays(now(), false);

                return max(0, (int) $daysOverdue);
            },
        );
    }

    /**
     * Get the total in base currency using the exchange rate.
     *
     * @return Attribute<float, never>
     */
    protected function baseCurrencyTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) $this->total * (float) $this->exchange_rate,
        );
    }

    /**
     * Get formatted total in invoice currency.
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
     * Get formatted amount outstanding in invoice currency.
     *
     * @return Attribute<string, never>
     */
    protected function formattedAmountOutstanding(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $currency = $this->currency;
                if ($currency === null) {
                    return number_format($this->amount_outstanding, 2);
                }

                return $currency->format($this->amount_outstanding);
            },
        );
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = $items->sum(fn (SupplierInvoiceItem $item): float => (float) $item->line_subtotal);
        $taxTotal = $items->sum(fn (SupplierInvoiceItem $item): float => (float) $item->line_tax);
        $total = $items->sum(fn (SupplierInvoiceItem $item): float => (float) $item->line_total);

        $this->subtotal = (string) round($subtotal, 4);
        $this->tax_total = (string) round($taxTotal, 4);
        $this->total = (string) round($total, 4);

        $this->saveQuietly();
    }

    /**
     * Recalculate amount paid from payments.
     */
    public function recalculateAmountPaid(): void
    {
        $amountPaid = $this->payments()->sum('amount');
        $this->amount_paid = (string) round((float) $amountPaid, 4);
        $this->saveQuietly();
    }

    /**
     * Update status based on payment amount.
     */
    public function updatePaymentStatus(): void
    {
        $total = (float) $this->total;
        $amountPaid = (float) $this->amount_paid;

        if ($amountPaid >= $total) {
            $this->status = InvoiceStatus::PAID;
        } elseif ($amountPaid > 0) {
            $this->status = InvoiceStatus::PARTIAL;
        }

        $this->saveQuietly();
    }

    /**
     * Mark the invoice as sent/received.
     */
    public function markAsSent(): void
    {
        if ($this->status !== InvoiceStatus::DRAFT) {
            return;
        }

        $this->status = InvoiceStatus::SENT;
        $this->save();
    }

    /**
     * Mark the invoice as partially paid.
     */
    public function markAsPartiallyPaid(): void
    {
        if ($this->status === InvoiceStatus::PAID || $this->status === InvoiceStatus::CANCELLED) {
            return;
        }

        $this->status = InvoiceStatus::PARTIAL;
        $this->save();
    }

    /**
     * Mark the invoice as fully paid.
     */
    public function markAsPaid(): void
    {
        if ($this->status === InvoiceStatus::CANCELLED) {
            return;
        }

        $this->status = InvoiceStatus::PAID;
        $this->save();
    }

    /**
     * Create a credit note for this invoice.
     *
     * @param  array<int, array{supplier_invoice_item_id: int, quantity: float, unit_price: float}>  $items
     */
    public function createCreditNote(array $items, string $reason): self
    {
        $creditNote = new self;
        $creditNote->team_id = $this->team_id;
        /** @var int|null $creatorId */
        $creatorId = auth()->id();
        $creditNote->creator_id = $creatorId;
        $creditNote->request_id = $this->request_id;
        $creditNote->supplier_id = $this->supplier_id;
        $creditNote->supplier_order_id = $this->supplier_order_id;
        $creditNote->invoice_number = 'CN-'.$this->invoice_number;
        $creditNote->type = InvoiceType::CREDIT_NOTE;
        $creditNote->status = InvoiceStatus::DRAFT;
        $creditNote->original_invoice_id = $this->getKey();
        $creditNote->credit_reason = $reason;
        $creditNote->currency_id = $this->currency_id;
        $creditNote->exchange_rate = $this->exchange_rate;
        $creditNote->net_days = $this->net_days;
        $creditNote->invoice_date = now();
        $creditNote->due_at = now()->addDays($this->net_days);
        $creditNote->save();

        $sortOrder = 0;
        foreach ($items as $itemData) {
            /** @var SupplierInvoiceItem|null $originalItem */
            $originalItem = SupplierInvoiceItem::find($itemData['supplier_invoice_item_id']);
            if ($originalItem === null) {
                continue;
            }

            $creditNoteItem = new SupplierInvoiceItem;
            $creditNoteItem->supplier_invoice_id = $creditNote->getKey();
            $creditNoteItem->supplier_order_item_id = $originalItem->supplier_order_item_id;
            $creditNoteItem->request_item_id = $originalItem->request_item_id;
            $creditNoteItem->article_id = $originalItem->article_id;
            $creditNoteItem->description = 'Credit: '.$originalItem->description;
            $creditNoteItem->quantity = (string) $itemData['quantity'];
            $creditNoteItem->unit = $originalItem->unit;
            $creditNoteItem->unit_price = (string) $itemData['unit_price'];
            $creditNoteItem->tax_code_id = $originalItem->tax_code_id;
            $creditNoteItem->tax_rate = $originalItem->tax_rate;
            $creditNoteItem->tax_inclusive = $originalItem->tax_inclusive;
            $creditNoteItem->sort_order = $sortOrder++;
            $creditNoteItem->calculateLineTotal();
            $creditNoteItem->save();
        }

        $creditNote->recalculateTotals();

        return $creditNote;
    }

    /**
     * Get the consolidated cost summary.
     *
     * @return array{subtotal: float, tax_total: float, total: float, amount_paid: float, amount_outstanding: float, base_currency_total: float, currency_code: string|null, exchange_rate: float}
     */
    public function getCostSummary(): array
    {
        return [
            'subtotal' => (float) $this->subtotal,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'amount_outstanding' => $this->amount_outstanding,
            'base_currency_total' => $this->base_currency_total,
            'currency_code' => $this->currency?->code,
            'exchange_rate' => (float) $this->exchange_rate,
        ];
    }
}
