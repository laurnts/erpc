<?php

declare(strict_types=1);

namespace App\Data\Erp;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Immutable record of an approved Profit & Loss document's financial figures,
 * frozen at the moment of approval. Stored as a JSON cast on
 * profit_and_losses.financial_snapshot; when present, views and PDFs read from
 * it exclusively and perform no live re-resolution.
 */
final class FinancialSnapshot extends Data
{
    public function __construct(
        public float $subtotal,
        public float $taxTotal,
        public float $grandTotal,
        public float $costTotal,
        public float $marginAmount,
        public float $marginPercent,
        public string $currency,
        public CarbonImmutable $snapshotAt,
        public int $buyerQuoteId,
    ) {}
}
