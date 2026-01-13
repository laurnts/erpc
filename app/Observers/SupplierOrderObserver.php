<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;

final readonly class SupplierOrderObserver
{
    /**
     * Handle the SupplierOrder "creating" event.
     */
    public function creating(SupplierOrder $supplierOrder): void
    {
        // Only set creator_id and team_id from auth if not already provided
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($supplierOrder->creator_id === null) {
                $supplierOrder->creator_id = $user->getKey();
            }

            if ($supplierOrder->team_id === null && $user->currentTeam !== null) {
                $supplierOrder->team_id = $user->currentTeam->getKey();
            }
        }

        // Get team settings
        $team = $supplierOrder->team ?? ($supplierOrder->team_id !== null ? Team::find($supplierOrder->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;

        // Auto-generate PO number if not provided
        /** @var string|null $poNumber */
        $poNumber = $supplierOrder->po_number;
        if ($poNumber === null || $poNumber === '') {
            $supplierOrder->po_number = $this->generatePoNumber($supplierOrder, $settings);
        }

        // Set default payment terms if not provided
        if ($supplierOrder->payment_terms_days === null) {
            $supplierOrder->payment_terms_days = $settings->default_payment_terms_days;
        }
    }

    /**
     * Generate a unique PO number (PO-YYYY-NNNN-A/B/C format).
     *
     * The suffix (A, B, C) is added when there are multiple orders for the same request.
     */
    private function generatePoNumber(SupplierOrder $supplierOrder, TeamErpSettings $settings): string
    {
        $prefix = $settings->supplier_order_number_prefix;
        $year = date('Y');

        // Check if this request already has orders
        $existingOrdersForRequest = SupplierOrder::query()
            ->withTrashed()
            ->where('team_id', $supplierOrder->team_id)
            ->where('request_id', $supplierOrder->request_id)
            ->orderBy('created_at')
            ->get();

        $existingOrdersCount = $existingOrdersForRequest->count();

        // If there are existing orders for this request, use the same base number with suffix
        if ($existingOrdersCount > 0) {
            $firstOrder = $existingOrdersForRequest->first();
            $firstPoNumber = (string) $firstOrder->po_number;

            // Extract the base number from the first order's PO number
            $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)(?:-[A-Z])?$/';
            if (preg_match($regex, $firstPoNumber, $matches)) {
                $baseNumber = (int) $matches[1];
                $basePoNumber = sprintf('%s-%s-%04d', $prefix, $year, $baseNumber);
                $suffix = chr(65 + $existingOrdersCount); // A=65 for second order, B=66 for third, etc.

                return $basePoNumber.'-'.$suffix;
            }
        }

        // Get the highest sequence number for this team and year (for new base number)
        $pattern = $prefix.'-'.$year.'-%';

        $allOrdersForTeam = SupplierOrder::query()
            ->withTrashed()
            ->where('team_id', $supplierOrder->team_id)
            ->where('po_number', 'like', $pattern)
            ->get();

        $nextNumber = 1;
        $regex = '/^'.preg_quote($prefix, '/').'-'.$year.'-(\d+)(?:-[A-Z])?$/';

        foreach ($allOrdersForTeam as $order) {
            if (preg_match($regex, (string) $order->po_number, $matches)) {
                $orderNumber = (int) $matches[1];
                if ($orderNumber >= $nextNumber) {
                    $nextNumber = $orderNumber + 1;
                }
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);
    }
}
