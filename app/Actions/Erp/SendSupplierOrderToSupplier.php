<?php

declare(strict_types=1);

namespace App\Actions\Erp;

use App\Enums\SupplierOrderSendOutcome;
use App\Mail\Erp\PurchaseOrderToSupplierMail;
use App\Models\SupplierOrder;
use App\Services\Email\EmailTemplateService;
use Illuminate\Support\Facades\Log;

/**
 * Sends an approved supplier order (purchase order) to its supplier: marks the
 * order as sent, then emails the purchase order using the team's configured
 * template. The order is marked sent even when no email is sent (missing
 * address) or the email fails, matching the single-order send behaviour.
 *
 * Shared by the per-row send action and the bulk "Send Purchase Orders to
 * Suppliers" action so both behave identically.
 */
final readonly class SendSupplierOrderToSupplier
{
    public function __construct(private EmailTemplateService $emailService) {}

    /**
     * @throws \InvalidArgumentException When the order is not in a sendable (approved) state.
     */
    public function execute(SupplierOrder $order): SupplierOrderSendOutcome
    {
        $order->markAsSent();

        $supplierEmail = $order->supplier->email ?? null;

        if (empty($supplierEmail)) {
            return SupplierOrderSendOutcome::MarkedWithoutEmail;
        }

        try {
            $settings = $order->team->getErpSettings();
            $this->emailService->sendWithTeamSettings(
                $order->team,
                new PurchaseOrderToSupplierMail($order),
                $supplierEmail,
                $settings->email_template_supplier_order,
            );

            return SupplierOrderSendOutcome::Sent;
        } catch (\Exception $e) {
            Log::error('Failed to send purchase order email', [
                'order_id' => $order->id,
                'supplier_email' => $supplierEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return SupplierOrderSendOutcome::EmailFailed;
        }
    }
}
