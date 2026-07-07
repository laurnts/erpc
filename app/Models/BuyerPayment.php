<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\TeamErpSettings;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Models\Concerns\LogsErpActivity;
use App\Observers\BuyerPaymentObserver;
use Database\Factories\BuyerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property Carbon|null $payment_date
 * @property string|null $reference_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read BuyerInvoice $buyerInvoice
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
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:4',
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
            'payment_date',
            'reference_number',
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
     * The buyer invoice this payment is for.
     *
     * @return BelongsTo<BuyerInvoice, $this>
     */
    public function buyerInvoice(): BelongsTo
    {
        return $this->belongsTo(BuyerInvoice::class);
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
     */
    public static function generateNextNumber(int $teamId): string
    {
        $team = Team::find($teamId);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->buyer_payment_number_prefix;

        $year = date('Y');
        $pattern = $prefix.'-'.$year.'-%';

        // Get the highest sequence number for this team and year
        $lastPayment = self::withTrashed()
            ->where('team_id', $teamId)
            ->where('payment_number', 'like', $pattern)
            ->orderByDesc('payment_number')
            ->first();

        $nextNumber = 1;
        if ($lastPayment !== null) {
            $regex = '/^'.preg_quote((string) $prefix, '/').'-'.$year.'-(\d+)$/';
            if (preg_match($regex, (string) $lastPayment->payment_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
