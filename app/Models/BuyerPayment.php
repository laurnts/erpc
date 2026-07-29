<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\TeamErpSettings;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Models\Concerns\LogsErpActivity;
use App\Observers\BuyerPaymentObserver;
use App\Services\Erp\Numbering\DocumentNumberAllocator;
use Database\Factories\BuyerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int|null $team_id
 * @property int|null $creator_id
 * @property int $buyer_invoice_id
 * @property string $payment_number
 * @property PaymentMethod $payment_method
 * @property string $amount
 * @property PaymentStatus $status
 * @property string|null $submitted_actor_type
 * @property int|null $submitted_by_id
 * @property int|null $confirmed_by_id
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $payment_date
 * @property string|null $reference_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read BuyerInvoice $buyerInvoice
 * @property-read User|null $submittedBy
 * @property-read User|null $confirmedBy
 */
#[ObservedBy(BuyerPaymentObserver::class)]
final class BuyerPayment extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<BuyerPaymentFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use LogsErpActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_invoice_id',
        'payment_number',
        'payment_method',
        'amount',
        'status',
        'submitted_actor_type',
        'submitted_by_id',
        'confirmed_by_id',
        'confirmed_at',
        'payment_date',
        'reference_number',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_method' => PaymentMethod::BANK_TRANSFER,
        'amount' => '0.0000',
        'status' => PaymentStatus::Confirmed->value,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:4',
            'status' => PaymentStatus::class,
            'confirmed_at' => 'datetime',
            'payment_date' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return [
            'payment_number',
            'payment_method',
            'amount',
            'status',
            'payment_date',
            'reference_number',
        ];
    }

    /**
     * Scope to only confirmed payments.
     *
     * @param  Builder<BuyerPayment>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function confirmed(Builder $query): void
    {
        $query->where('status', PaymentStatus::Confirmed->value);
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
     * The buyer invoice this payment is for.
     *
     * @return BelongsTo<BuyerInvoice, $this>
     */
    public function buyerInvoice(): BelongsTo
    {
        return $this->belongsTo(BuyerInvoice::class);
    }

    /**
     * The user who submitted this payment entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * The staff user who confirmed this payment entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /**
     * Confirm a pending payment entry, releasing it against the invoice balance.
     */
    public function confirm(User $staff): void
    {
        $this->status = PaymentStatus::Confirmed;
        $this->confirmed_by_id = $staff->getKey();
        $this->confirmed_at = now();
        $this->save();
    }

    /**
     * Get the display text for the payment.
     */
    public function getDisplayTextAttribute(): string
    {
        return sprintf('%s - %s (%s)', $this->payment_number, number_format((float) $this->amount, 2), $this->payment_method->getLabel());
    }

    /**
     * Generate the next payment number for the given team.
     *
     * @see BuyerQuote::generateNextNumber() for why this is a counter row.
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_payment_number_prefix;

        $year = date('Y');
        $sequence = app(DocumentNumberAllocator::class)->next($teamId, 'buyer_payment', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
