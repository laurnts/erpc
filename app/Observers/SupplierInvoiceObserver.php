<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Models\SupplierInvoice;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

final readonly class SupplierInvoiceObserver
{
    /**
     * Handle the SupplierInvoice "creating" event.
     */
    public function creating(SupplierInvoice $supplierInvoice): void
    {
        // Only set creator_id and team_id from auth if not already provided
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($supplierInvoice->creator_id === null) {
                $supplierInvoice->creator_id = $user->getKey();
            }

            if ($supplierInvoice->team_id === null && $user->currentTeam !== null) {
                $supplierInvoice->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate reference number if not provided
        /** @var string|null $referenceNumber */
        $referenceNumber = $supplierInvoice->reference_number;
        if ($referenceNumber === null || $referenceNumber === '') {
            $supplierInvoice->reference_number = $this->generateReferenceNumber($supplierInvoice);
        }

        // Set default due date based on invoice_date and net_days if not provided
        if ($supplierInvoice->due_at === null && $supplierInvoice->invoice_date !== null) {
            $supplierInvoice->due_at = $supplierInvoice->invoice_date->addDays($supplierInvoice->net_days);
        }
    }

    /**
     * Generate a unique reference number (SI-YYYY-NNNN format).
     */
    private function generateReferenceNumber(SupplierInvoice $supplierInvoice): string
    {
        $team = $supplierInvoice->team ?? ($supplierInvoice->team_id !== null ? Team::find($supplierInvoice->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->supplier_invoice_number_prefix;
        $year = date('Y');

        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $supplierInvoice->team_id, 'supplier_invoice', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
