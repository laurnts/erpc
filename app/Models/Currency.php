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
 * @property string $thousands_separator
 * @property string $decimal_separator
 * @property string $symbol_position
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
        'thousands_separator',
        'decimal_separator',
        'symbol_position',
        'is_active',
        'is_default',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'decimal_places' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
        'symbol_position' => 'before',
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
     * Supports custom thousands/decimal separators and symbol position.
     *
     * Examples:
     * - USD: $1,000.00
     * - IDR: Rp 1.000,-
     * - EUR: 1.000,00 EUR
     */
    public function format(float|int $amount): string
    {
        $thousandsSep = $this->thousands_separator ?? ',';
        $decimalSep = $this->decimal_separator ?? '.';
        $decimalPlaces = $this->decimal_places ?? 2;

        // Format the number
        $formatted = number_format((float) $amount, $decimalPlaces, $decimalSep, $thousandsSep);

        // For currencies with 0 decimal places (like IDR), append ,- suffix
        if ($decimalPlaces === 0 && $this->code === 'IDR') {
            $formatted .= ',-';
        }

        // Position the symbol
        $symbol = $this->symbol ?? '';
        $position = $this->symbol_position ?? 'before';

        if ($position === 'after') {
            return $formatted.' '.$symbol;
        }

        return $symbol.' '.$formatted;
    }
}
