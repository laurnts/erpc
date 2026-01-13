<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_active
 * @property bool $is_default
 */
final class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
        'is_default',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'decimal_places' => 2,
        'is_active' => true,
        'is_default' => false,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get exchange rates where this currency is the source.
     *
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    /**
     * Get exchange rates where this currency is the target.
     *
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    /**
     * Format an amount with this currency's symbol and decimal places.
     */
    public function format(float|int $amount): string
    {
        return $this->symbol.number_format((float) $amount, $this->decimal_places);
    }
}
