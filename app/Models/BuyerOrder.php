<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\TeamErpSettings;
use App\Enums\OrderStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\BuyerOrderObserver;
use Database\Factories\BuyerOrderFactory;
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
 * @property int|null $team_id
 * @property int $request_id
 * @property int $buyer_id
 * @property int|null $creator_id
 * @property int|null $buyer_quote_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property int $payment_terms_days
 * @property string|null $payment_terms_text
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property Carbon|null $ordered_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read bool $can_edit
 * @property-read bool $exceeds_credit_limit
 * @property-read Request|null $request
 * @property-read Company|null $buyer
 * @property-read BuyerQuote|null $buyerQuote
 */
#[ObservedBy(BuyerOrderObserver::class)]
final class BuyerOrder extends Model implements HasCustomFields
{
    use HasCreator;

    /** @use HasFactory<BuyerOrderFactory> */
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
        'buyer_quote_id',
        'order_number',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'payment_terms_days',
        'payment_terms_text',
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
        'subtotal' => '0.00',
        'tax_total' => '0.00',
        'total' => '0.00',
        'payment_terms_days' => 30,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_terms_days' => 'integer',
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
     * The buyer (company) this order is for.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
    }

    /**
     * The source buyer quote (if created from a quote).
     *
     * @return BelongsTo<BuyerQuote, $this>
     */
    public function buyerQuote(): BelongsTo
    {
        return $this->belongsTo(BuyerQuote::class);
    }

    /**
     * The items in this order.
     *
     * @return HasMany<BuyerOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BuyerOrderItem::class)->orderBy('sort_order');
    }

    /**
     * Check if the order can be edited.
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
     * Check if order total exceeds buyer's available credit.
     *
     * @return Attribute<bool, never>
     */
    protected function exceedsCreditLimit(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $buyer = $this->buyer;
                if ($buyer === null) {
                    return false;
                }

                $availableCredit = (float) $buyer->available_credit;
                $orderTotal = (float) $this->total;

                return $orderTotal > $availableCredit && $availableCredit > 0;
            },
        );
    }

    /**
     * Confirm the order.
     */
    public function confirm(): void
    {
        if (! $this->status->canConfirm()) {
            throw new \InvalidArgumentException('Only draft orders can be confirmed.');
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmed_at = now();
        $this->save();
    }

    /**
     * Cancel the order.
     */
    public function cancel(): void
    {
        if (! $this->status->canCancel()) {
            throw new \InvalidArgumentException('This order cannot be cancelled.');
        }

        $this->status = OrderStatus::CANCELLED;
        $this->save();
    }

    /**
     * Progress to the next status.
     */
    public function progressStatus(): void
    {
        $nextStatus = $this->status->getNextStatus();

        if ($nextStatus === null) {
            throw new \InvalidArgumentException('Order is already in a terminal state.');
        }

        $this->status = $nextStatus;

        if ($nextStatus === OrderStatus::CONFIRMED) {
            $this->confirmed_at = now();
        }

        $this->save();
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
            $lineSubtotal = (float) $item->unit_price_exc_tax * (float) $item->quantity;
            $lineTax = (float) $item->tax_amount * (float) $item->quantity;
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        $this->subtotal = (string) round($subtotal, 2);
        $this->tax_total = (string) round($taxTotal, 2);
        $this->total = (string) round($subtotal + $taxTotal, 2);
        $this->saveQuietly();
    }

    /**
     * Create an order from an accepted buyer quote.
     */
    public static function createFromQuote(BuyerQuote $buyerQuote): self
    {
        if ($buyerQuote->status !== \App\Enums\BuyerQuoteStatus::ACCEPTED) {
            throw new \InvalidArgumentException('Can only create orders from accepted quotes.');
        }

        $order = new self;
        $order->team_id = $buyerQuote->team_id;
        /** @var int|null $creatorId */
        $creatorId = auth()->id();
        $order->creator_id = $creatorId;
        $order->request_id = $buyerQuote->request_id;
        $order->buyer_id = $buyerQuote->buyer_id;
        $order->buyer_quote_id = $buyerQuote->getKey();
        $order->status = OrderStatus::DRAFT;

        // Lock payment terms from quote
        $order->payment_terms_days = $buyerQuote->payment_terms_days;
        $order->payment_terms_text = $buyerQuote->payment_terms_description;

        // Lock totals from quote
        $order->subtotal = (string) round((float) $buyerQuote->subtotal, 2);
        $order->tax_total = (string) round((float) $buyerQuote->tax_total, 2);
        $order->total = (string) round((float) $buyerQuote->total, 2);

        $order->notes = $buyerQuote->notes;
        $order->ordered_at = now();

        $order->save();

        // Copy items with locked tax fields
        foreach ($buyerQuote->items as $quoteItem) {
            BuyerOrderItem::createFromQuoteItem($order, $quoteItem);
        }

        return $order;
    }

    /**
     * Get the display text for the order (for select fields etc).
     */
    public function getDisplayTextAttribute(): string
    {
        return sprintf('%s - %s', $this->order_number, $this->status->getLabel());
    }

    /**
     * Generate the next order number for the given team.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_order_number_prefix;

        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-%';

        // Get the highest sequence number for this team and year
        $lastOrder = self::withTrashed()
            ->where('team_id', $teamId)
            ->where('order_number', 'like', $pattern)
            ->orderByDesc('order_number')
            ->first();

        $nextNumber = 1;
        if ($lastOrder !== null) {
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastOrder->order_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Check credit limit and return warning message if exceeded.
     */
    public function getCreditLimitWarning(): ?string
    {
        $buyer = $this->buyer;
        if ($buyer === null) {
            return null;
        }

        $availableCredit = (float) $buyer->available_credit;
        $creditLimit = (float) $buyer->credit_limit;
        $orderTotal = (float) $this->total;

        // No credit limit set
        if ($creditLimit <= 0) {
            return null;
        }

        // Check if order exceeds available credit
        if ($orderTotal > $availableCredit) {
            return sprintf(
                'Warning: Order total (%s) exceeds available credit (%s). Credit limit: %s, Used: %s.',
                number_format($orderTotal, 2),
                number_format($availableCredit, 2),
                number_format($creditLimit, 2),
                number_format((float) $buyer->credit_used, 2)
            );
        }

        return null;
    }
}
