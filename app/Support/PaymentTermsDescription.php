<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PrepaymentType;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;

final readonly class PaymentTermsDescription
{
    /**
     * Build numbered payment-term lines from a buyer quote (prepayment + schedule).
     *
     * @return list<string>
     */
    public static function linesFromBuyerQuote(BuyerQuote $quote): array
    {
        if (! $quote->relationLoaded('paymentTerms')) {
            $quote->load('paymentTerms');
        }

        $lines = [];
        $lineNumber = 1;

        $prepaymentLine = self::formatPrepaymentLine($quote);
        if ($prepaymentLine !== null) {
            $lines[] = sprintf('%d. %s', $lineNumber, $prepaymentLine);
            $lineNumber++;
        }

        $termNumber = 1;
        foreach ($quote->paymentTerms->sortBy('sort_order') as $term) {
            if ((int) $term->percentage <= 0) {
                continue;
            }

            $lines[] = sprintf(
                '%d. Payment term %d: %d days - %d%%',
                $lineNumber,
                $termNumber,
                (int) $term->due_days,
                (int) $term->percentage,
            );
            $lineNumber++;
            $termNumber++;
        }

        return $lines;
    }

    public static function formatFromBuyerQuote(BuyerQuote $quote): string
    {
        return implode("\n", self::linesFromBuyerQuote($quote));
    }

    /**
     * Resolve display text for a buyer order, rebuilding from the source quote when
     * the locked text predates prepayment-aware formatting.
     */
    public static function formatForBuyerOrder(BuyerOrder $order): string
    {
        $text = $order->payment_terms_text;
        if ($text !== null && str_contains($text, 'Prepayment')) {
            return $text;
        }

        if ($order->buyerQuote !== null) {
            $order->buyerQuote->loadMissing('paymentTerms');

            return self::formatFromBuyerQuote($order->buyerQuote);
        }

        return $text ?? '';
    }

    /**
     * Resolve the effective prepayment percentage, including legacy data stored in prepayment_amount.
     */
    public static function effectivePrepaymentPercent(BuyerQuote $quote): int
    {
        if ($quote->prepayment_type !== PrepaymentType::PERCENT) {
            return 0;
        }

        return (int) $quote->prepayment_percent > 0
            ? (int) $quote->prepayment_percent
            : (int) round((float) $quote->prepayment_amount);
    }

    /**
     * Whether the quote has a non-zero prepayment configured.
     */
    public static function hasPrepayment(BuyerQuote $quote): bool
    {
        if ($quote->prepayment_type === PrepaymentType::PERCENT) {
            return self::effectivePrepaymentPercent($quote) > 0;
        }

        return (float) $quote->prepayment_amount > 0;
    }

    private static function formatPrepaymentLine(BuyerQuote $quote): ?string
    {
        if ($quote->prepayment_type === PrepaymentType::PERCENT) {
            $percent = self::effectivePrepaymentPercent($quote);
            if ($percent <= 0) {
                return null;
            }

            return sprintf('Prepayment: %d%%', $percent);
        }

        $amount = (float) $quote->prepayment_amount;
        if ($amount <= 0) {
            return null;
        }

        return sprintf('Prepayment: %s', number_format($amount, 2));
    }
}
