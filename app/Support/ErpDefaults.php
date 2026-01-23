<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized constants for ERP default values.
 *
 * These defaults are used when team settings don't specify values.
 */
final readonly class ErpDefaults
{
    /**
     * Default quote validity period in days.
     */
    public const int QUOTE_VALIDITY_DAYS = 30;

    /**
     * Default payment terms in days.
     */
    public const int PAYMENT_TERMS_DAYS = 30;

    /**
     * Default currency code.
     */
    public const string CURRENCY_CODE = 'USD';

    /**
     * Default unit of measurement.
     */
    public const string DEFAULT_UNIT = 'pcs';

    /**
     * Default exchange rate (1:1 for base currency).
     */
    public const float DEFAULT_EXCHANGE_RATE = 1.0000;

    /**
     * Default decimal precision for quantities.
     */
    public const int QUANTITY_PRECISION = 4;

    /**
     * Default decimal precision for prices.
     */
    public const int PRICE_PRECISION = 4;

    /**
     * Default decimal precision for percentages.
     */
    public const int PERCENTAGE_PRECISION = 2;

    /**
     * Prevent instantiation.
     */
    private function __construct() {}
}
