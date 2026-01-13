<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\ExchangeRateObserver;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $from_currency_id
 * @property int $to_currency_id
 * @property string $rate
 * @property \Illuminate\Support\Carbon $effective_date
 * @property-read string $created_by
 */
#[ObservedBy(ExchangeRateObserver::class)]
final class ExchangeRate extends Model
{
    use HasCreator;

    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'rate',
        'effective_date',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_date' => 'date',
        ];
    }

    /**
     * Get the source currency.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /**
     * Get the target currency.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    /**
     * Convert an amount using this exchange rate.
     */
    public function convert(float|int $amount): float
    {
        return (float) $amount * (float) $this->rate;
    }
}
