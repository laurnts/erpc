<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\SupplierPaymentObserver;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int $supplier_invoice_id
 * @property string $payment_number
 * @property PaymentMethod|null $payment_method
 * @property string $amount
 * @property Carbon $payment_date
 * @property string|null $reference_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 * @property-read string $formatted_amount
 * @property-read SupplierInvoice $supplierInvoice
 */
#[ObservedBy(SupplierPaymentObserver::class)]
final class SupplierPayment extends Model implements HasMedia
{
    use HasCreator;

    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory;

    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_invoice_id',
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
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment_proof')
            ->useDisk('private');
    }

    /**
     * The supplier invoice this payment is for.
     *
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /**
     * Get formatted amount in invoice currency.
     *
     * @return Attribute<string, never>
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $currency = $this->supplierInvoice->currency;
                if ($currency === null) {
                    return number_format((float) $this->amount, 2);
                }

                return $currency->format((float) $this->amount);
            },
        );
    }
}
