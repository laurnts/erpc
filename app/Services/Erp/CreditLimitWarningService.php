<?php

declare(strict_types=1);

namespace App\Services\Erp;

use App\Models\Company;

/**
 * Service to check and warn about credit limit violations.
 *
 * Provides methods to check if a new order would exceed a buyer's credit limit
 * and returns warning data for display in the UI.
 */
final readonly class CreditLimitWarningService
{
    /**
     * Check if a new order amount would exceed the buyer's credit limit.
     *
     * @param  float  $newOrderAmount  The amount of the new order to be placed
     * @return array{
     *     exceeds_limit: bool,
     *     credit_limit: float,
     *     credit_used: float,
     *     available_credit: float,
     *     new_order_amount: float,
     *     projected_credit_used: float,
     *     over_limit_amount: float,
     *     has_credit_limit: bool,
     *     warning_message: string|null,
     *     warning_level: string|null
     * }
     */
    public function checkCreditLimit(Company $buyer, float $newOrderAmount): array
    {
        $creditLimit = (float) $buyer->credit_limit;
        $creditUsed = $buyer->credit_exposure;
        $availableCredit = $creditLimit - $creditUsed;

        // If no credit limit is set (0 or negative), there's no limit to enforce
        $hasCreditLimit = $creditLimit > 0;

        if (! $hasCreditLimit) {
            return [
                'exceeds_limit' => false,
                'credit_limit' => $creditLimit,
                'credit_used' => $creditUsed,
                'available_credit' => $availableCredit,
                'new_order_amount' => $newOrderAmount,
                'projected_credit_used' => $creditUsed + $newOrderAmount,
                'over_limit_amount' => 0.0,
                'has_credit_limit' => false,
                'warning_message' => null,
                'warning_level' => null,
            ];
        }

        $projectedCreditUsed = $creditUsed + $newOrderAmount;
        $exceedsLimit = $newOrderAmount > $availableCredit;
        $overLimitAmount = max(0.0, $newOrderAmount - $availableCredit);

        // Calculate warning level
        $warningLevel = $this->calculateWarningLevel(
            $availableCredit,
            $creditLimit,
            $newOrderAmount,
            $exceedsLimit
        );

        $warningMessage = $this->buildWarningMessage(
            $buyer,
            $creditLimit,
            $creditUsed,
            $availableCredit,
            $newOrderAmount,
            $overLimitAmount,
            $exceedsLimit
        );

        return [
            'exceeds_limit' => $exceedsLimit,
            'credit_limit' => $creditLimit,
            'credit_used' => $creditUsed,
            'available_credit' => $availableCredit,
            'new_order_amount' => $newOrderAmount,
            'projected_credit_used' => $projectedCreditUsed,
            'over_limit_amount' => $overLimitAmount,
            'has_credit_limit' => true,
            'warning_message' => $warningMessage,
            'warning_level' => $warningLevel,
        ];
    }

    /**
     * Check if a buyer is approaching their credit limit.
     *
     * @param  float  $thresholdPercent  Percentage of credit limit to trigger warning (default 80%)
     * @return array{
     *     approaching_limit: bool,
     *     credit_limit: float,
     *     credit_used: float,
     *     available_credit: float,
     *     usage_percent: float,
     *     threshold_percent: float,
     *     warning_message: string|null
     * }
     */
    public function checkApproachingLimit(Company $buyer, float $thresholdPercent = 80.0): array
    {
        $creditLimit = (float) $buyer->credit_limit;
        $creditUsed = $buyer->credit_exposure;
        $availableCredit = $creditLimit - $creditUsed;

        if ($creditLimit <= 0) {
            return [
                'approaching_limit' => false,
                'credit_limit' => $creditLimit,
                'credit_used' => $creditUsed,
                'available_credit' => $availableCredit,
                'usage_percent' => 0.0,
                'threshold_percent' => $thresholdPercent,
                'warning_message' => null,
            ];
        }

        $usagePercent = ($creditUsed / $creditLimit) * 100;
        $approachingLimit = $usagePercent >= $thresholdPercent;

        $warningMessage = null;
        if ($approachingLimit) {
            $formattedUsed = number_format($creditUsed, 2);
            $formattedLimit = number_format($creditLimit, 2);
            $formattedPercent = number_format($usagePercent, 1);

            $warningMessage = "{$buyer->name} has used {$formattedPercent}% of their credit limit ";
            $warningMessage .= "({$formattedUsed} of {$formattedLimit}).";
        }

        return [
            'approaching_limit' => $approachingLimit,
            'credit_limit' => $creditLimit,
            'credit_used' => $creditUsed,
            'available_credit' => $availableCredit,
            'usage_percent' => round($usagePercent, 2),
            'threshold_percent' => $thresholdPercent,
            'warning_message' => $warningMessage,
        ];
    }

    /**
     * Calculate the warning level based on credit usage.
     */
    private function calculateWarningLevel(
        float $availableCredit,
        float $creditLimit,
        float $newOrderAmount,
        bool $exceedsLimit
    ): ?string {
        if ($exceedsLimit) {
            return 'danger';
        }

        // Calculate remaining credit after order
        $remainingAfterOrder = $availableCredit - $newOrderAmount;
        $remainingPercent = ($remainingAfterOrder / $creditLimit) * 100;

        if ($remainingPercent < 10) {
            return 'warning';
        }

        if ($remainingPercent < 20) {
            return 'info';
        }

        return null;
    }

    /**
     * Build a warning message for display.
     */
    private function buildWarningMessage(
        Company $buyer,
        float $creditLimit,
        float $creditUsed,
        float $availableCredit,
        float $newOrderAmount,
        float $overLimitAmount,
        bool $exceedsLimit
    ): ?string {
        if (! $exceedsLimit) {
            // Check if this order would use a significant portion of remaining credit
            $remainingAfterOrder = $availableCredit - $newOrderAmount;
            $remainingPercent = ($remainingAfterOrder / $creditLimit) * 100;

            if ($remainingPercent < 20) {
                return sprintf(
                    'After this order, %s will have only %s (%.1f%%) credit remaining.',
                    $buyer->name,
                    number_format($remainingAfterOrder, 2),
                    $remainingPercent
                );
            }

            return null;
        }

        return sprintf(
            'Warning: Order total (%s) exceeds available credit (%s) by %s. Credit limit: %s, Used: %s.',
            number_format($newOrderAmount, 2),
            number_format($availableCredit, 2),
            number_format($overLimitAmount, 2),
            number_format($creditLimit, 2),
            number_format($creditUsed, 2)
        );
    }

    /**
     * Get a summary of the buyer's credit status.
     *
     * @return array{
     *     buyer_name: string,
     *     credit_limit: float,
     *     credit_used: float,
     *     available_credit: float,
     *     usage_percent: float,
     *     is_on_hold: bool,
     *     on_hold_reason: string|null,
     *     status: string,
     *     status_color: string
     * }
     */
    public function getCreditSummary(Company $buyer): array
    {
        $creditLimit = (float) $buyer->credit_limit;
        $creditUsed = $buyer->credit_exposure;
        $availableCredit = $creditLimit - $creditUsed;
        $usagePercent = $creditLimit > 0 ? ($creditUsed / $creditLimit) * 100 : 0;

        $status = $this->determineStatus($buyer, $usagePercent);
        $statusColor = $this->determineStatusColor($buyer, $usagePercent);

        return [
            'buyer_name' => $buyer->name,
            'credit_limit' => $creditLimit,
            'credit_used' => $creditUsed,
            'available_credit' => $availableCredit,
            'usage_percent' => round($usagePercent, 2),
            'is_on_hold' => $buyer->is_on_hold,
            'on_hold_reason' => $buyer->on_hold_reason,
            'status' => $status,
            'status_color' => $statusColor,
        ];
    }

    /**
     * Determine the credit status text.
     */
    private function determineStatus(Company $buyer, float $usagePercent): string
    {
        if ($buyer->is_on_hold) {
            return 'On Hold';
        }

        $creditLimit = (float) $buyer->credit_limit;
        if ($creditLimit <= 0) {
            return 'No Limit';
        }

        if ($usagePercent >= 100) {
            return 'Over Limit';
        }

        if ($usagePercent >= 90) {
            return 'Critical';
        }

        if ($usagePercent >= 80) {
            return 'Warning';
        }

        return 'Good';
    }

    /**
     * Determine the status color for UI display.
     */
    private function determineStatusColor(Company $buyer, float $usagePercent): string
    {
        if ($buyer->is_on_hold) {
            return 'danger';
        }

        $creditLimit = (float) $buyer->credit_limit;
        if ($creditLimit <= 0) {
            return 'gray';
        }

        if ($usagePercent >= 100) {
            return 'danger';
        }

        if ($usagePercent >= 90) {
            return 'danger';
        }

        if ($usagePercent >= 80) {
            return 'warning';
        }

        return 'success';
    }
}
