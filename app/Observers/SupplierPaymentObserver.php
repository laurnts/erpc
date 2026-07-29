<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Models\SupplierPayment;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\Numbering\DocumentNumberAllocator;

final readonly class SupplierPaymentObserver
{
    /**
     * Handle the SupplierPayment "creating" event.
     */
    public function creating(SupplierPayment $supplierPayment): void
    {
        // Only set creator_id and team_id from auth if not already provided
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($supplierPayment->creator_id === null) {
                $supplierPayment->creator_id = $user->getKey();
            }

            if ($supplierPayment->team_id === null && $user->currentTeam !== null) {
                $supplierPayment->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate payment number if not provided
        /** @var string|null $paymentNumber */
        $paymentNumber = $supplierPayment->payment_number;
        if ($paymentNumber === null || $paymentNumber === '') {
            $supplierPayment->payment_number = $this->generatePaymentNumber($supplierPayment);
        }
    }

    /**
     * Handle the SupplierPayment "created" event.
     */
    public function created(SupplierPayment $supplierPayment): void
    {
        $this->updateInvoicePaymentStatus($supplierPayment);
    }

    /**
     * Handle the SupplierPayment "updated" event.
     */
    public function updated(SupplierPayment $supplierPayment): void
    {
        $this->updateInvoicePaymentStatus($supplierPayment);
    }

    /**
     * Handle the SupplierPayment "deleted" event.
     */
    public function deleted(SupplierPayment $supplierPayment): void
    {
        $this->updateInvoicePaymentStatus($supplierPayment);
    }

    /**
     * Handle the SupplierPayment "restored" event.
     */
    public function restored(SupplierPayment $supplierPayment): void
    {
        $this->updateInvoicePaymentStatus($supplierPayment);
    }

    /**
     * Update the invoice amount_paid and status.
     */
    private function updateInvoicePaymentStatus(SupplierPayment $supplierPayment): void
    {
        $invoice = $supplierPayment->supplierInvoice;
        if ($invoice === null) {
            return;
        }

        $invoice->recalculateAmountPaid();
        $invoice->updatePaymentStatus();
    }

    /**
     * Generate a unique payment number (SP-YYYY-NNNN format).
     */
    private function generatePaymentNumber(SupplierPayment $supplierPayment): string
    {
        $team = $supplierPayment->team ?? ($supplierPayment->team_id !== null ? Team::find($supplierPayment->team_id) : null);
        $settings = $team?->getErpSettings() ?? new TeamErpSettings;
        $prefix = $settings->supplier_payment_number_prefix;
        $year = date('Y');

        $sequence = app(DocumentNumberAllocator::class)
            ->next((int) $supplierPayment->team_id, 'supplier_payment', $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
