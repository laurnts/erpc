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
    /**
     * @param  array<int, array<string, mixed>>  $supplierGroups  Per-supplier line detail frozen
     *                                                            with the totals; each group holds supplierName, supplierCurrency, costTotal, netSell,
     *                                                            marginAmount, grossTotal, hasChildLines and a list of lines (label, isChild, quantity,
     *                                                            unitLabel, costPrice, sellPrice, lineTax, marginPercent, lineTotal). Empty on
     *                                                            snapshots captured before line detail was frozen; views fall back to live rows then.
     */
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
        public array $supplierGroups = [],
    ) {}
}
